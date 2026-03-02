<?php
/**
 * Şoför - Yolcu Listesi
 */
$page_title = 'Yolcu Listesi';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['sofor']);

$database = new Database();
$db = $database->getConnection();
$driver_id = $_SESSION['user_id'];
$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;

$schedule = null;
$passengers = [];

try {
    // Sefer bilgisi (şoföre ait mi kontrol et)
    $stmt = $db->prepare("
        SELECT s.*, c1.name as from_city, c2.name as to_city,
               b.plate_number, b.capacity, bc.name as company_name
        FROM schedules s
        INNER JOIN routes r ON s.route_id = r.id
        INNER JOIN cities c1 ON r.from_city_id = c1.id
        INNER JOIN cities c2 ON r.to_city_id = c2.id
        INNER JOIN buses b ON s.bus_id = b.id
        INNER JOIN bus_companies bc ON b.company_id = bc.id
        WHERE s.id = ? AND s.driver_id = ?
    ");
    $stmt->execute([$schedule_id, $driver_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($schedule) {
        // Yolcu listesi
        $stmt = $db->prepare("
            SELECT bk.booking_code, u.full_name, u.phone, u.email,
                   st.seat_number, p.status as payment_status
            FROM bookings bk
            INNER JOIN users u ON bk.user_id = u.id
            INNER JOIN seats st ON bk.seat_id = st.id
            LEFT JOIN payments p ON bk.id = p.booking_id
            WHERE bk.schedule_id = ? AND bk.status = 'confirmed'
            ORDER BY st.seat_number
        ");
        $stmt->execute([$schedule_id]);
        $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Passengers page error: " . $e->getMessage());
}

if (!$schedule) {
    header('Location: schedules.php');
    exit;
}
?>

<div class="page-header">
    <h1 class="page-title">Yolcu Listesi</h1>
    <a href="schedules.php" class="btn btn-secondary">← Geri</a>
</div>

<!-- Sefer Bilgisi -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="color: #333; margin-bottom: 5px;">
                <?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?>
            </h2>
            <p style="color: #666;">
                <?php echo formatDateTime($schedule['departure_time']); ?> • 
                <?php echo sanitize($schedule['plate_number']); ?> • 
                <?php echo sanitize($schedule['company_name']); ?>
            </p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 36px; font-weight: bold; color: #e94560;">
                <?php echo count($passengers); ?>
            </div>
            <div style="color: #666;">Yolcu</div>
        </div>
    </div>
</div>

<?php if (empty($passengers)): ?>
<div class="card" style="text-align: center; padding: 40px;">
    <p style="font-size: 48px; margin-bottom: 15px;">🪑</p>
    <p style="color: #666;">Bu sefere henüz rezervasyon yapılmamış.</p>
</div>
<?php else: ?>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Koltuk</th>
                <th>Yolcu Adı</th>
                <th>Telefon</th>
                <th>E-posta</th>
                <th>Bilet Kodu</th>
                <th>Ödeme</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($passengers as $passenger): ?>
            <tr>
                <td>
                    <span style="display: inline-block; width: 40px; height: 40px; background: rgba(233,69,96,0.1); border-radius: 8px; text-align: center; line-height: 40px; font-weight: bold; color: #e94560;">
                        <?php echo $passenger['seat_number']; ?>
                    </span>
                </td>
                <td><strong><?php echo sanitize($passenger['full_name']); ?></strong></td>
                <td><?php echo sanitize($passenger['phone'] ?? '-'); ?></td>
                <td><?php echo sanitize($passenger['email'] ?? '-'); ?></td>
                <td><code style="background: #e9ecef; padding: 3px 8px; border-radius: 4px; color: #333;"><?php echo sanitize($passenger['booking_code']); ?></code></td>
                <td>
                    <?php if ($passenger['payment_status'] === 'completed'): ?>
                        <span style="color: #28a745;">✓ Ödendi</span>
                    <?php else: ?>
                        <span style="color: #ffc107;">Beklemede</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
