<?php
/**
 * Şoför Dashboard
 */
$page_title = 'Şoför Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['sofor']);

$database = new Database();
$db = $database->getConnection();
$driver_id = $_SESSION['user_id'];

// Bugünkü seferler
$stmt = $db->prepare("
    SELECT s.*, c1.name as from_city, c2.name as to_city,
           b.plate_number, b.capacity, bc.name as company_name,
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status = 'confirmed') as passenger_count
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE s.driver_id = ? AND DATE(s.departure_time) = CURDATE() AND s.status = 'scheduled'
    ORDER BY s.departure_time
");
$stmt->execute([$driver_id]);
$today_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gelecek seferler
$stmt = $db->prepare("
    SELECT s.*, c1.name as from_city, c2.name as to_city,
           b.plate_number, bc.name as company_name,
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status = 'confirmed') as passenger_count
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE s.driver_id = ? AND s.departure_time > NOW() AND s.status = 'scheduled'
    ORDER BY s.departure_time
    LIMIT 10
");
$stmt->execute([$driver_id]);
$upcoming_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_schedules,
        SUM(CASE WHEN DATE(departure_time) = CURDATE() THEN 1 ELSE 0 END) as today_count,
        SUM(CASE WHEN departure_time > NOW() THEN 1 ELSE 0 END) as upcoming_count
    FROM schedules
    WHERE driver_id = ? AND status = 'scheduled'
");
$stmt->execute([$driver_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Hoş Geldiniz, <?php echo sanitize($_SESSION['full_name']); ?>!</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['today_count'] ?? 0; ?></div>
        <div class="stat-label">Bugünkü Sefer</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['upcoming_count'] ?? 0; ?></div>
        <div class="stat-label">Gelecek Sefer</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_schedules'] ?? 0; ?></div>
        <div class="stat-label">Toplam Atanmış</div>
    </div>
</div>

<?php if (!empty($today_schedules)): ?>
<div class="card">
    <h3 class="card-title">🚌 Bugünkü Seferleriniz</h3>
    <?php foreach ($today_schedules as $schedule): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: rgba(233,69,96,0.1); border-radius: 10px; margin-bottom: 15px; border: 1px solid rgba(233,69,96,0.3);">
        <div>
            <div style="font-size: 20px; font-weight: bold; color: #fff;">
                <?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?>
            </div>
            <div style="color: rgba(255,255,255,0.6); margin-top: 5px;">
                <span style="color: #e94560;"><?php echo formatTime($schedule['departure_time']); ?></span> - 
                <?php echo formatTime($schedule['arrival_time']); ?> • 
                <?php echo sanitize($schedule['plate_number']); ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 24px; font-weight: bold; color: #e94560;">
                <?php echo $schedule['passenger_count']; ?>/<?php echo $schedule['capacity']; ?>
            </div>
            <div style="color: rgba(255,255,255,0.6); font-size: 13px;">Yolcu</div>
        </div>
        <a href="passengers.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-primary">Yolcu Listesi</a>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align: center; padding: 40px;">
    <p style="font-size: 48px; margin-bottom: 15px;">☕</p>
    <p style="color: rgba(255,255,255,0.6);">Bugün için planlanmış seferiniz bulunmuyor.</p>
</div>
<?php endif; ?>

<?php if (!empty($upcoming_schedules)): ?>
<div class="card">
    <h3 class="card-title">📅 Gelecek Seferleriniz</h3>
    <table>
        <thead>
            <tr>
                <th>Güzergah</th>
                <th>Tarih</th>
                <th>Saat</th>
                <th>Otobüs</th>
                <th>Yolcu</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming_schedules as $schedule): ?>
            <tr>
                <td><?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?></td>
                <td><?php echo formatDate($schedule['departure_time']); ?></td>
                <td><?php echo formatTime($schedule['departure_time']); ?></td>
                <td><?php echo sanitize($schedule['plate_number']); ?></td>
                <td><?php echo $schedule['passenger_count']; ?></td>
                <td>
                    <a href="passengers.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-secondary">Yolcular</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
