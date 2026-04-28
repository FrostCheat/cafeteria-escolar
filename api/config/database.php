<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

define('DB_HOST', 'sql306.infinityfree.com');
define('DB_NAME', 'if0_41745979_cafeteria');
define('DB_USER', 'if0_41745979');
define('DB_PASS', 'cNLNhBke4hmJ');
define('JWT_SECRET', 'cafeteria_secret_key_2024_xK9#mP');
define('UPLOAD_PATH', __DIR__ . '/../../imgs/uploads/');
define('BASE_URL', 'http://localhost:8080');
date_default_timezone_set('America/Bogota');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            logInfo('Conectando a la base de datos', ['host' => DB_HOST, 'db' => DB_NAME]);
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            logInfo('Conexión a base de datos exitosa');
            initDB($pdo);
        } catch(PDOException $e) {
            logDatabaseError('getDB', $e);
            throw new Exception('DB Connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function initDB(PDO $pdo): void {
    try {
        logInfo('Inicializando tablas');

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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
                qr_code VARCHAR(255),
                qr_token VARCHAR(100),
                notes TEXT,
                turn_number INT DEFAULT NULL,
                paid_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE INDEX idx_qr_token (qr_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        try {
            $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'turn_number'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN turn_number INT DEFAULT NULL AFTER notes");
                logInfo('Columna turn_number agregada a orders');
            }
        } catch (PDOException $e) {
            logWarning('No se pudo verificar columna turn_number', ['error' => $e->getMessage()]);
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                product_price DECIMAL(10,2) NOT NULL,
                quantity INT NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
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

        $existing = $pdo->query("SELECT id FROM queue_config WHERE id=1")->fetch();
        if (!$existing) {
            $pdo->exec("INSERT INTO queue_config (id, enabled, current_turn) VALUES (1, 0, 0)");
            logInfo('queue_config inicializado');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute(['admin@santajuanalestonnac.edu.co']);
        $admin = $stmt->fetch();

        if (!$admin) {
            logInfo('Creando usuario administrador por defecto');
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, birth_date, grade, doc_type, doc_number, email, password, role)
                VALUES ('Administrador', '1990-01-01', '11A', 'CC', '000000001', 'admin@santajuanalestonnac.edu.co', ?, 'admin')
            ");
            $stmt->execute([$hash]);
            logInfo('Usuario administrador creado');
        }

        logInfo('Inicialización de tablas completada');

    } catch (PDOException $e) {
        logDatabaseError('initDB', $e);
        throw new Exception('InitDB failed: ' . $e->getMessage());
    }
}