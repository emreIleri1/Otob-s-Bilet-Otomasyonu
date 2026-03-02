<?php
/**
 * Yolcu - Biletlerim
 */
$page_title = 'Biletlerim';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['yolcu']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = 'info';

// Başarılı satın alma
if (isset($_GET['success'])) {
    $message = "Biletiniz başarıyla satın alındı! Bilet Kodunuz: <strong>" . sanitize($_GET['code']) . "</strong>";
    $message_type = 'success';
}

// İptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    // Kullanıcının kendi bileti mi ve iptal edilebilir mi kontrol et
    $stmt = $db->prepare("
        SELECT bk.*, s.departure_time 
        FROM bookings bk 
        INNER JOIN schedules s ON bk.schedule_id = s.id 
        WHERE bk.id = ? AND bk.user_id = ? AND bk.status = 'confirmed'
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        // Kalkışa en az 2 saat kala iptal
        if (strtotime($booking['departure_time']) - time() > 7200) {
            $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            logAction($db, $user_id, 'BOOKING_SELF_CANCEL', "Kullanıcı biletini iptal etti: $booking_id");
            
            $message = 'Biletiniz iptal edildi. İade işlemi için lütfen müşteri hizmetleri ile iletişime geçin.';
            $message_type = 'success';
        } else {
            $message = 'Kalkışa 2 saatten az kala bilet iptal edilemez.';
            $message_type = 'error';
        }
    }
}

// Tüm biletler (Doğrudan SQL - SP bağımlılığı kaldırıldı)
$bookings = [];
try {
    $stmt = $db->prepare("
        SELECT bk.*, s.departure_time, s.arrival_time, s.price,
               c1.name as from_city, c2.name as to_city,
               st.seat_number, b.plate_number, bc.name as company_name,
               p.status as payment_status
        FROM bookings bk
        INNER JOIN schedules s ON bk.schedule_id = s.id
        INNER JOIN routes r ON s.route_id = r.id
        INNER JOIN cities c1 ON r.from_city_id = c1.id
        INNER JOIN cities c2 ON r.to_city_id = c2.id
        INNER JOIN seats st ON bk.seat_id = st.id
        INNER JOIN buses b ON s.bus_id = b.id
        INNER JOIN bus_companies bc ON b.company_id = bc.id
        LEFT JOIN payments p ON bk.id = p.booking_id
        WHERE bk.user_id = ?
        ORDER BY s.departure_time DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Booking Error: " . $e->getMessage());
}

// Kategorize et
$upcoming = [];
$past = [];
$cancelled = [];

foreach ($bookings as $booking) {
    if ($booking['status'] === 'cancelled') {
        $cancelled[] = $booking;
    } elseif (strtotime($booking['departure_time']) > time()) {
        $upcoming[] = $booking;
    } else {
        $past[] = $booking;
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Biletlerim</h1>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<?php if (empty($bookings)): ?>
<div class="card" style="text-align: center; padding: 50px;">
    <p style="font-size: 48px; margin-bottom: 15px;">🎫</p>
    <p style="color: #666;">Henüz bilet satın almadınız.</p>
    <a href="search.php" class="btn btn-primary" style="margin-top: 20px;">Bilet Ara</a>
</div>
<?php else: ?>

<!-- Aktif Biletler -->
<?php if (!empty($upcoming)): ?>
<div class="card">
    <h3 class="card-title">🎫 Aktif Biletler (<?php echo count($upcoming); ?>)</h3>
    
    <?php foreach ($upcoming as $booking): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 25px; background: #f0fff4; border-radius: 15px; margin-bottom: 15px; border: 1px solid #28a745;">
        <div>
            <div style="font-size: 20px; font-weight: bold; color: #333;">
                <?php echo sanitize($booking['from_city'] . ' → ' . $booking['to_city']); ?>
            </div>
            <div style="color: #666; margin-top: 8px;">
                📅 <?php echo formatDateTime($booking['departure_time']); ?>
            </div>
            <div style="color: #888; font-size: 13px; margin-top: 5px;">
                <?php echo sanitize($booking['company_name']); ?> • <?php echo sanitize($booking['plate_number']); ?> • Koltuk <?php echo $booking['seat_number']; ?>
            </div>
        </div>
        
        <div style="text-align: center;">
            <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Bilet Kodu</div>
            <div style="background: #fff; color: #333; padding: 10px 20px; border-radius: 8px; font-family: monospace; font-size: 18px; font-weight: bold; border: 1px dashed #28a745;">
                <?php echo sanitize($booking['booking_code']); ?>
            </div>
        </div>
        
        <div style="text-align: right;">
            <div style="font-size: 24px; font-weight: bold; color: #e94560;"><?php echo formatMoney($booking['price']); ?></div>
            <?php if (strtotime($booking['departure_time']) - time() > 7200): ?>
            <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('Bu bileti iptal etmek istediğinize emin misiniz?')">
                <input type="hidden" name="cancel_booking" value="1">
                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger">İptal Et</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Geçmiş Biletler -->
<?php if (!empty($past)): ?>
<div class="card">
    <h3 class="card-title">📋 Geçmiş Biletler (<?php echo count($past); ?>)</h3>
    
    <table>
        <thead>
            <tr>
                <th>Güzergah</th>
                <th>Tarih</th>
                <th>Firma</th>
                <th>Koltuk</th>
                <th>Bilet Kodu</th>
                <th>Tutar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($past as $booking): ?>
            <tr style="opacity: 0.7;">
                <td><?php echo sanitize($booking['from_city'] . ' → ' . $booking['to_city']); ?></td>
                <td><?php echo formatDateTime($booking['departure_time']); ?></td>
                <td><?php echo sanitize($booking['company_name']); ?></td>
                <td><?php echo $booking['seat_number']; ?></td>
                <td><code><?php echo sanitize($booking['booking_code']); ?></code></td>
                <td><?php echo formatMoney($booking['price']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- İptal Edilen Biletler -->
<?php if (!empty($cancelled)): ?>
<div class="card">
    <h3 class="card-title">❌ İptal Edilen Biletler (<?php echo count($cancelled); ?>)</h3>
    
    <table>
        <thead>
            <tr>
                <th>Güzergah</th>
                <th>Tarih</th>
                <th>Firma</th>
                <th>Bilet Kodu</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cancelled as $booking): ?>
            <tr style="opacity: 0.5;">
                <td><?php echo sanitize($booking['from_city'] . ' → ' . $booking['to_city']); ?></td>
                <td><?php echo formatDateTime($booking['departure_time']); ?></td>
                <td><?php echo sanitize($booking['company_name']); ?></td>
                <td><code><?php echo sanitize($booking['booking_code']); ?></code></td>
                <td><?php echo getStatusBadge('cancelled'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
