<?php
// ========================
// Database Configuration
// ========================
define('DB_HOST', 'localhost:8080');
define('DB_NAME', 'jr_rodriguez_meat_dealer');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ========================
// Application Configuration
// ========================
define('APP_NAME', 'JR Rodriguez Meat Dealer');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/jr-rodriguez-meat-dealer/');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300);
define('ORDER_PREFIX', 'JRR-');
define('BACKUP_PATH', __DIR__ . '/../backups/');
define('BACKUP_MAX_FILES', 30);

// ========================
// Security Configuration
// ========================
define('JWT_SECRET', 'change-this-to-a-very-long-random-string');
define('ENCRYPTION_KEY', 'another-very-long-random-string');
define('CSRF_TOKEN_SECRET', 'your-csrf-secret-key-change-me');
define('PASSWORD_RESET_EXPIRE', 3600);

// ========================
// Email Configuration
// ========================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@jrrodriguez.com');
define('FROM_NAME', 'JR Rodriguez Meat Dealer');

// ========================
// Payment Configuration
// ========================
define('GCASH_MERCHANT_ID', 'your-gcash-merchant-id');
define('GCASH_SECRET_KEY', 'your-gcash-secret-key');
define('PAYMAYA_PUBLIC_KEY', 'your-paymaya-public-key');
define('PAYMAYA_SECRET_KEY', 'your-paymaya-secret-key');

// ========================
// SMS Configuration
// ========================
define('SMS_API_KEY', 'your-sms-api-key');
define('SMS_SENDER_NAME', 'JRRodriguez');

// ========================
// File Upload Configuration
// ========================
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// ========================
// Session Configuration
// ========================
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

// ========================
// Error Reporting
// ========================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ========================
// Timezone
// ========================
date_default_timezone_set('Asia/Manila');

// ========================
// Database Class
// ========================
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Query execution failed");
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->query($sql, $data);
        
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

// ========================
// Utility Functions
// ========================
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^(\+63|0)?[0-9]{10}$/', $phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message, 'success' => false], $status);
}

function sendSuccess($data = [], $message = 'Success') {
    sendResponse(['data' => $data, 'message' => $message, 'success' => true]);
}

function logError($message, $context = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log(json_encode($logData) . "\n", 3, __DIR__ . '/../logs/app_errors.log');
}

function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendError('Authentication required', 401);
    }
    return $_SESSION;
}

function checkPermission($requiredRole) {
    $session = requireAuth();
    if ($session['user_role'] !== $requiredRole && $session['user_role'] !== 'admin') {
        sendError('Insufficient permissions', 403);
    }
}

function uploadFile($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('No file uploaded');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds maximum limit');
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        throw new Exception('File type not allowed');
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid MIME type');
    }
    
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload file');
    }
    
    return $fileName;
}

function sendEmail($to, $subject, $body, $isHTML = true) {
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function sendSMS($phone, $message) {
    $data = [
        'phone' => $phone,
        'message' => $message,
        'sender' => SMS_SENDER_NAME
    ];
    
    logError('SMS would be sent', $data);
    return true;
}

function generateQRCode($data, $size = 200) {
    $baseUrl = 'https://chart.googleapis.com/chart';
    $params = [
        'chs' => $size . 'x' . $size,
        'cht' => 'qr',
        'chl' => urlencode($data),
        'choe' => 'UTF-8'
    ];
    
    return $baseUrl . '?' . http_build_query($params);
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function calculateDeliveryFee($distance) {
    $baseFee = 50;
    $perKmFee = 10;
    
    if ($distance <= 2) {
        return $baseFee;
    }
    
    return $baseFee + (($distance - 2) * $perKmFee);
}

// ========================
// Database Initialization
// ========================
function initializeDatabase() {
    $db = Database::getInstance();
    
    try {
        $db->beginTransaction();

        // Users table
        $db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                address TEXT NOT NULL,
                customer_type VARCHAR(50) NOT NULL,
                role VARCHAR(20) DEFAULT 'customer',
                email_verified BOOLEAN DEFAULT FALSE,
                phone_verified BOOLEAN DEFAULT FALSE,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (email),
                UNIQUE KEY (phone),
                INDEX idx_users_role (role),
                INDEX idx_users_status (status)
            ) ENGINE=InnoDB
        ");

        // Password resets table
        $db->query("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_resets_email (email),
                INDEX idx_password_resets_token (token),
                FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Products table
        $db->query("
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) NOT NULL,
                category VARCHAR(100) NOT NULL,
                image_url VARCHAR(500),
                stock_quantity INT DEFAULT 0,
                min_stock_level INT DEFAULT 5,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_products_category (category),
                INDEX idx_products_status (status),
                INDEX idx_products_price (price),
                FULLTEXT idx_products_search (name, description)
            ) ENGINE=InnoDB
        ");

        // Orders table
        $db->query("
            CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                order_number VARCHAR(50) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                delivery_fee DECIMAL(10,2) DEFAULT 0,
                total_amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                payment_status VARCHAR(20) DEFAULT 'pending',
                order_status VARCHAR(20) DEFAULT 'pending',
                delivery_address TEXT NOT NULL,
                delivery_date DATE,
                delivery_time VARCHAR(20),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (order_number),
                INDEX idx_orders_user_id (user_id),
                INDEX idx_orders_status (order_status),
                INDEX idx_orders_date (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Order items table
        $db->query("
            CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_items_order (order_id),
                INDEX idx_order_items_product (product_id),
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Shopping cart table
        $db->query("
            CREATE TABLE IF NOT EXISTS shopping_cart (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (user_id, product_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Favorites table
        $db->query("
            CREATE TABLE IF NOT EXISTS favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (user_id, product_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        // Insert sample products
        $sampleProducts = [
            ['Premium Pork Belly', 'Fresh, high-quality pork belly perfect for BBQ and roasting', 280.00, 'per kg', 'fresh', 50],
            ['Pork Shoulder', 'Tender pork shoulder ideal for slow cooking and stews', 260.00, 'per kg', 'fresh', 30],
            ['Ground Pork', 'Freshly ground pork meat for burgers, meatballs, and more', 240.00, 'per kg', 'ground', 25],
            ['Pork Chops', 'Premium cut pork chops, perfect for grilling and frying', 320.00, 'per kg', 'fresh', 20],
            ['Pork Ribs', 'Succulent pork ribs, great for BBQ and slow cooking', 300.00, 'per kg', 'fresh', 15],
            ['Pork Tenderloin', 'The most tender cut of pork, perfect for special occasions', 450.00, 'per kg', 'specialty', 10],
            ['Pork Sausages', 'Homemade pork sausages with traditional spices', 280.00, 'per kg', 'specialty', 35],
            ['Bacon Strips', 'Crispy bacon strips, perfect for breakfast', 380.00, 'per kg', 'specialty', 22]
        ];

        foreach ($sampleProducts as $product) {
            $existing = $db->fetch("SELECT id FROM products WHERE name = ?", [$product[0]]);
            if (!$existing) {
                $db->query("
                    INSERT INTO products (name, description, price, unit, category, stock_quantity) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ", $product);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// ========================
// Error Handlers
// ========================
function handleError($errno, $errstr, $errfile, $errline) {
    $errorData = [
        'type' => 'PHP Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logError('PHP Error occurred', $errorData);
    return true;
}

function handleException($exception) {
    $errorData = [
        'type' => 'Uncaught Exception',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logError('Uncaught Exception', $errorData);
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        sendError('An unexpected error occurred', 500);
    }
}

set_error_handler('handleError');
set_exception_handler('handleException');

// ========================
// Initialization
// ========================
$requiredDirs = ['logs', 'uploads', 'backups'];
foreach ($requiredDirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
}

try {
    initializeDatabase();
} catch (Exception $e) {
    logError('Database initialization failed', ['error' => $e->getMessage()]);
    if (ini_get('display_errors')) {
        throw $e;
    }
}
?>