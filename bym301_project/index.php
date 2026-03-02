<?php
/**
 * Ana Sayfa - Login'e Yönlendir
 */
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['user_type']) {
        case 'admin':
            header('Location: /bym301_project/admin/');
            break;
        case 'sofor':
            header('Location: /bym301_project/driver/');
            break;
        default:
            header('Location: /bym301_project/passenger/');
    }
} else {
    header('Location: /bym301_project/auth/login.php');
}
exit;
