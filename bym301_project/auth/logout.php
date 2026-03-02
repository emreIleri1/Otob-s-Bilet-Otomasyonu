<?php
/**
 * Çıkış İşlemi
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    logAction($db, $_SESSION['user_id'], 'LOGOUT', 'Kullanıcı çıkış yaptı');
}

destroySession();
header('Location: /bym301_project/auth/login.php');
exit;
