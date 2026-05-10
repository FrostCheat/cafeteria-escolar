<?php
define('DB_HOST', getenv('DB_HOST') ?: 'sql306.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_41745979_cafeteria_escolar');
define('DB_USER', getenv('DB_USER') ?: 'if0_41745979');
define('DB_PASS', getenv('DB_PASS') ?: 'f7DorrtCWyEbTC');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'cafeteria_secret_key_2024_xK9#mP');
define('UPLOAD_PATH', __DIR__ . '/../../imgs/uploads/');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8080');

define('PAYPAL_CLIENT_ID',     getenv('PAYPAL_CLIENT_ID')     ?: 'AQnW8s6sj1shs06clbCmXNC0JMv6y_UJPVTqAoBXVX5Xy66OzvnE9gwKkLaXJV0Rv45zFfH0DiWTpnED');
define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: 'ENfv1ASxVCeAZZYM-eqP5IiuS3BXjzSedoSFquf2O2MYYbkCldsuIUoO-GPLO5sZS-QNrz3oKSzZsVMa');
define('PAYPAL_ENV',           getenv('PAYPAL_ENV')           ?: 'sandbox');

date_default_timezone_set('America/Bogota');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='-05:00'",
        ]);
        initDB($pdo);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB Connection failed']);
        exit;
    }
    return $pdo;
}

function initDB(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name TEXT NOT NULL,
            birth_date TEXT NOT NULL,
            grade TEXT NOT NULL,
            doc_type TEXT NOT NULL,
            doc_number VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            blocked INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_doc_number (doc_number),
            UNIQUE INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            image VARCHAR(255),
            qr_code VARCHAR(255),
            active INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active_category (active, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY unique_cart (user_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
            paypal_order_id VARCHAR(100) DEFAULT NULL,
            paypal_capture_id VARCHAR(100) DEFAULT NULL,
            paypal_funding_source VARCHAR(50) DEFAULT NULL,
            qr_code VARCHAR(255),
            qr_token VARCHAR(100),
            notes TEXT,
            turn_number INT DEFAULT NULL,
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE INDEX idx_qr_token (qr_token),
            UNIQUE INDEX idx_paypal_capture (paypal_capture_id),
            INDEX idx_status (status),
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_created_date (created_at),
            INDEX idx_paypal_order (paypal_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            INDEX idx_order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue_config (
            id INT PRIMARY KEY DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            current_turn INT NOT NULL DEFAULT 0,
            current_order_id INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            payload JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_id (id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!$pdo->query("SELECT id FROM queue_config WHERE id=1")->fetch()) {
        $pdo->exec("INSERT INTO queue_config (id, enabled, current_turn) VALUES (1, 0, 0)");
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@santajuanalestonnac.edu.co']);
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("
            INSERT INTO users (full_name, birth_date, grade, doc_type, doc_number, email, password, role)
            VALUES ('Administrador', '1990-01-01', '11A', 'CC', '000000001', 'admin@santajuanalestonnac.edu.co', ?, 'admin')
        ")->execute([$hash]);
    }

    try {
        $pdo->exec("DELETE FROM events WHERE created_at < NOW() - INTERVAL 2 HOUR");
    } catch (PDOException $e) {}
}