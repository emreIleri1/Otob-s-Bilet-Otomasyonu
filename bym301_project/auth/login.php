<?php
/**
 * Giriş Sayfası
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: /bym301_project/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'E-posta ve şifre gereklidir.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // JOIN SORGUSU #1: Kullanıcı girişi
        $stmt = $db->prepare("
            SELECT u.*, ut.name as user_type_name 
            FROM users u 
            INNER JOIN user_types ut ON u.user_type_id = ut.id 
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Şifre doğrulama - hem hash hem düz metin desteği
            $password_valid = password_verify($password, $user['password']) || $user['password'] === $password;
            
            if ($password_valid) {
                // Eğer şifre düz metin ise, hash'le ve güncelle
                if ($user['password'] === $password) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed, $user['id']]);
                }
                
                setUserSession($user);
                logAction($db, $user['id'], 'LOGIN', 'Kullanıcı giriş yaptı');
                header('Location: /bym301_project/');
                exit;
            } else {
                $error = 'Geçersiz şifre.';
            }
        } else {
            $error = 'Kullanıcı bulunamadı veya hesap aktif değil.';
        }
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $error = 'Bu sayfaya erişim yetkiniz yok.';
            break;
        case 'timeout':
            $error = 'Oturumunuz zaman aşımına uğradı. Lütfen tekrar giriş yapın.';
            break;
    }
}

if (isset($_GET['registered'])) {
    $success = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - OtoBilet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            border: 1px solid #e1e4e8;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 36px;
            font-weight: bold;
        }
        
        .logo h1 span {
            color: #e94560;
        }
        
        .logo p {
            color: #666;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: #444;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background: #f8f9fa;
            color: #333;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e94560;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(233, 69, 96, 0.1);
        }
        
        .form-control::placeholder {
            color: #adb5bd;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #e94560, #c73e54);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.3);
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }
        
        .register-link a {
            color: #e94560;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
        }
        
        .alert-success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #2f855a;
        }
        
        .demo-accounts {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .demo-accounts h4 {
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .demo-list {
            font-size: 12px;
            color: #666;
        }
        
        .demo-list div {
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            border: 1px solid #eee;
        }
        
        .demo-list span {
            color: #e94560;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1>Oto<span>Bilet</span></h1>
                <p>Otobüs Bilet Satış Sistemi</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>E-posta</label>
                    <input type="email" name="email" class="form-control" placeholder="ornek@email.com" required>
                </div>
                
                <div class="form-group">
                    <label>Şifre</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="btn-login">Giriş Yap</button>
            </form>
            
            <div class="register-link">
                Hesabınız yok mu? <a href="register.php">Kayıt Olun</a>
            </div>
            
            <div class="demo-accounts">
                <h4>Demo Hesapları</h4>
                <div class="demo-list">
                    <div><span>Admin:</span> admin@test.com / 123456</div>
                    <div><span>Şoför:</span> sofor1@test.com / 123456</div>
                    <div><span>Yolcu:</span> yolcu@test.com / 123456</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
