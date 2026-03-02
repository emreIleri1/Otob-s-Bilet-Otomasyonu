<?php
/**
 * Admin - Sefer Detayları (Yolcu ve Koltuk Bilgileri)
 */
$page_title = 'Sefer Detayları';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($schedule_id <= 0) {
    header('Location: schedules.php');
    exit;
}

// Sefer bilgisi
$stmt = $db->prepare("
    SELECT s.*, c1.name as from_city, c2.name as to_city,
           b.plate_number, b.capacity, bc.name as company_name,
           u.full_name as driver_name, b.id as bus_id
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    LEFT JOIN users u ON s.driver_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    header('Location: schedules.php');
    exit;
}

// Rezervasyonlar ve yolcu bilgileri
$stmt = $db->prepare("
    SELECT bk.id, bk.booking_code, bk.status, bk.created_at,
           u.id as user_id, u.full_name, u.email, u.phone, u.tc_no,
           st.seat_number, st.seat_type,
           p.amount, p.payment_method, p.status as payment_status
    FROM bookings bk
    INNER JOIN users u ON bk.user_id = u.id
    INNER JOIN seats st ON bk.seat_id = st.id
    LEFT JOIN payments p ON bk.id = p.booking_id
    WHERE bk.schedule_id = ?
    ORDER BY st.seat_number
");
$stmt->execute([$schedule_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tüm koltukları al (dolu ve boş)
$stmt = $db->prepare("
    SELECT st.id, st.seat_number, st.seat_type,
           bk.id as booking_id, bk.status as booking_status,
           u.full_name
    FROM seats st
    LEFT JOIN bookings bk ON st.id = bk.seat_id AND bk.schedule_id = ? AND bk.status != 'cancelled'
    LEFT JOIN users u ON bk.user_id = u.id
    WHERE st.bus_id = ?
    ORDER BY st.seat_number
");
$stmt->execute([$schedule_id, $schedule['bus_id']]);
$all_seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$confirmed_count = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
$pending_count = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$cancelled_count = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
$total_revenue = array_sum(array_map(fn($b) => $b['payment_status'] === 'completed' ? $b['amount'] : 0, $bookings));
?>

<div class="page-header">
    <h1 class="page-title">Sefer Detayları</h1>
    <a href="schedules.php" class="btn btn-secondary">← Seferlere Dön</a>
</div>

<!-- Sefer Bilgisi -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        <div>
            <h2 style="color: #333; margin-bottom: 10px; font-size: 24px;">
                <?php echo sanitize($schedule['from_city'] . ' → ' . $schedule['to_city']); ?>
            </h2>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px;">
                <div>
                    <div style="color: #666; font-size: 13px;">Kalkış</div>
                    <div style="color: #333; font-size: 18px; font-weight: 600;"><?php echo formatDateTime($schedule['departure_time']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px;">Varış</div>
                    <div style="color: #333; font-size: 18px; font-weight: 600;"><?php echo formatDateTime($schedule['arrival_time']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px;">Firma / Otobüs</div>
                    <div style="color: #333;"><?php echo sanitize($schedule['company_name']); ?> - <?php echo sanitize($schedule['plate_number']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px;">Şoför</div>
                    <div style="color: #333;"><?php echo sanitize($schedule['driver_name'] ?? 'Atanmadı'); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px;">Bilet Fiyatı</div>
                    <div style="color: #e94560; font-weight: 600;"><?php echo formatMoney($schedule['price']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px;">Durum</div>
                    <div><?php echo getStatusBadge($schedule['status']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- İstatistikler -->
        <div style="background: #f8f9fa; border: 1px solid #e1e4e8; border-radius: 10px; padding: 20px;">
            <h3 style="color: #333; margin-bottom: 15px; font-size: 16px;">📊 İstatistikler</h3>
            <div style="display: grid; gap: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Kapasite</span>
                    <span style="color: #333; font-weight: 600;"><?php echo $schedule['capacity']; ?> Koltuk</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Onaylı Bilet</span>
                    <span style="color: #28a745; font-weight: 600;"><?php echo $confirmed_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Bekleyen</span>
                    <span style="color: #ffc107; font-weight: 600;"><?php echo $pending_count; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Boş Koltuk</span>
                    <span style="color: #333; font-weight: 600;"><?php echo $schedule['capacity'] - $confirmed_count - $pending_count; ?></span>
                </div>
                <div style="border-top: 1px solid #e1e4e8; padding-top: 15px; display: flex; justify-content: space-between;">
                    <span style="color: #666;">Toplam Gelir</span>
                    <span style="color: #e94560; font-weight: bold; font-size: 18px;"><?php echo formatMoney($total_revenue); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Koltuk Haritası -->
<div class="card" style="margin-bottom: 20px;">
    <h3 class="card-title">🪑 Koltuk Haritası</h3>
    <div style="display: flex; gap: 30px; align-items: flex-start;">
        <div style="display: grid; grid-template-columns: repeat(4, 60px); gap: 8px; justify-content: center;">
            <div style="grid-column: span 4; text-align: center; padding: 10px; background: #e9ecef; border-radius: 8px; margin-bottom: 10px;">
                <span style="color: #666;">🚌 Ön</span>
            </div>
            <?php 
            $col = 0;
            foreach ($all_seats as $seat): 
                $col++;
                $is_booked = !empty($seat['booking_id']);
                $bg_color = $is_booked ? 'rgba(220,53,69,0.3)' : 'rgba(40,167,69,0.3)';
                $border_color = $is_booked ? '#dc3545' : '#28a745';
                $text_color = $is_booked ? '#dc3545' : '#28a745';
            ?>
                <div style="width: 60px; height: 50px; background: <?php echo $bg_color; ?>; border: 2px solid <?php echo $border_color; ?>; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: <?php echo $is_booked ? 'pointer' : 'default'; ?>; transition: all 0.2s ease;"
                     <?php if ($is_booked): ?>
                     onclick="showPassengerInfo(<?php echo $seat['seat_number']; ?>)"
                     onmouseover="this.style.transform='scale(1.05)';"
                     onmouseout="this.style.transform='scale(1)';"
                     title="<?php echo sanitize($seat['full_name']); ?>"
                     <?php endif; ?>>
                    <span style="color: <?php echo $text_color; ?>; font-weight: bold; font-size: 16px;"><?php echo $seat['seat_number']; ?></span>
                    <?php if ($is_booked): ?>
                    <span style="color: <?php echo $text_color; ?>; font-size: 9px; max-width: 55px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo sanitize(explode(' ', $seat['full_name'])[0]); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="font-size: 13px;">
            <div style="margin-bottom: 10px;"><span style="display: inline-block; width: 20px; height: 20px; background: #fff; border: 2px solid #28a745; border-radius: 4px; vertical-align: middle;"></span> Boş</div>
            <div style="margin-bottom: 10px;"><span style="display: inline-block; width: 20px; height: 20px; background: rgba(220,53,69,0.3); border: 2px solid #dc3545; border-radius: 4px; vertical-align: middle;"></span> Dolu (tıkla)</div>
            <p style="color: #666; margin-top: 15px; font-size: 12px;">Dolu koltuklara tıklayarak<br>yolcu bilgilerini görebilirsiniz.</p>
        </div>
    </div>
</div>

<!-- Yolcu Listesi -->
<div class="card">
    <h3 class="card-title">👥 Yolcu Listesi (<?php echo count($bookings); ?>)</h3>
    
    <?php if (empty($bookings)): ?>
        <p style="color: #666; text-align: center; padding: 30px;">Bu sefere henüz bilet satılmamış.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Koltuk</th>
                    <th>Yolcu Adı</th>
                    <th>TC Kimlik</th>
                    <th>Telefon</th>
                    <th>E-posta</th>
                    <th>Bilet Kodu</th>
                    <th>Ödeme</th>
                    <th>Durum</th>
                    <th>Tarih</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr id="passenger-<?php echo $booking['seat_number']; ?>" style="<?php echo $booking['status'] === 'cancelled' ? 'opacity: 0.5;' : ''; ?>">
                    <td>
                        <span style="display: inline-block; width: 40px; height: 40px; background: rgba(233,69,96,0.2); border-radius: 8px; text-align: center; line-height: 40px; font-weight: bold; color: #e94560;">
                            <?php echo $booking['seat_number']; ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color: #333;"><?php echo sanitize($booking['full_name']); ?></strong>
                    </td>
                    <td>
                        <code style="background: #e9ecef; color: #333; padding: 3px 8px; border-radius: 4px;">
                            <?php echo sanitize($booking['tc_no'] ?? '-'); ?>
                        </code>
                    </td>
                    <td><?php echo sanitize($booking['phone'] ?? '-'); ?></td>
                    <td><?php echo sanitize($booking['email']); ?></td>
                    <td>
                        <code style="background: rgba(233,69,96,0.2); color: #e94560; padding: 3px 8px; border-radius: 4px; font-weight: 600;">
                            <?php echo sanitize($booking['booking_code']); ?>
                        </code>
                    </td>
                    <td>
                        <?php if ($booking['payment_status'] === 'completed'): ?>
                            <span style="color: #28a745;">✓ <?php echo formatMoney($booking['amount']); ?></span>
                        <?php else: ?>
                            <span style="color: #ffc107;">Beklemede</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo getStatusBadge($booking['status']); ?></td>
                    <td style="font-size: 12px; color: #666;"><?php echo formatDateTime($booking['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function showPassengerInfo(seatNumber) {
    const row = document.getElementById('passenger-' + seatNumber);
    if (row) {
        row.style.background = 'rgba(233,69,96,0.2)';
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => {
            row.style.background = '';
        }, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
