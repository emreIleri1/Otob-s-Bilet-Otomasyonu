<?php
/**
 * Yolcu - Sefer Arama
 */
$page_title = 'Sefer Ara';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['yolcu']);

$database = new Database();
$db = $database->getConnection();

$from_city = isset($_GET['from']) ? intval($_GET['from']) : 0;
$to_city = isset($_GET['to']) ? intval($_GET['to']) : 0;
$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

$schedules = [];

if ($from_city > 0 && $to_city > 0) {
    try {
        // Doğrudan SQL sorgusu (SP'ye bağımlılığı kaldırıldı)
        $stmt = $db->prepare("
            SELECT s.*, c1.name as from_city, c2.name as to_city,
                   b.plate_number, b.capacity, b.has_wifi, b.has_tv, bc.name as company_name,
                   (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status != 'cancelled') as booked_seats
            FROM schedules s
            INNER JOIN routes r ON s.route_id = r.id
            INNER JOIN cities c1 ON r.from_city_id = c1.id
            INNER JOIN cities c2 ON r.to_city_id = c2.id
            INNER JOIN buses b ON s.bus_id = b.id
            INNER JOIN bus_companies bc ON b.company_id = bc.id
            WHERE r.from_city_id = ? 
              AND r.to_city_id = ?
              AND DATE(s.departure_time) = ?
              AND s.status = 'scheduled'
              AND s.departure_time > NOW()
            ORDER BY s.departure_time
        ");
        $stmt->execute([$from_city, $to_city, $date]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Search Error: " . $e->getMessage());
        $schedules = [];
    }
}

// Şehirler
$cities = $db->query("SELECT * FROM cities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Sefer Ara</h1>
</div>

<!-- Arama Formu -->
<div class="card">
    <form method="GET" class="search-form">
        <div class="form-group">
            <label>Nereden</label>
            <select name="from" class="form-control" required>
                <option value="">Seçiniz</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city['id']; ?>" <?php echo $from_city == $city['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($city['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Nereye</label>
            <select name="to" class="form-control" required>
                <option value="">Seçiniz</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city['id']; ?>" <?php echo $to_city == $city['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($city['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tarih</label>
            <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ara</button>
        </div>
    </form>
</div>

<!-- Sonuçlar -->
<?php if ($from_city > 0 && $to_city > 0): ?>
    <?php if (empty($schedules)): ?>
    <div class="card" style="text-align: center; padding: 50px;">
        <p style="font-size: 48px; margin-bottom: 15px;">🔍</p>
        <p style="color: #666;">Bu tarih için uygun sefer bulunamadı.</p>
        <p style="color: #888; font-size: 14px; margin-top: 10px;">Farklı bir tarih veya güzergah deneyebilirsiniz.</p>
    </div>
    <?php else: ?>
    <div class="card">
        <h3 class="card-title"><?php echo count($schedules); ?> Sefer Bulundu</h3>
        
        <?php foreach ($schedules as $schedule): 
            $available_seats = $schedule['capacity'] - $schedule['booked_seats'];
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 25px; background: #f8f9fa; border-radius: 15px; margin-bottom: 15px; border: 1px solid #e1e4e8;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo formatTime($schedule['departure_time']); ?></div>
                        <div style="color: #666; font-size: 13px;"><?php echo sanitize($schedule['from_city']); ?></div>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <div style="border-top: 2px dashed #ccc; position: relative;">
                            <span style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #e9ecef; padding: 0 10px; color: #666; font-size: 12px; border-radius: 10px;">
                                <?php 
                                $duration = (strtotime($schedule['arrival_time']) - strtotime($schedule['departure_time'])) / 60;
                                echo floor($duration / 60) . ' sa ' . ($duration % 60) . ' dk';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo formatTime($schedule['arrival_time']); ?></div>
                        <div style="color: #666; font-size: 13px;"><?php echo sanitize($schedule['to_city']); ?></div>
                    </div>
                </div>
                <div style="margin-top: 15px; display: flex; gap: 15px; font-size: 13px; color: #666;">
                    <span>🚌 <?php echo sanitize($schedule['company_name']); ?></span>
                    <span><?php echo sanitize($schedule['plate_number']); ?></span>
                    <?php if ($schedule['has_wifi']): ?><span>📶 WiFi</span><?php endif; ?>
                    <?php if ($schedule['has_tv']): ?><span>📺 TV</span><?php endif; ?>
                </div>
            </div>
            
            <div style="text-align: right; min-width: 150px;">
                <div style="font-size: 28px; font-weight: bold; color: #e94560;"><?php echo formatMoney($schedule['price']); ?></div>
                <div style="color: <?php echo $available_seats > 5 ? '#666' : '#dc3545'; ?>; font-size: 13px; margin-top: 5px;">
                    <?php echo $available_seats; ?> koltuk kaldı
                </div>
                <?php if ($available_seats > 0): ?>
                <a href="book.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-primary" style="margin-top: 10px;">Bilet Al</a>
                <?php else: ?>
                <button class="btn btn-secondary" disabled style="margin-top: 10px; cursor: not-allowed;">Dolu</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
