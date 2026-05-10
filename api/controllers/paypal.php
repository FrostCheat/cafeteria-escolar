<?php
$db = getDB();

if ($resource === 'paypal') {
    $auth = requireAuth();

    if ($method === 'POST' && $action === 'create-order') {
        $userId = (int)$auth['id'];
        try {
            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci JOIN products p ON p.id=ci.product_id
                WHERE ci.user_id=? AND p.active=1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) jsonError('El carrito está vacío');

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError("Stock insuficiente para: {$item['name']}. Disponible: {$item['stock']}", 400);
                }
            }

            $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $totalUSD = convertCOPtoUSD($total);

            $accessToken = getPayPalAccessToken();
            $idempotencyKey = generateToken(16);

            $orderPayload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'cafeteria_' . $userId . '_' . time(),
                    'description'  => 'Cafetería Santa Juana — ' . count($cartItems) . ' producto(s)',
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => number_format($totalUSD, 2, '.', ''),
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value'         => number_format($totalUSD, 2, '.', ''),
                            ],
                        ],
                    ],
                    'items' => array_map(fn($i) => [
                        'name'        => substr($i['name'], 0, 127),
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value'         => number_format(convertCOPtoUSD($i['price']), 2, '.', ''),
                        ],
                        'quantity' => (string)$i['quantity'],
                    ], $cartItems),
                ]],
                'application_context' => [
                    'brand_name'          => 'Cafetería Santa Juana',
                    'user_action'         => 'PAY_NOW',
                    'payment_method'      => ['payer_selected' => 'PAYPAL', 'payee_preferred' => 'UNRESTRICTED'],
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ];

            $response = paypalRequest('POST', '/v2/checkout/orders', $orderPayload, $accessToken, [
                'PayPal-Request-Id: ' . $idempotencyKey,
            ]);

            if (empty($response['id'])) {
                logError('PayPal create-order failed', $response);
                jsonError('No se pudo crear la orden de PayPal', 502);
            }

            jsonResponse(['paypal_order_id' => $response['id']]);
        } catch (PDOException $e) {
            logDatabaseError('paypal/create-order', $e);
            jsonError('Error al procesar la solicitud', 500);
        }
    }

    if ($method === 'POST' && $action === 'capture-order') {
        $userId      = (int)$auth['id'];
        $paypalOrderId  = trim($body['paypal_order_id'] ?? '');
        $fundingSource  = trim($body['funding_source']   ?? 'paypal');

        if (!$paypalOrderId) jsonError('paypal_order_id requerido');
        if (!preg_match('/^[A-Z0-9]{10,50}$/', $paypalOrderId)) jsonError('paypal_order_id inválido');

        $existing = $db->prepare("SELECT id, status FROM orders WHERE paypal_order_id=?");
        $existing->execute([$paypalOrderId]);
        $dup = $existing->fetch();
        if ($dup) {
            if ($dup['status'] === 'paid') jsonError('Esta orden ya fue procesada', 409);
            jsonError('Orden duplicada', 409);
        }

        try {
            $accessToken = getPayPalAccessToken();

            $orderDetail = paypalRequest('GET', '/v2/checkout/orders/' . $paypalOrderId, null, $accessToken);
            if (($orderDetail['status'] ?? '') !== 'APPROVED') {
                jsonError('La orden de PayPal no está aprobada', 402);
            }

            $ppUserId = $orderDetail['payer']['payer_id'] ?? null;

            $captureIdempotencyKey = generateToken(16);
            $capture = paypalRequest(
                'POST',
                '/v2/checkout/orders/' . $paypalOrderId . '/capture',
                new stdClass(),
                $accessToken,
                ['PayPal-Request-Id: cap_' . $captureIdempotencyKey]
            );

            $captureStatus = $capture['status'] ?? '';
            if ($captureStatus !== 'COMPLETED') {
                logError('PayPal capture not completed', $capture);
                jsonError('El pago no fue completado por PayPal', 402);
            }

            $captureUnit = $capture['purchase_units'][0]['payments']['captures'][0] ?? null;
            if (!$captureUnit || ($captureUnit['status'] ?? '') !== 'COMPLETED') {
                jsonError('Captura de pago inválida', 402);
            }

            $captureId      = $captureUnit['id'];
            $capturedAmount = (float)($captureUnit['amount']['value'] ?? 0);
            $capturedCurrency = $captureUnit['amount']['currency_code'] ?? 'USD';

            $dupCapture = $db->prepare("SELECT id FROM orders WHERE paypal_capture_id=?");
            $dupCapture->execute([$captureId]);
            if ($dupCapture->fetch()) jsonError('Capture ID ya utilizado', 409);

            $stmt = $db->prepare("
                SELECT ci.quantity, p.id as product_id, p.name, p.price, p.stock
                FROM cart_items ci JOIN products p ON p.id=ci.product_id
                WHERE ci.user_id=? AND p.active=1
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) jsonError('El carrito está vacío o expiró');

            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    jsonError("Stock insuficiente para: {$item['name']}", 400);
                }
            }

            $total    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
            $totalUSD = convertCOPtoUSD($total);

            if (abs($capturedAmount - $totalUSD) > 0.10) {
                logError('PayPal amount mismatch', [
                    'expected' => $totalUSD,
                    'captured' => $capturedAmount,
                    'currency' => $capturedCurrency,
                    'user_id'  => $userId,
                    'capture'  => $captureId,
                ]);
                jsonError('El monto capturado no coincide con el pedido', 402);
            }

            $qrToken = generateToken(24);
            $queueCfg   = $db->query("SELECT enabled FROM queue_config WHERE id=1")->fetch();
            $turnEnabled = $queueCfg && $queueCfg['enabled'];
            $turnNumber  = null;

            if ($turnEnabled) {
                $lastTurn   = $db->query("SELECT MAX(turn_number) as max_turn FROM orders WHERE DATE(created_at)=CURDATE()")->fetch();
                $turnNumber = ((int)($lastTurn['max_turn'] ?? 0)) + 1;
            }

            $qrData = generateQRData(['type' => 'order', 'token' => $qrToken, 'user_id' => $userId, 'total' => $total]);

            $db->beginTransaction();

            $db->prepare("
                INSERT INTO orders
                    (user_id, total, status, payment_method, paypal_order_id, paypal_capture_id, paypal_funding_source, qr_code, qr_token, turn_number, paid_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ")->execute([
                $userId, $total, 'paid', 'paypal',
                $paypalOrderId, $captureId,
                substr(preg_replace('/[^a-z_]/', '', strtolower($fundingSource)), 0, 50),
                $qrData, $qrToken, $turnNumber,
            ]);
            $orderId = (int)$db->lastInsertId();

            foreach ($cartItems as $item) {
                $db->prepare("
                    INSERT INTO order_items (order_id,product_id,product_name,product_price,quantity,subtotal)
                    VALUES (?,?,?,?,?,?)
                ")->execute([
                    $orderId, $item['product_id'], $item['name'],
                    $item['price'], $item['quantity'],
                    $item['price'] * $item['quantity'],
                ]);
                $db->prepare("UPDATE products SET stock=stock-? WHERE id=?")->execute([$item['quantity'], $item['product_id']]);
            }

            $db->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
            emitEvent($db, 'order_created', ['order_id' => $orderId, 'user_id' => $userId]);
            $db->commit();

            $order = $db->query("SELECT * FROM orders WHERE id=$orderId")->fetch();
            $order['items'] = $cartItems;
            jsonResponse($order, 201);
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            logDatabaseError('paypal/capture-order', $e);
            jsonError('Error al registrar el pago', 500);
        }
    }

    jsonError('Ruta paypal no encontrada', 404);
}

function getPayPalAccessToken(): string {
    $env      = PAYPAL_ENV === 'production' ? 'api-m' : 'api-m.sandbox';
    $endpoint = "https://{$env}.paypal.com/v1/oauth2/token";

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logError('PayPal token request failed', ['code' => $httpCode, 'body' => $body]);
        jsonError('No se pudo autenticar con PayPal', 502);
    }

    $data = json_decode($body, true);
    if (empty($data['access_token'])) jsonError('Token de PayPal inválido', 502);
    return $data['access_token'];
}

function paypalRequest(string $method, string $path, $payload, string $token, array $extraHeaders = []): array {
    $env      = PAYPAL_ENV === 'production' ? 'api-m' : 'api-m.sandbox';
    $url      = "https://{$env}.paypal.com{$path}";
    $headers  = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ], $extraHeaders);

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $opts);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($body, true) ?? [];
    if ($httpCode >= 500) {
        logError('PayPal API server error', ['code' => $httpCode, 'path' => $path, 'body' => $body]);
        jsonError('Error en el servicio de PayPal', 502);
    }

    return $data;
}

function convertCOPtoUSD(float $cop): float {
    $rate = defined('COP_USD_RATE') ? COP_USD_RATE : 0.00024;
    return round($cop * $rate, 2);
}