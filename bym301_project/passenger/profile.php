<?php
/**
 * Yolcu - Profil Sayfası
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['yolcu']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = 'info';

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /bym301_project/auth/logout.php');
    exit;
}

// Form gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $tc_no = sanitize($_POST['tc_no'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        
        $errors = [];
        
        // Doğrulamalar
        if (empty($full_name)) {
            $errors[] = 'Ad soyad zorunludur.';
        }
        
        if (!empty($tc_no) && !validateTcNo($tc_no)) {
            $errors[] = 'Geçerli bir TC Kimlik numarası giriniz.';
        }
        
        if (!empty($phone) && !validatePhone($phone)) {
            $errors[] = 'Geçerli bir telefon numarası giriniz (5XX XXX XXXX).';
        }
        
        if (empty($errors)) {
            // Telefon numarasını formatla
            $phone = validatePhone($phone) ?: $phone;
            
            $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, tc_no = ?, gender = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $tc_no, $gender, $user_id]);
            
            // Session'ı güncelle
            $_SESSION['full_name'] = $full_name;
            
            logAction($db, $user_id, 'PROFILE_UPDATE', 'Kullanıcı profil bilgilerini güncelledi');
            
            $message = 'Profil bilgileriniz başarıyla güncellendi.';
            $message_type = 'success';
            
            // Kullanıcı bilgilerini yeniden çek
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Mevcut şifre kontrolü
        if (!password_verify($current_password, $user['password']) && $current_password !== $user['password']) {
            $errors[] = 'Mevcut şifreniz hatalı.';
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Yeni şifreler eşleşmiyor.';
        }
        
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            logAction($db, $user_id, 'PASSWORD_CHANGE', 'Kullanıcı şifresini değiştirdi');
            
            $message = 'Şifreniz başarıyla değiştirildi.';
            $message_type = 'success';
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
}

// Sayfa header'ı
$page_title = 'Profilim';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Profilim</h1>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Profil Bilgileri -->
    <div class="card">
        <h3 class="card-title">👤 Kişisel Bilgiler</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label>E-posta</label>
                <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled 
                       style="background: #e9ecef; cursor: not-allowed;">
                <small style="color: #666;">E-posta adresi değiştirilemez.</small>
            </div>
            
            <div class="form-group">
                <label>Ad Soyad *</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo sanitize($user['full_name']); ?>" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>TC Kimlik No</label>
                    <input type="text" name="tc_no" class="form-control" value="<?php echo sanitize($user['tc_no'] ?? ''); ?>" 
                           maxlength="11" placeholder="11 haneli">
                </div>
                
                <div class="form-group">
                    <label>Cinsiyet</label>
                    <select name="gender" class="form-control">
                        <option value="">Seçiniz</option>
                        <option value="erkek" <?php echo ($user['gender'] ?? '') === 'erkek' ? 'selected' : ''; ?>>Erkek</option>
                        <option value="kadin" <?php echo ($user['gender'] ?? '') === 'kadin' ? 'selected' : ''; ?>>Kadın</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Telefon</label>
                <input type="text" name="phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>" 
                       placeholder="5XX XXX XXXX">
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e1e4e8;">
                <small style="color: #666;">
                    Kayıt Tarihi: <?php echo formatDateTime($user['created_at']); ?>
                </small>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
    
    <!-- Şifre Değiştirme -->
    <div class="card">
        <h3 class="card-title">🔒 Şifre Değiştir</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label>Mevcut Şifre</label>
                <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
            </div>
            
            <div class="form-group">
                <label>Yeni Şifre</label>
                <input type="password" name="new_password" class="form-control" required placeholder="En az 6 karakter" minlength="6">
            </div>
            
            <div class="form-group">
                <label>Yeni Şifre (Tekrar)</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Şifrenizi tekrar girin">
            </div>
            
            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">Şifreyi Değiştir</button>
        </form>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; border: 1px solid #e1e4e8;">
            <h4 style="color: #333; font-size: 14px; margin-bottom: 15px;">📊 Hesap Özeti</h4>
        <?php
            // Hesap istatistikleri
            $stmt = $db->prepare("SELECT COUNT(*) as total_bookings, 
                                         SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                                         SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                                  FROM bookings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // SQL Function kullanarak toplam harcamayı getir
            $total_spent = getUserTotalSpending($db, $user_id);
            ?>
            <div style="display: grid; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Toplam Bilet</span>
                    <span style="color: #333; font-weight: 600;"><?php echo $stats['total_bookings'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Aktif Bilet</span>
                    <span style="color: #28a745; font-weight: 600;"><?php echo $stats['confirmed'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">İptal Edilen</span>
                    <span style="color: #dc3545; font-weight: 600;"><?php echo $stats['cancelled'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid #e1e4e8;">
                    <span style="color: #666;">Toplam Harcama</span>
                    <span style="color: #e94560; font-weight: 600;"><?php echo formatMoney($total_spent); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
