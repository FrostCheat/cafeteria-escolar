<?php
$db = getDB();

if ($resource === 'paypal') {

    if ($method === 'GET' && $action === 'config') {
        $clientId = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
        $env      = defined('PAYPAL_ENV')       ? PAYPAL_ENV       : 'sandbox';
        jsonResponse(['client_id' => $clientId, 'env' => $env]);
    }

    $auth = requireAuth();

    if ($method === 'POST' && $action === 'create-order') {
        $userId = (int)$auth['id'];

        try {
            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.user_id = ? AND p.active = 1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) jsonError('El carrito está vacío', 400);

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError('Stock insuficiente para: ' . $item['name'] . '. Disponible: ' . $item['stock'], 400);
                }
            }

            $totalCOP  = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $totalUSD  = convertCOPtoUSD($totalCOP);

            $accessToken = getPayPalAccessToken();

            $env      = defined('PAYPAL_ENV') ? PAYPAL_ENV : 'sandbox';
            $baseUrl  = $env === 'production'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            $orderPayload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'description'       => 'Cafetería Santa Juana — ' . count($cartItems) . ' producto(s)',
                    'custom_id'         => 'user_' . $userId . '_' . time(),
                    'soft_descriptor'   => 'CafeteriaSJ',
                    'amount'            => [
                        'currency_code' => 'USD',
                        'value'         => number_format($totalUSD, 2, '.', ''),
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value'         => number_format($totalUSD, 2, '.', ''),
                            ],
                        ],
                    ],
                    'items' => array_map(fn($item) => [
                        'name'        => mb_substr($item['name'], 0, 127),
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value'         => number_format(convertCOPtoUSD((float)$item['price']), 2, '.', ''),
                        ],
                        'quantity'    => (string)$item['quantity'],
                        'category'    => 'PHYSICAL_GOODS',
                    ], $cartItems),
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'brand_name'          => 'Cafetería Santa Juana',
                            'locale'              => 'es-CO',
                            'user_action'         => 'PAY_NOW',
                            'shipping_preference' => 'NO_SHIPPING',
                        ],
                    ],
                ],
            ];

            $ch = curl_init($baseUrl . '/v2/checkout/orders');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($orderPayload),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'PayPal-Request-Id: cafeteria-' . $userId . '-' . uniqid(),
                    'Prefer: return=representation',
                ],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                logError('PayPal create-order curl error', ['error' => $curlErr]);
                jsonError('Error de conexión con PayPal. Usa efectivo por favor.', 502);
            }

            $data = json_decode($response, true);

            if ($httpCode < 200 || $httpCode >= 300) {
                logError('PayPal create-order API error', ['status' => $httpCode, 'body' => $data]);
                jsonError('PayPal rechazó la creación de la orden. Usa efectivo por favor.', 502);
            }

            jsonResponse(['id' => $data['id']]);

        } catch (PDOException $e) {
            logDatabaseError('paypal/create-order', $e);
            jsonError('Error de base de datos al crear la orden.', 500);
        }
    }

    if ($method === 'POST' && $action === 'capture-order') {
        $userId      = (int)$auth['id'];
        $paypalOrderId = trim($body['paypal_order_id'] ?? '');

        if (!$paypalOrderId) jsonError('paypal_order_id es requerido', 400);
        if (!preg_match('/^[A-Z0-9\-]{5,100}$/', $paypalOrderId)) jsonError('paypal_order_id inválido', 400);

        $dupOrder = $db->prepare("SELECT id, status FROM orders WHERE paypal_order_id = ?");
        $dupOrder->execute([$paypalOrderId]);
        $existingOrder = $dupOrder->fetch();
        if ($existingOrder) {
            if ($existingOrder['status'] === 'paid') jsonError('Esta orden ya fue pagada anteriormente', 409);
            jsonError('Orden duplicada', 409);
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

            if (empty($cartItems)) jsonError('El carrito está vacío o expiró', 400);

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError('Stock insuficiente para: ' . $item['name'], 400);
                }
            }

            $totalCOP    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $accessToken = getPayPalAccessToken();

            $env     = defined('PAYPAL_ENV') ? PAYPAL_ENV : 'sandbox';
            $baseUrl = $env === 'production'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            $ch = curl_init($baseUrl . '/v2/checkout/orders/' . urlencode($paypalOrderId) . '/capture');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '{}',
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'PayPal-Request-Id: capture-' . $userId . '-' . uniqid(),
                    'Prefer: return=representation',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                logError('PayPal capture curl error', ['error' => $curlErr, 'order_id' => $paypalOrderId]);
                jsonError('Error de conexión al capturar el pago. Contacta al administrador.', 502);
            }

            $captureData = json_decode($response, true);

            if ($httpCode === 422 && isset($captureData['details'][0]['issue'])) {
                $issue = $captureData['details'][0]['issue'];
                if ($issue === 'ORDER_ALREADY_CAPTURED') jsonError('Esta orden ya fue capturada.', 409);
                if ($issue === 'INSTRUMENT_DECLINED') jsonError('El método de pago fue rechazado. Intenta con otro.', 402);
                if ($issue === 'PAYER_ACTION_REQUIRED') jsonError('PayPal requiere acción adicional del comprador.', 422);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                logError('PayPal capture API error', [
                    'status'   => $httpCode,
                    'body'     => $captureData,
                    'order_id' => $paypalOrderId,
                    'user_id'  => $userId,
                ]);
                jsonError('Error al capturar el pago con PayPal (HTTP ' . $httpCode . '). Contacta al administrador con el ID: ' . $paypalOrderId, 502);
            }

            $captureStatus = $captureData['status'] ?? '';
            if ($captureStatus !== 'COMPLETED') {
                logError('PayPal capture not completed', ['status' => $captureStatus, 'order_id' => $paypalOrderId]);
                jsonError('El pago no fue completado por PayPal (estado: ' . $captureStatus . ').', 402);
            }

            $captureUnit   = $captureData['purchase_units'][0]['payments']['captures'][0] ?? null;
            $captureId     = $captureUnit['id']                    ?? null;
            $capturedAmount = (float)($captureUnit['amount']['value'] ?? 0);
            $fundingSource = $captureData['payment_source']
                ? array_key_first($captureData['payment_source'])
                : 'paypal';

            if (!$captureId) {
                logError('PayPal capture missing capture ID', ['body' => $captureData]);
                jsonError('Respuesta de PayPal incompleta. Contacta al administrador.', 502);
            }

            $dupCapture = $db->prepare("SELECT id FROM orders WHERE paypal_capture_id = ?");
            $dupCapture->execute([$captureId]);
            if ($dupCapture->fetch()) jsonError('Este pago ya fue registrado.', 409);

            $totalUSD     = convertCOPtoUSD($totalCOP);
            $tolerance    = max(0.50, $totalUSD * 0.05);
            if ($capturedAmount > 0 && abs($capturedAmount - $totalUSD) > $tolerance) {
                logError('PayPal capture amount mismatch', [
                    'expected_usd'  => $totalUSD,
                    'captured_usd'  => $capturedAmount,
                    'total_cop'     => $totalCOP,
                    'capture_id'    => $captureId,
                ]);
            }

            $qrToken = generateToken(24);

            $queueCfg    = $db->query("SELECT enabled FROM queue_config WHERE id = 1")->fetch();
            $turnEnabled = $queueCfg && $queueCfg['enabled'];
            $turnNumber  = null;

            if ($turnEnabled) {
                $lastTurn   = $db->query("SELECT MAX(turn_number) as max_turn FROM orders WHERE DATE(created_at) = CURDATE()")->fetch();
                $turnNumber = ((int)($lastTurn['max_turn'] ?? 0)) + 1;
            }

            $qrData            = generateQRData(['type' => 'order', 'token' => $qrToken, 'user_id' => $userId, 'total' => $totalCOP]);
            $cleanFundingSource = substr(preg_replace('/[^a-z_]/', '', strtolower($fundingSource)), 0, 50);

            $db->beginTransaction();

            $db->prepare("
                INSERT INTO orders
                    (user_id, total, status, payment_method, paypal_order_id, paypal_capture_id,
                     paypal_funding_source, qr_code, qr_token, turn_number, paid_at)
                VALUES (?, ?, 'paid', 'paypal', ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $userId, $totalCOP, $paypalOrderId, $captureId,
                $cleanFundingSource, $qrData, $qrToken, $turnNumber,
            ]);

            $orderId = (int)$db->lastInsertId();

            foreach ($cartItems as $item) {
                $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $orderId, $item['product_id'], $item['name'],
                    $item['price'], $item['quantity'], $item['price'] * $item['quantity'],
                ]);
                $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
                   ->execute([$item['quantity'], $item['product_id']]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
            emitEvent($db, 'order_created', ['order_id' => $orderId, 'user_id' => $userId]);
            $db->commit();

            $order          = $db->query("SELECT * FROM orders WHERE id = $orderId")->fetch();
            $order['items'] = $cartItems;

            jsonResponse($order, 201);

        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            logDatabaseError('paypal/capture-order', $e);
            jsonError('Error interno al registrar el pedido. El pago fue procesado. Guarda tu ID de orden: ' . $paypalOrderId, 500);
        }
    }

    jsonError('Ruta paypal no encontrada', 404);
}

function getPayPalAccessToken(): string {
    $clientId     = defined('PAYPAL_CLIENT_ID')     ? PAYPAL_CLIENT_ID     : '';
    $clientSecret = defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : '';
    $env          = defined('PAYPAL_ENV')           ? PAYPAL_ENV           : 'sandbox';

    if (!$clientId || !$clientSecret) {
        logError('PayPal credentials not configured');
        throw new RuntimeException('PayPal no está configurado correctamente.');
    }

    $baseUrl = $env === 'production'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';

    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_USERPWD        => $clientId . ':' . $clientSecret,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        logError('PayPal OAuth curl error', ['error' => $curlErr]);
        throw new RuntimeException('Error de conexión al autenticar con PayPal.');
    }

    if ($httpCode !== 200) {
        logError('PayPal OAuth failed', ['status' => $httpCode, 'body' => $response]);
        throw new RuntimeException('No se pudo autenticar con PayPal (HTTP ' . $httpCode . ').');
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        logError('PayPal OAuth missing token', ['body' => $data]);
        throw new RuntimeException('PayPal no devolvió un token de acceso válido.');
    }

    return $data['access_token'];
}

function convertCOPtoUSD(float $cop): float {
    $rate = defined('COP_USD_RATE') ? (float)COP_USD_RATE : 0.00024;
    return max(0.01, round($cop * $rate, 2));
}