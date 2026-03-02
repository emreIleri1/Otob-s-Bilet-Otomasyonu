<?php
/**
 * Kayıt Sayfası
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: /bym301_project/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $tc_no = sanitize($_POST['tc_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Doğrulamalar
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Ad soyad, e-posta ve şifre gereklidir.';
    } elseif (!validateEmail($email)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    } elseif ($password !== $password_confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } elseif (!empty($tc_no) && !validateTcNo($tc_no)) {
        $error = 'Geçerli bir TC Kimlik numarası giriniz.';
    } elseif (!empty($phone) && !validatePhone($phone)) {
        $error = 'Geçerli bir telefon numarası giriniz (5XX XXX XXXX).';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // E-posta kontrolü
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Yolcu olarak kayıt (user_type_id = 4)
            $stmt = $db->prepare("
                INSERT INTO users (user_type_id, email, password, full_name, phone, tc_no, is_active, created_at) 
                VALUES (4, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            if ($stmt->execute([$email, $hashed_password, $full_name, $phone, $tc_no])) {
                $user_id = $db->lastInsertId();
                logAction($db, $user_id, 'REGISTER', 'Yeni kullanıcı kaydı');
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error = 'Kayıt sırasında bir hata oluştu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - OtoBilet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 480px;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #fff;
            font-size: 36px;
            font-weight: bold;
        }
        
        .logo h1 span {
            color: #e94560;
        }
        
        .logo p {
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #e0e0e0;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 4px rgba(233, 69, 96, 0.2);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-register {
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
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .login-link a {
            color: #e94560;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b7a;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo">
                <h1>Oto<span>Bilet</span></h1>
                <p>Yeni Hesap Oluştur</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Ad Soyad *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Adınız Soyadınız" required>
                </div>
                
                <div class="form-group">
                    <label>E-posta *</label>
                    <input type="email" name="email" class="form-control" placeholder="ornek@email.com" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" class="form-control" placeholder="5XX XXX XXXX">
                    </div>
                    
                    <div class="form-group">
                        <label>TC Kimlik No</label>
                        <input type="text" name="tc_no" class="form-control" placeholder="11 haneli" maxlength="11">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Şifre *</label>
                        <input type="password" name="password" class="form-control" placeholder="En az 6 karakter" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Şifre Tekrar *</label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="Şifrenizi tekrarlayın" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">Kayıt Ol</button>
            </form>
            
            <div class="login-link">
                Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a>
            </div>
        </div>
    </div>
</body>
</html>
