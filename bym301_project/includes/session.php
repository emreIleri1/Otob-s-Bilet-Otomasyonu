<?php
/**
 * Oturum Yönetimi
 */

if (session_status() === PHP_SESSION_NONE) {
    // Güvenli session ayarları
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'user_type_id' => $_SESSION['user_type_id'],
        'user_type' => $_SESSION['user_type']
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /bym301_project/auth/login.php');
        exit;
    }
}

function requireRole($allowed_roles) {
    requireLogin();
    if (!in_array($_SESSION['user_type'], $allowed_roles)) {
        header('Location: /bym301_project/auth/login.php?error=unauthorized');
        exit;
    }
}

function setUserSession($user) {
    // Session fixation koruması
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_type_id'] = $user['user_type_id'];
    $_SESSION['user_type'] = $user['user_type_name'];
    $_SESSION['last_activity'] = time();
    
    // CSRF token oluştur
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function destroySession() {
    session_unset();
    session_destroy();
    
    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// CSRF token doğrulama
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// CSRF token HTML input oluştur
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
}

// Session timeout kontrolü (30 dakika)
function checkSessionTimeout($timeout = 1800) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        destroySession();
        header('Location: /bym301_project/auth/login.php?error=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

