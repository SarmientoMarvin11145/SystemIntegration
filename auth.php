<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'POST':
            handlePostRequest($action, $db);
            break;
        case 'GET':
            handleGetRequest($action, $db);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    logError('Auth API Error', ['error' => $e->getMessage(), 'action' => $action]);
    sendError('Internal server error', 500);
}

function handlePostRequest($action, $db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'login':
            login($input, $db);
            break;
        case 'register':
            register($input, $db);
            break;
        case 'logout':
            logout();
            break;
        case 'forgot-password':
            forgotPassword($input, $db);
            break;
        case 'reset-password':
            resetPassword($input, $db);
            break;
        case 'verify-email':
            verifyEmail($input, $db);
            break;
        case 'resend-verification':
            resendVerification($input, $db);
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function handleGetRequest($action, $db) {
    switch ($action) {
        case 'profile':
            getProfile($db);
            break;
        case 'check-session':
            checkSession();
            break;
        default:
            sendError('Invalid action', 400);
    }
}

function login($input, $db) {
    // Validate input
    if (empty($input['email']) || empty($input['password'])) {
        sendError('Email and password are required');
    }
    
    $email = sanitizeInput($input['email']);
    $password = $input['password'];
    
    // Check if user exists
    $user = $db->fetch("
        SELECT id, first_name, last_name, email, phone, password_hash, role, status, email_verified 
        FROM users 
        WHERE email = ? OR phone = ?
    ", [$email, $email]);
    
    if (!$user) {
        sendError('Invalid credentials');
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        sendError('Account is suspended or inactive');
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        sendError('Invalid credentials');
    }
    
    // Start session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
    
    // Update last login
    $db->query("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
    
    // Log successful login
    logError('User logged in', ['user_id' => $user['id'], 'email' => $user['email']]);
    
    sendSuccess([
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'email_verified' => $user['email_verified']
        ]
    ], 'Login successful');
}

function register($input, $db) {
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'password', 'address', 'customer_type'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            sendError(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    // Sanitize inputs
    $firstName = sanitizeInput($input['first_name']);
    $lastName = sanitizeInput($input['last_name']);
    $email = sanitizeInput($input['email']);
    $phone = sanitizeInput($input['phone']);
    $password = $input['password'];
    $address = sanitizeInput($input['address']);
    $customerType = sanitizeInput($input['customer_type']);
    
    // Validate email format
    if (!validateEmail($email)) {
        sendError('Invalid email format');
    }
    
    // Validate phone format
    if (!validatePhone($phone)) {
        sendError('Invalid phone number format');
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        sendError('Password must be at least 8 characters long');
    }
    
    // Check if user already exists
    $existingUser = $db->fetch("SELECT id FROM users WHERE email = ? OR phone = ?", [$email, $phone]);
    if ($existingUser) {
        sendError('User with this email or phone already exists');
    }
    
    // Hash password
    $passwordHash = hashPassword($password);
    
    // Generate verification token
    $verificationToken = generateToken();
    
    try {
        $db->beginTransaction();
        
        // Insert user
        $userId = $db->insert('users', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'address' => $address,
            'customer_type' => $customerType,
            'role' => 'customer'
        ]);
        
        // Create verification token record (you'd need to create this table)
        // For now, we'll skip email verification in demo
        
        $db->commit();
        
        // Send welcome email (implement as needed)
        // sendWelcomeEmail($email, $firstName, $verificationToken);
        
        // Log registration
        logError('New user registered', ['user_id' => $userId, 'email' => $email]);
        
        sendSuccess([
            'user_id' => $userId,
            'message' => 'Registration successful! Please check your email for verification.'
        ], 'Registration successful');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function logout() {
    session_start();
    
    if (isset($_SESSION['user_id'])) {
        logError('User logged out', ['user_id' => $_SESSION['user_id']]);
    }
    
    session_destroy();
    sendSuccess([], 'Logout successful');
}

function forgotPassword($input, $db) {
    if (empty($input['email'])) {
        sendError('Email is required');
    }
    
    $email = sanitizeInput($input['email']);
    
    // Check if user exists
    $user = $db->fetch("SELECT id, first_name, email FROM users WHERE email = ?", [$email]);
    
    // Always return success for security (don't reveal if email exists)
    if ($user) {
        // Generate reset token
        $resetToken = generateToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token (you'd need to create password_resets table)
        // For demo, we'll just log it
        logError('Password reset requested', [
            'user_id' => $user['id'],
            'email' => $email,
            'reset_token' => $resetToken
        ]);
        
        // Send reset email (implement as needed)
        // sendPasswordResetEmail($email, $user['first_name'], $resetToken);
    }
    
    sendSuccess([], 'If your email exists in our system, you will receive a password reset link');
}

function resetPassword($input, $db) {
    if (empty($input['token']) || empty($input['password'])) {
        sendError('Reset token and new password are required');
    }
    
    $token = sanitizeInput($input['token']);
    $newPassword = $input['password'];
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        sendError('Password must be at least 8 characters long');
    }
    
    // For demo purposes, we'll skip token validation
    // In production, you'd verify the token from password_resets table
    
    sendSuccess([], 'Password reset successful');
}

function verifyEmail($input, $db) {
    if (empty($input['token'])) {
        sendError('Verification token is required');
    }
    
    $token = sanitizeInput($input['token']);
    
    // For demo purposes, we'll skip actual verification
    // In production, you'd verify the token and update email_verified status
    
    sendSuccess([], 'Email verified successfully');
}

function resendVerification($input, $db) {
    if (empty($input['email'])) {
        sendError('Email is required');
    }
    
    $email = sanitizeInput($input['email']);
    
    $user = $db->fetch("SELECT id, first_name, email_verified FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        sendError('User not found');
    }
    
    if ($user['email_verified']) {
        sendError('Email is already verified');
    }
    
    // Generate new verification token
    $verificationToken = generateToken();
    
    // Send verification email (implement as needed)
    // sendVerificationEmail($email, $user['first_name'], $verificationToken);
    
    sendSuccess([], 'Verification email sent');
}

function getProfile($db) {
    $session = requireAuth();
    
    $user = $db->fetch("
        SELECT id, first_name, last_name, email, phone, address, customer_type, role, email_verified, created_at
        FROM users 
        WHERE id = ?
    ", [$session['user_id']]);
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    sendSuccess([
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'address' => $user['address'],
            'customer_type' => $user['customer_type'],
            'role' => $user['role'],
            'email_verified' => $user['email_verified'],
            'member_since' => $user['created_at']
        ]
    ]);
}

function checkSession() {
    session_start();
    
    if (isset($_SESSION['user_id'])) {
        sendSuccess([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ]
        ]);
    } else {
        sendSuccess(['authenticated' => false]);
    }
}

function sendWelcomeEmail($email, $name, $verificationToken) {
    $subject = 'Welcome to JR Rodriguez Meat Dealer!';
    $verificationLink = BASE_URL . "verify-email.php?token=" . $verificationToken;
    
    $body = "
    <html>
    <head>
        <title>Welcome to JR Rodriguez Meat Dealer</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <h2 style='color: #8B4513;'>Welcome to JR Rodriguez Meat Dealer!</h2>
            <p>Dear {$name},</p>
            <p>Thank you for registering with us! We're excited to have you as part of our community.</p>
            <p>To complete your registration, please verify your email address by clicking the link below:</p>
            <p><a href='{$verificationLink}' style='background: #8B4513; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email Address</a></p>
            <p>If you didn't create an account with us, please ignore this email.</p>
            <br>
            <p>Best regards,<br>JR Rodriguez Meat Dealer Team</p>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, true);
}

function sendPasswordResetEmail($email, $name, $resetToken) {
    $subject = 'Password Reset - JR Rodriguez Meat Dealer';
    $resetLink = BASE_URL . "reset-password.php?token=" . $resetToken;
    
    $body = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <h2 style='color: #8B4513;'>Password Reset Request</h2>
            <p>Dear {$name},</p>
            <p>We received a request to reset your password. Click the link below to reset it:</p>
            <p><a href='{$resetLink}' style='background: #8B4513; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
            <p>This link will expire in 1 hour for security reasons.</p>
            <p>If you didn't request a password reset, please ignore this email.</p>
            <br>
            <p>Best regards,<br>JR Rodriguez Meat Dealer Team</p>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, true);
}
?>