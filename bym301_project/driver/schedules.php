<?php
/**
 * Şoför - Seferlerim
 */
$page_title = 'Seferlerim';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['sofor']);

$database = new Database();
$db = $database->getConnection();
$driver_id = $_SESSION['user_id'];
$driver_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    
    if ($action === 'leave_trip' && $schedule_id > 0) {
        $stmt = $db->prepare("UPDATE schedules SET driver_id = NULL WHERE id = ? AND driver_id = ? AND departure_time > NOW()");
        $stmt->execute([$schedule_id, $driver_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = 'Seferden ayrıldınız.';
            $message_type = 'success';
            logAction($db, $driver_id, 'TRIP_LEAVE', "Şoför seferden ayrıldı: $schedule_id");
        } else {
            $message = 'İşlem başarısız veya sefer süresi geçmiş.';
            $message_type = 'error';
        }
    }
    
    if ($action === 'claim_trip' && $schedule_id > 0) {
        // Müsait mi kontrol et (Concurrency için)
        $stmt = $db->prepare("UPDATE schedules SET driver_id = ? WHERE id = ? AND driver_id IS NULL AND departure_time > NOW()");
        $stmt->execute([$driver_id, $schedule_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = 'Seferi başarıyla aldınız.';
            $message_type = 'success';
            logAction($db, $driver_id, 'TRIP_CLAIM', "Şoför seferi aldı: $schedule_id");
        } else {
            $message = 'Bu sefer artık müsait değil.';
            $message_type = 'error';
        }
    }
}

// Müsait seferler (Şoförü olmayan)
$available_schedules = $db->query("
    SELECT s.*, c1.name as from_city, c2.name as to_city,
           b.plate_number, b.capacity, bc.name as company_name
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE s.driver_id IS NULL AND s.status = 'scheduled' AND s.departure_time > NOW()
    ORDER BY s.departure_time
")->fetchAll(PDO::FETCH_ASSOC);
// Şoför seferleri (View kullanımı)
$stmt = $db->prepare("
    SELECT schedule_id as id, from_city, to_city, departure_time, arrival_time,
           plate_number, company_name, status, passenger_count,
           (SELECT capacity FROM buses WHERE plate_number = v.plate_number) as capacity
    FROM v_driver_schedules v
    WHERE driver_id = ?
    ORDER BY departure_time DESC
    LIMIT 50
");
$stmt->execute([$driver_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Seferlerim</h1>
    <button class="btn btn-primary" onclick="document.getElementById('claimModal').classList.add('active')">+ Yeni Sefer Bul</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Güzergah</th>
                <th>Tarih</th>
                <th>Kalkış</th>
                <th>Varış</th>
                <th>Otobüs</th>
                <th>Firma</th>
                <th>Yolcu</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $schedule): 
                $is_past = strtotime($schedule['departure_time']) < time();
            ?>
            <tr style="<?php echo $is_past ? 'opacity: 0.6;' : ''; ?>">
                <td><?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?></td>
                <td><?php echo formatDate($schedule['departure_time']); ?></td>
                <td><?php echo formatTime($schedule['departure_time']); ?></td>
                <td><?php echo formatTime($schedule['arrival_time']); ?></td>
                <td><?php echo sanitize($schedule['plate_number']); ?></td>
                <td><?php echo sanitize($schedule['company_name']); ?></td>
                <td>
                    <div style="background: #e9ecef; border-radius: 10px; overflow: hidden; height: 8px; width: 60px; display: inline-block; vertical-align: middle;">
                        <div style="background: #e94560; height: 100%; width: <?php echo ($schedule['passenger_count'] / $schedule['capacity']) * 100; ?>%;"></div>
                    </div>
                    <?php echo $schedule['passenger_count']; ?>/<?php echo $schedule['capacity']; ?>
                </td>
                <td><?php echo getStatusBadge($schedule['status']); ?></td>
                <td>
                    <a href="passengers.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-secondary">Yolcular</a>
                    <?php if (!$is_past && $schedule['status'] == 'scheduled'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu seferden ayrılmak istediğinize emin misiniz?')">
                        <input type="hidden" name="action" value="leave_trip">
                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Ayrıl</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Yeni Sefer Bul Modal -->
<div id="claimModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 style="color: #333;">Müsait Seferler</h3>
            <button class="modal-close" onclick="document.getElementById('claimModal').classList.remove('active')">&times;</button>
        </div>
        
        <?php if (empty($available_schedules)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">Şu anda boşta sefer bulunamadı.</p>
        <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Güzergah</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Otobüs</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_schedules as $schedule): ?>
                        <tr>
                            <td><?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?></td>
                            <td><?php echo formatDate($schedule['departure_time']); ?></td>
                            <td><?php echo formatTime($schedule['departure_time']); ?></td>
                            <td><?php echo sanitize($schedule['plate_number']); ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="action" value="claim_trip">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Seferi Al</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
