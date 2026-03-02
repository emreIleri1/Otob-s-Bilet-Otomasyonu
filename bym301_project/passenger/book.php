<?php
/**
 * Yolcu - Bilet Al
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['yolcu']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$message = '';
$message_type = 'info';

// Sefer bilgisi
$stmt = $db->prepare("
    SELECT s.*, c1.name as from_city, c2.name as to_city,
           b.plate_number, b.capacity, bc.name as company_name, b.id as bus_id
    FROM schedules s
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE s.id = ? AND s.status = 'scheduled' AND s.departure_time > NOW()
");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    header('Location: search.php');
    exit;
}

// Rezervasyon işlemi - HEADER GÖNDERMEDEN ÖNCE YAPILMALI
// Rezervasyon işlemi - HEADER GÖNDERMEDEN ÖNCE YAPILMALI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seat_ids_str = $_POST['seat_ids'] ?? '';
    $seat_ids = array_filter(explode(',', $seat_ids_str));
    $payment_method = sanitize($_POST['payment_method'] ?? 'credit_card');
    
    if (!empty($seat_ids)) {
        // Koltuklar müsait mi?
        $placeholders = str_repeat('?,', count($seat_ids) - 1) . '?';
        $params = array_merge([$schedule_id], $seat_ids);
        
        $stmt = $db->prepare("SELECT id FROM bookings WHERE schedule_id = ? AND seat_id IN ($placeholders) AND status != 'cancelled'");
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            $message = 'Seçilen koltuklardan bazıları başka biri tarafından alınmış!';
            $message_type = 'error';
        } else {
            $booking_codes = [];
            
            foreach ($seat_ids as $seat_id) {
                // Rezervasyon oluştur
                $booking_code = generateBookingCode();
                $stmt = $db->prepare("INSERT INTO bookings (user_id, schedule_id, seat_id, status, booking_code, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())");
                $stmt->execute([$user_id, $schedule_id, intval($seat_id), $booking_code]);
                $booking_id = $db->lastInsertId();
                $booking_codes[] = $booking_code;
                
                // Ödeme kaydı (Trigger otomatik 'confirmed' yapacak)
                $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, status, created_at) VALUES (?, ?, ?, 'completed', NOW())");
                $stmt->execute([$booking_id, $schedule['price'], $payment_method]);
                
                logAction($db, $user_id, 'TICKET_PURCHASE', "Bilet satın alındı: $booking_code");
            }
            
            $codes_str = implode(', ', $booking_codes);
            header("Location: tickets.php?success=1&code=$codes_str");
            exit;
        }
    } else {
        $message = 'Lütfen en az bir koltuk seçin.';
        $message_type = 'error';
    }
}

// Koltukları getir
$stmt = $db->prepare("
    SELECT st.id, st.seat_number, st.seat_type,
           CASE WHEN bk.id IS NOT NULL THEN 'occupied' ELSE 'available' END as status
    FROM seats st
    LEFT JOIN bookings bk ON st.id = bk.seat_id AND bk.schedule_id = ? AND bk.status != 'cancelled'
    WHERE st.bus_id = ?
    ORDER BY st.seat_number
");
$stmt->execute([$schedule_id, $schedule['bus_id']]);
$seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Artık header dahil edilebilir
$page_title = 'Bilet Al';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Bilet Al</h1>
    <a href="search.php" class="btn btn-secondary">← Geri</a>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Koltuk Seçimi -->
    <div class="card">
        <h3 class="card-title">Koltuk Seçin</h3>
        
        <form method="POST" id="bookingForm">
            <input type="hidden" name="seat_ids" id="selected_seat_ids" value="">
            
            <div style="display: flex; gap: 40px;">
                <div>
                    <div style="text-align: center; margin-bottom: 20px; padding: 10px; background: #e9ecef; border-radius: 8px;">
                        <span style="color: #666;">🚌 Ön</span>
                    </div>
                    <div class="seat-grid">
                        <?php 
                        $col = 0;
                        foreach ($seats as $seat): 
                            $col++;
                        ?>
                            <div class="seat <?php echo $seat['status']; ?>" 
                                 data-seat-id="<?php echo $seat['id']; ?>"
                                 data-seat-number="<?php echo $seat['seat_number']; ?>"
                                 onclick="<?php echo $seat['status'] === 'available' ? 'selectSeat(this)' : ''; ?>">
                                <?php echo $seat['seat_number']; ?>
                            </div>
                            <?php if ($col % 4 == 2): ?>
                                <div class="seat corridor"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="font-size: 13px;">
                    <div style="margin-bottom: 15px;">
                        <div style="margin-bottom: 8px;"><span style="display: inline-block; width: 25px; height: 25px; background: #fff; border: 2px solid #28a745; border-radius: 6px; vertical-align: middle;"></span> Müsait</div>
                        <div style="margin-bottom: 8px;"><span style="display: inline-block; width: 25px; height: 25px; background: rgba(220,53,69,0.1); border: 2px solid #dc3545; border-radius: 6px; vertical-align: middle;"></span> Dolu</div>
                        <div><span style="display: inline-block; width: 25px; height: 25px; background: #007bff; border: 2px solid #007bff; border-radius: 6px; vertical-align: middle;"></span> Seçili</div>
                    </div>
                    
                    <div id="selectedSeatInfo" style="background: rgba(233,69,96,0.1); padding: 15px; border-radius: 10px; display: none;">
                        <div style="color: #e94560; font-weight: 600;">Seçilen Koltuklar</div>
                        <div id="selectedSeatNumber" style="font-size: 18px; font-weight: bold; color: #e94560; word-break: break-all;"></div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8;">
                <div class="form-group">
                    <label>Ödeme Yöntemi</label>
                    <select name="payment_method" class="form-control" style="max-width: 300px;">
                        <option value="credit_card">Kredi Kartı</option>
                        <option value="debit_card">Banka Kartı</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 15px 40px;">Bilet Satın Al</button>
            </div>
        </form>
        
        <script>
        let selectedSeats = [];
        const ticketPrice = <?php echo floatval($schedule['price']); ?>;

        function selectSeat(el) {
            const seatId = el.dataset.seatId;
            const seatNum = el.dataset.seatNumber;

            if (selectedSeats.some(s => s.id === seatId)) {
                // Deselect
                selectedSeats = selectedSeats.filter(s => s.id !== seatId);
                el.classList.remove('selected');
                el.classList.add('available');
            } else {
                // Select
                selectedSeats.push({id: seatId, num: seatNum});
                el.classList.remove('available');
                el.classList.add('selected');
            }
            
            updateUI();
        }

        function updateUI() {
            // Update hidden input
            const ids = selectedSeats.map(s => s.id).join(',');
            document.getElementById('selected_seat_ids').value = ids;

            // Update info box
            if (selectedSeats.length > 0) {
                document.getElementById('selectedSeatInfo').style.display = 'block';
                const nums = selectedSeats.map(s => s.num).sort((a,b) => a-b).join(', ');
                document.getElementById('selectedSeatNumber').textContent = nums;
            } else {
                document.getElementById('selectedSeatInfo').style.display = 'none';
            }

            // Update Total Price
            const total = selectedSeats.length * ticketPrice;
            document.getElementById('totalPrice').textContent = formatMoney(total);
        }

        function formatMoney(amount) {
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
        }
        
        document.getElementById('bookingForm').onsubmit = function(e) {
            if (!document.getElementById('selected_seat_ids').value) {
                e.preventDefault();
                alert('Lütfen en az bir koltuk seçin!');
            }
        };
        </script>
    </div>
    
    <!-- Sefer Özeti -->
    <div class="card">
        <h3 class="card-title">Sefer Bilgileri</h3>
        
        <div style="padding: 20px; background: #f8f9fa; border: 1px solid #e1e4e8; border-radius: 10px; margin-bottom: 20px;">
            <div style="font-size: 20px; font-weight: bold; color: #333; margin-bottom: 10px;">
                <?php echo sanitize($schedule['from_city']); ?> → <?php echo sanitize($schedule['to_city']); ?>
            </div>
            <div style="color: #666;">
                <?php echo formatDateTime($schedule['departure_time']); ?>
            </div>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #666;">Firma</span>
                <span style="color: #333;"><?php echo sanitize($schedule['company_name']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #666;">Otobüs</span>
                <span style="color: #333;"><?php echo sanitize($schedule['plate_number']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #666;">Kalkış</span>
                <span style="color: #333;"><?php echo formatTime($schedule['departure_time']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #666;">Varış</span>
                <span style="color: #333;"><?php echo formatTime($schedule['arrival_time']); ?></span>
            </div>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #666; font-size: 18px;">Toplam</span>
                <span id="totalPrice" style="font-size: 32px; font-weight: bold; color: #e94560;"><?php echo formatMoney($schedule['price']); ?></span>
            </div>
            <div style="text-align: right; margin-top: 5px; font-size: 13px; color: #888;">
                <span style="color: #333; font-weight: bold;"><?php echo formatMoney($schedule['price']); ?></span> x 1 Bilet
            </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
