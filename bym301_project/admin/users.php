<?php
/**
 * Admin - Kullanıcı Yönetimi
 */
$page_title = 'Kullanıcı Yönetimi';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = 'info';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($action === 'toggle_status' && $user_id > 0) {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND id != ?");
        $stmt->execute([$user_id, $_SESSION['user_id']]);
        $message = 'Kullanıcı durumu güncellendi.';
        $message_type = 'success';
        logAction($db, $_SESSION['user_id'], 'USER_STATUS_CHANGE', "Kullanıcı ID: $user_id durumu değiştirildi");
    }
    
    if ($action === 'delete' && $user_id > 0) {
        // Önce ilişkili kayıtları kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $booking_count = $stmt->fetchColumn();
        
        if ($booking_count > 0) {
            $message = 'Bu kullanıcının rezervasyonları var, silinemez!';
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$user_id, $_SESSION['user_id']]);
            $message = 'Kullanıcı silindi.';
            $message_type = 'success';
            logAction($db, $_SESSION['user_id'], 'USER_DELETE', "Kullanıcı ID: $user_id silindi");
        }
    }
    
    if ($action === 'add') {
        $email = sanitize($_POST['email'] ?? '');
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $user_type_id = intval($_POST['user_type_id'] ?? 4);
        $password = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
        
        if (!empty($email) && !empty($full_name)) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $message = 'Bu e-posta adresi zaten kullanılıyor.';
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO users (user_type_id, email, password, full_name, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$user_type_id, $email, $password, $full_name, $phone]);
                $message = 'Kullanıcı eklendi.';
                $message_type = 'success';
                logAction($db, $_SESSION['user_id'], 'USER_ADD', "Yeni kullanıcı eklendi: $email");
            }
        }
    }
}

// Kullanıcı tipleri
$user_types = $db->query("SELECT * FROM user_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı listesi - JOIN Sorgusu #3
$filter_type = isset($_GET['type']) ? intval($_GET['type']) : 0;
$sql = "SELECT u.*, ut.name as user_type_name,
        (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as booking_count
        FROM users u
        INNER JOIN user_types ut ON u.user_type_id = ut.id";
$params = [];
if ($filter_type > 0) {
    $sql .= " WHERE u.user_type_id = ?";
    $params[] = $filter_type;
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Kullanıcı Yönetimi</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ Yeni Kullanıcı</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <a href="users.php" class="btn <?php echo $filter_type === 0 ? 'btn-primary' : 'btn-secondary'; ?>">Tümü</a>
        <?php foreach ($user_types as $type): ?>
            <a href="users.php?type=<?php echo $type['id']; ?>" class="btn <?php echo $filter_type === $type['id'] ? 'btn-primary' : 'btn-secondary'; ?>">
                <?php echo ucfirst($type['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ad Soyad</th>
                <th>E-posta</th>
                <th>Telefon</th>
                <th>Tip</th>
                <th>Rezervasyon</th>
                <th>Durum</th>
                <th>Kayıt Tarihi</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo sanitize($user['full_name']); ?></td>
                <td><?php echo sanitize($user['email']); ?></td>
                <td><?php echo sanitize($user['phone'] ?? '-'); ?></td>
                <td><span class="user-badge"><?php echo ucfirst($user['user_type_name']); ?></span></td>
                <td><?php echo $user['booking_count']; ?></td>
                <td>
                    <?php if ($user['is_active']): ?>
                        <span style="color: #28a745;">● Aktif</span>
                    <?php else: ?>
                        <span style="color: #dc3545;">● Engelli</span>
                    <?php endif; ?>
                </td>
                <td><?php echo formatDateTime($user['created_at']); ?></td>
                <td>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                            <?php echo $user['is_active'] ? 'Engelle' : 'Aktif Et'; ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Kullanıcı Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Yeni Kullanıcı Ekle</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Ad Soyad</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>E-posta</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Telefon</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label>Kullanıcı Tipi</label>
                <select name="user_type_id" class="form-control">
                    <?php foreach ($user_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo ucfirst($type['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" class="form-control" value="123456">
                <small style="color: #666;">Varsayılan: 123456</small>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ekle</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
