<?php
/**
 * Admin Dashboard
 */
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

// İstatistikler - Sorgu #2
$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE user_type_id = 4) as passenger_count,
        (SELECT COUNT(*) FROM users WHERE user_type_id = 3) as driver_count,
        (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed') as booking_count,
        (SELECT COUNT(*) FROM schedules WHERE departure_time > NOW()) as upcoming_schedules,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed') as total_revenue,
        (SELECT COUNT(*) FROM buses WHERE is_active = 1) as active_buses
")->fetch(PDO::FETCH_ASSOC);

// Bugünkü seferler - JOIN Sorgusu #2
$today_schedules = $db->query("
    SELECT s.id, s.departure_time, s.arrival_time, s.price,
           c1.name as from_city, c2.name as to_city,
           b.plate_number, bc.name as company_name,
           u.full_name as driver_name,
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status = 'confirmed') as booked_seats
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    LEFT JOIN users u ON s.driver_id = u.id
    WHERE DATE(s.departure_time) = CURDATE()
    ORDER BY s.departure_time
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Son işlemler (Log) - Sorgu #3
$recent_logs = $db->query("
    SELECT l.*, u.full_name
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Admin Dashboard</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['passenger_count']); ?></div>
        <div class="stat-label">Kayıtlı Yolcu</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['driver_count']); ?></div>
        <div class="stat-label">Aktif Şoför</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['booking_count']); ?></div>
        <div class="stat-label">Toplam Rezervasyon</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['upcoming_schedules']); ?></div>
        <div class="stat-label">Aktif Sefer</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?> ₺</div>
        <div class="stat-label">Toplam Gelir</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($stats['active_buses']); ?></div>
        <div class="stat-label">Aktif Otobüs</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div class="card">
        <h3 class="card-title">Bugünkü Seferler</h3>
        <?php if (empty($today_schedules)): ?>
            <p style="color: rgba(255,255,255,0.6);">Bugün için planlanmış sefer bulunmuyor.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Güzergah</th>
                        <th>Kalkış</th>
                        <th>Firma</th>
                        <th>Şoför</th>
                        <th>Doluluk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($today_schedules as $schedule): ?>
                    <tr>
                        <td><?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?></td>
                        <td><?php echo formatTime($schedule['departure_time']); ?></td>
                        <td><?php echo sanitize($schedule['company_name']); ?></td>
                        <td><?php echo sanitize($schedule['driver_name'] ?? 'Atanmadı'); ?></td>
                        <td><?php echo $schedule['booked_seats']; ?> kişi</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h3 class="card-title">Son İşlemler</h3>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php foreach ($recent_logs as $log): ?>
            <div style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 13px;">
                <div style="color: #e94560; font-weight: 500;"><?php echo sanitize($log['action']); ?></div>
                <div style="color: rgba(255,255,255,0.6); margin-top: 3px;">
                    <?php echo sanitize($log['full_name'] ?? 'Sistem'); ?> - <?php echo formatDateTime($log['created_at']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
