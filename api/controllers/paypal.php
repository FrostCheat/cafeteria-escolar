<?php
$db = getDB();

if ($resource === 'paypal') {
    $auth = requireAuth();

    if ($method === 'POST' && $action === 'capture-order') {
        $userId         = (int)$auth['id'];
        $paypalOrderId  = trim($body['paypal_order_id'] ?? '');
        $captureId      = trim($body['capture_id']      ?? '');
        $capturedAmount = floatval($body['amount']       ?? 0);
        $fundingSource  = trim($body['funding_source']  ?? 'paypal');

        if (!$paypalOrderId) {
            jsonError('paypal_order_id es requerido', 400);
        }
        if (!$captureId) {
            jsonError('capture_id es requerido', 400);
        }
        if ($capturedAmount <= 0) {
            jsonError('El monto capturado no es válido', 400);
        }
        if (!preg_match('/^[A-Z0-9\-]{5,100}$/', $paypalOrderId)) {
            jsonError('paypal_order_id tiene formato inválido', 400);
        }
        if (!preg_match('/^[A-Z0-9\-]{5,100}$/', $captureId)) {
            jsonError('capture_id tiene formato inválido', 400);
        }

        $dupCapture = $db->prepare("SELECT id FROM orders WHERE paypal_capture_id = ?");
        $dupCapture->execute([$captureId]);
        if ($dupCapture->fetch()) {
            jsonError('Este pago ya fue procesado anteriormente', 409);
        }

        $dupOrder = $db->prepare("SELECT id, status FROM orders WHERE paypal_order_id = ?");
        $dupOrder->execute([$paypalOrderId]);
        $existingOrder = $dupOrder->fetch();
        if ($existingOrder) {
            if ($existingOrder['status'] === 'paid') {
                jsonError('Esta orden de PayPal ya fue registrada como pagada', 409);
            }
            jsonError('Orden duplicada detectada', 409);
        }

        try {
            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.user_id = ? AND p.active = 1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                jsonError('El carrito está vacío o expiró', 400);
            }

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError('Stock insuficiente para: ' . $item['name'] . '. Disponible: ' . $item['stock'], 400);
                }
            }

            $totalCOP    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $totalUSD    = convertCOPtoUSD($totalCOP);
            $tolerance   = 0.20;

            if (abs($capturedAmount - $totalUSD) > $tolerance) {
                logError('PayPal amount mismatch', [
                    'expected_usd'   => $totalUSD,
                    'captured_usd'   => $capturedAmount,
                    'total_cop'      => $totalCOP,
                    'user_id'        => $userId,
                    'paypal_order'   => $paypalOrderId,
                    'capture_id'     => $captureId,
                ]);
                jsonError('El monto del pago no coincide con el total del pedido', 402);
            }

            $qrToken = generateToken(24);

            $queueCfg    = $db->query("SELECT enabled FROM queue_config WHERE id = 1")->fetch();
            $turnEnabled = $queueCfg && $queueCfg['enabled'];
            $turnNumber  = null;

            if ($turnEnabled) {
                $lastTurn   = $db->query("SELECT MAX(turn_number) as max_turn FROM orders WHERE DATE(created_at) = CURDATE()")->fetch();
                $turnNumber = ((int)($lastTurn['max_turn'] ?? 0)) + 1;
            }

            $qrData = generateQRData([
                'type'    => 'order',
                'token'   => $qrToken,
                'user_id' => $userId,
                'total'   => $totalCOP,
            ]);

            $cleanFundingSource = substr(preg_replace('/[^a-z_]/', '', strtolower($fundingSource)), 0, 50);

            $db->beginTransaction();

            $db->prepare("
                INSERT INTO orders
                    (user_id, total, status, payment_method, paypal_order_id, paypal_capture_id,
                     paypal_funding_source, qr_code, qr_token, turn_number, paid_at)
                VALUES (?, ?, 'paid', 'paypal', ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $userId,
                $totalCOP,
                $paypalOrderId,
                $captureId,
                $cleanFundingSource,
                $qrData,
                $qrToken,
                $turnNumber,
            ]);

            $orderId = (int)$db->lastInsertId();

            foreach ($cartItems as $item) {
                $db->prepare("
                    INSERT INTO order_items
                        (order_id, product_id, product_name, product_price, quantity, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $item['price'] * $item['quantity'],
                ]);

                $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([
                    $item['quantity'],
                    $item['product_id'],
                ]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);

            emitEvent($db, 'order_created', ['order_id' => $orderId, 'user_id' => $userId]);

            $db->commit();

            $order = $db->query("SELECT * FROM orders WHERE id = $orderId")->fetch();
            $order['items'] = $cartItems;

            logInfo('PayPal order registered successfully', [
                'order_id'   => $orderId,
                'user_id'    => $userId,
                'total_cop'  => $totalCOP,
                'total_usd'  => $capturedAmount,
                'capture_id' => $captureId,
                'funding'    => $cleanFundingSource,
            ]);

            jsonResponse($order, 201);
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logDatabaseError('paypal/capture-order', $e);
            jsonError('Error interno al registrar el pedido. Contacta al administrador.', 500);
        }
    }

    if ($method === 'GET' && $action === 'config') {
        $clientId = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
        $env      = defined('PAYPAL_ENV')       ? PAYPAL_ENV       : 'sandbox';
        jsonResponse([
            'client_id' => $clientId,
            'env'       => $env,
        ]);
    }

    jsonError('Ruta paypal no encontrada', 404);
}

function convertCOPtoUSD(float $cop): float {
    $rate = defined('COP_USD_RATE') ? (float)COP_USD_RATE : 0.00024;
    return max(0.01, round($cop * $rate, 2));
}