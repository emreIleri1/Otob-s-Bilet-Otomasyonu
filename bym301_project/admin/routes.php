<?php
/**
 * Admin - Güzergah Yönetimi
 */
$page_title = 'Güzergah Yönetimi';
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
        $from_city_id = intval($_POST['from_city_id'] ?? 0);
        $to_city_id = intval($_POST['to_city_id'] ?? 0);
        $distance_km = intval($_POST['distance_km'] ?? 0);
        $duration_minutes = intval($_POST['duration_minutes'] ?? 0);
        
        if ($from_city_id > 0 && $to_city_id > 0 && $from_city_id != $to_city_id) {
            $stmt = $db->prepare("SELECT id FROM routes WHERE from_city_id = ? AND to_city_id = ?");
            $stmt->execute([$from_city_id, $to_city_id]);
            
            if ($stmt->fetch()) {
                $message = 'Bu güzergah zaten mevcut.';
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO routes (from_city_id, to_city_id, distance_km, duration_minutes, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$from_city_id, $to_city_id, $distance_km, $duration_minutes]);
                $message = 'Güzergah eklendi.';
                $message_type = 'success';
            }
        } else {
            $message = 'Kalkış ve varış şehri farklı olmalıdır.';
            $message_type = 'error';
        }
    }
    
    if ($action === 'toggle_status') {
        $route_id = intval($_POST['route_id'] ?? 0);
        $stmt = $db->prepare("UPDATE routes SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$route_id]);
        $message = 'Güzergah durumu güncellendi.';
        $message_type = 'success';
    }
    
    if ($action === 'delete') {
        $route_id = intval($_POST['route_id'] ?? 0);
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE route_id = ?");
        $stmt->execute([$route_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = 'Bu güzergahta seferler var, silinemez!';
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM routes WHERE id = ?");
            $stmt->execute([$route_id]);
            $message = 'Güzergah silindi.';
            $message_type = 'success';
        }
    }
}

// Şehirler
$cities = $db->query("SELECT * FROM cities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Güzergahlar - JOIN Sorgusu #5
$routes = $db->query("
    SELECT r.*, 
           c1.name as from_city_name, 
           c2.name as to_city_name,
           (SELECT COUNT(*) FROM schedules WHERE route_id = r.id) as schedule_count
    FROM routes r
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    ORDER BY c1.name, c2.name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Güzergah Yönetimi</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ Yeni Güzergah</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kalkış</th>
                <th>Varış</th>
                <th>Mesafe</th>
                <th>Süre</th>
                <th>Sefer Sayısı</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($routes as $route): ?>
            <tr>
                <td><?php echo $route['id']; ?></td>
                <td><?php echo sanitize($route['from_city_name']); ?></td>
                <td><?php echo sanitize($route['to_city_name']); ?></td>
                <td><?php echo $route['distance_km']; ?> km</td>
                <td><?php echo floor($route['duration_minutes'] / 60); ?> sa <?php echo $route['duration_minutes'] % 60; ?> dk</td>
                <td><?php echo $route['schedule_count']; ?></td>
                <td>
                    <?php if ($route['is_active']): ?>
                        <span style="color: #28a745;">● Aktif</span>
                    <?php else: ?>
                        <span style="color: #dc3545;">● Pasif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $route['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                            <?php echo $route['is_active'] ? 'Pasif' : 'Aktif'; ?>
                        </button>
                    </form>
                    <?php if ($route['schedule_count'] == 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Güzergah Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Yeni Güzergah Ekle</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Kalkış Şehri</label>
                <select name="from_city_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $city['id']; ?>"><?php echo sanitize($city['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Varış Şehri</label>
                <select name="to_city_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $city['id']; ?>"><?php echo sanitize($city['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mesafe (km)</label>
                <input type="number" name="distance_km" class="form-control" min="0">
            </div>
            <div class="form-group">
                <label>Süre (dakika)</label>
                <input type="number" name="duration_minutes" class="form-control" min="0">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ekle</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
