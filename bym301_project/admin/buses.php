<?php
/**
 * Admin - Otobüs Yönetimi
 */
$page_title = 'Otobüs Yönetimi';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $company_id = intval($_POST['company_id'] ?? 0);
        $plate_number = sanitize($_POST['plate_number'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 40);
        $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
        $has_tv = isset($_POST['has_tv']) ? 1 : 0;
        
        if (!empty($plate_number) && $company_id > 0) {
            $stmt = $db->prepare("SELECT id FROM buses WHERE plate_number = ?");
            $stmt->execute([$plate_number]);
            
            if ($stmt->fetch()) {
                $message = 'Bu plaka numarası zaten kayıtlı.';
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO buses (company_id, plate_number, model, capacity, has_wifi, has_tv, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$company_id, $plate_number, $model, $capacity, $has_wifi, $has_tv]);
                $bus_id = $db->lastInsertId();
                
                // Koltukları oluştur
                for ($i = 1; $i <= $capacity; $i++) {
                    $seat_type = ($i % 4 == 1 || $i % 4 == 0) ? 'window' : 'aisle';
                    $stmt = $db->prepare("INSERT INTO seats (bus_id, seat_number, seat_type) VALUES (?, ?, ?)");
                    $stmt->execute([$bus_id, $i, $seat_type]);
                }
                
                $message = 'Otobüs ve koltukları eklendi.';
                $message_type = 'success';
            }
        }
    }
    
    if ($action === 'toggle_status') {
        $bus_id = intval($_POST['bus_id'] ?? 0);
        $stmt = $db->prepare("UPDATE buses SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$bus_id]);
        $message = 'Otobüs durumu güncellendi.';
        $message_type = 'success';
    }
    
    if ($action === 'delete') {
        $bus_id = intval($_POST['bus_id'] ?? 0);
        
        // Seferlerde kullanılıyor mu kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE bus_id = ?");
        $stmt->execute([$bus_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = 'Bu otobüs seferlerde kullanılıyor, silinemez!';
            $message_type = 'error';
        } else {
            $db->prepare("DELETE FROM seats WHERE bus_id = ?")->execute([$bus_id]);
            $db->prepare("DELETE FROM buses WHERE id = ?")->execute([$bus_id]);
            $message = 'Otobüs silindi.';
            $message_type = 'success';
        }
    }
}

// Firmalar
$companies = $db->query("SELECT * FROM bus_companies WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Otobüsler - JOIN Sorgusu #4
$buses = $db->query("
    SELECT b.*, bc.name as company_name,
           (SELECT COUNT(*) FROM schedules WHERE bus_id = b.id AND departure_time > NOW()) as upcoming_schedules
    FROM buses b
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    ORDER BY bc.name, b.plate_number
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Otobüs Yönetimi</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ Yeni Otobüs</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Plaka</th>
                <th>Firma</th>
                <th>Model</th>
                <th>Kapasite</th>
                <th>WiFi</th>
                <th>TV</th>
                <th>Aktif Sefer</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($buses as $bus): ?>
            <tr>
                <td><strong><?php echo sanitize($bus['plate_number']); ?></strong></td>
                <td><?php echo sanitize($bus['company_name']); ?></td>
                <td><?php echo sanitize($bus['model'] ?? '-'); ?></td>
                <td><?php echo $bus['capacity']; ?> koltuk</td>
                <td><?php echo $bus['has_wifi'] ? '✓' : '✗'; ?></td>
                <td><?php echo $bus['has_tv'] ? '✓' : '✗'; ?></td>
                <td><?php echo $bus['upcoming_schedules']; ?></td>
                <td>
                    <?php if ($bus['is_active']): ?>
                        <span style="color: #28a745;">● Aktif</span>
                    <?php else: ?>
                        <span style="color: #dc3545;">● Pasif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $bus['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                            <?php echo $bus['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                        </button>
                    </form>
                    <?php if ($bus['upcoming_schedules'] == 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu otobüsü silmek istediğinize emin misiniz?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Otobüs Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Yeni Otobüs Ekle</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Firma</label>
                <select name="company_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>"><?php echo sanitize($company['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plaka No</label>
                <input type="text" name="plate_number" class="form-control" placeholder="34 ABC 123" required>
            </div>
            <div class="form-group">
                <label>Model</label>
                <input type="text" name="model" class="form-control" placeholder="Mercedes Travego">
            </div>
            <div class="form-group">
                <label>Kapasite</label>
                <input type="number" name="capacity" class="form-control" value="40" min="10" max="60" required>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="has_wifi" value="1"> WiFi
                </label>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="has_tv" value="1"> TV
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ekle</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
