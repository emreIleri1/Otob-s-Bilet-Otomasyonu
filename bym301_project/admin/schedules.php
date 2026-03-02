<?php
/**
 * Admin - Sefer Yönetimi
 */
$page_title = 'Sefer Yönetimi';
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
        $route_id = intval($_POST['route_id'] ?? 0);
        $bus_id = intval($_POST['bus_id'] ?? 0);
        $driver_id = intval($_POST['driver_id'] ?? 0) ?: null;
        $departure_time = $_POST['departure_time'] ?? '';
        $arrival_time = $_POST['arrival_time'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        
        if ($route_id > 0 && $bus_id > 0 && !empty($departure_time) && !empty($arrival_time) && $price > 0) {
            $stmt = $db->prepare("
                INSERT INTO schedules (route_id, bus_id, driver_id, departure_time, arrival_time, price, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$route_id, $bus_id, $driver_id, $departure_time, $arrival_time, $price]);
            $message = 'Sefer eklendi.';
            $message_type = 'success';
            logAction($db, $_SESSION['user_id'], 'SCHEDULE_ADD', "Yeni sefer eklendi: $departure_time");
        } else {
            $message = 'Tüm alanları doldurunuz.';
            $message_type = 'error';
        }
    }
    
    if ($action === 'cancel') {
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $stmt = $db->prepare("UPDATE schedules SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$schedule_id]);
        
        // İlgili rezervasyonları iptal et
        $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        
        $message = 'Sefer ve ilgili rezervasyonlar iptal edildi.';
        $message_type = 'success';
        logAction($db, $_SESSION['user_id'], 'SCHEDULE_CANCEL', "Sefer iptal edildi: $schedule_id");
    }
}

// Güzergahlar
$routes = $db->query("
    SELECT r.id, CONCAT(c1.name, ' → ', c2.name) as route_name
    FROM routes r
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    WHERE r.is_active = 1
    ORDER BY c1.name
")->fetchAll(PDO::FETCH_ASSOC);

// Otobüsler
$buses = $db->query("
    SELECT b.id, CONCAT(b.plate_number, ' (', bc.name, ')') as bus_name
    FROM buses b
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE b.is_active = 1
    ORDER BY bc.name, b.plate_number
")->fetchAll(PDO::FETCH_ASSOC);

// Şoförler
$drivers = $db->query("SELECT id, full_name FROM users WHERE user_type_id = 3 AND is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Seferler - JOIN Sorgusu #6 (Ana liste sorgusu)
$schedules = $db->query("
    SELECT s.*, 
           c1.name as from_city, c2.name as to_city,
           b.plate_number, bc.name as company_name,
           u.full_name as driver_name,
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status = 'confirmed') as booked_count,
           b.capacity
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    LEFT JOIN users u ON s.driver_id = u.id
    WHERE s.departure_time > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY s.departure_time
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Sefer Yönetimi</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ Yeni Sefer</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Güzergah</th>
                <th>Kalkış</th>
                <th>Varış</th>
                <th>Otobüs</th>
                <th>Şoför</th>
                <th>Fiyat</th>
                <th>Doluluk</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $schedule): ?>
            <tr>
                <td><?php echo $schedule['id']; ?></td>
                <td><?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?></td>
                <td><?php echo formatDateTime($schedule['departure_time']); ?></td>
                <td><?php echo formatTime($schedule['arrival_time']); ?></td>
                <td>
                    <div><?php echo sanitize($schedule['plate_number']); ?></div>
                    <small style="color: #666;"><?php echo sanitize($schedule['company_name']); ?></small>
                </td>
                <td><?php echo sanitize($schedule['driver_name'] ?? 'Atanmadı'); ?></td>
                <td><?php echo formatMoney($schedule['price']); ?></td>
                <td>
                    <div style="background: #e9ecef; border-radius: 10px; overflow: hidden; height: 8px; width: 80px;">
                        <div style="background: #e94560; height: 100%; width: <?php echo ($schedule['booked_count'] / $schedule['capacity']) * 100; ?>%;"></div>
                    </div>
                    <small><?php echo $schedule['booked_count']; ?>/<?php echo $schedule['capacity']; ?></small>
                </td>
                <td><?php echo getStatusBadge($schedule['status']); ?></td>
                <td>
                    <a href="schedule_details.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-secondary" title="Detayları Görüntüle">Detay</a>
                    <?php if ($schedule['status'] === 'scheduled'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu seferi iptal etmek istediğinize emin misiniz? Tüm rezervasyonlar da iptal edilecek.')">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">İptal</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Sefer Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Yeni Sefer Ekle</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Güzergah</label>
                <select name="route_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($routes as $route): ?>
                        <option value="<?php echo $route['id']; ?>"><?php echo sanitize($route['route_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Otobüs</label>
                <select name="bus_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?php echo $bus['id']; ?>"><?php echo sanitize($bus['bus_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Şoför</label>
                <select name="driver_id" class="form-control">
                    <option value="">Seçiniz (Opsiyonel)</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?php echo $driver['id']; ?>"><?php echo sanitize($driver['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Kalkış Zamanı</label>
                <input type="datetime-local" name="departure_time" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Varış Zamanı</label>
                <input type="datetime-local" name="arrival_time" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Fiyat (TL)</label>
                <input type="number" name="price" class="form-control" step="0.01" min="0" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ekle</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
