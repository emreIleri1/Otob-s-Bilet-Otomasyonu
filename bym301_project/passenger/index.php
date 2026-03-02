<?php
/**
 * Yolcu Dashboard
 */
$page_title = 'Ana Sayfa';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['yolcu']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Kullanıcının yaklaşan seferleri
$stmt = $db->prepare("
    SELECT bk.*, s.departure_time, s.arrival_time, s.price,
           c1.name as from_city, c2.name as to_city,
           st.seat_number, b.plate_number, bc.name as company_name
    FROM bookings bk
    INNER JOIN schedules s ON bk.schedule_id = s.id
    INNER JOIN routes r ON s.route_id = r.id
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN seats st ON bk.seat_id = st.id
    INNER JOIN buses b ON s.bus_id = b.id
    INNER JOIN bus_companies bc ON b.company_id = bc.id
    WHERE bk.user_id = ? AND s.departure_time > NOW() AND bk.status = 'confirmed'
    ORDER BY s.departure_time
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcoming_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Popüler güzergahlar
$popular_routes = $db->query("
    SELECT c1.name as from_city, c2.name as to_city, r.id,
           r.from_city_id, r.to_city_id,
           MIN(s.price) as min_price,
           COUNT(DISTINCT s.id) as schedule_count
    FROM routes r
    INNER JOIN cities c1 ON r.from_city_id = c1.id
    INNER JOIN cities c2 ON r.to_city_id = c2.id
    INNER JOIN schedules s ON r.id = s.route_id AND s.departure_time > NOW() AND s.status = 'scheduled'
    WHERE r.is_active = 1
    GROUP BY r.id
    ORDER BY schedule_count DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Şehirler (arama için)
$cities = $db->query("SELECT * FROM cities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Hoş Geldiniz, <?php echo sanitize($_SESSION['full_name']); ?>!</h1>
</div>

<!-- Hızlı Arama -->
<div class="card" style="background: linear-gradient(135deg, #fff5f5, #fff); border: 1px solid #e94560;">
    <h3 class="card-title">🔍 Bilet Ara</h3>
    <form action="search.php" method="GET" class="search-form">
        <div class="form-group">
            <label>Nereden</label>
            <select name="from" class="form-control" required>
                <option value="">Seçiniz</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city['id']; ?>"><?php echo sanitize($city['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Nereye</label>
            <select name="to" class="form-control" required>
                <option value="">Seçiniz</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city['id']; ?>"><?php echo sanitize($city['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tarih</label>
            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sefer Ara</button>
        </div>
    </form>
</div>

<?php if (!empty($upcoming_trips)): ?>
<!-- Yaklaşan Seyahatler -->
<div class="card">
    <h3 class="card-title">🎫 Yaklaşan Seyahatleriniz</h3>
    <?php foreach ($upcoming_trips as $trip): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f8f9fa; border: 1px solid #e1e4e8; border-radius: 10px; margin-bottom: 10px;">
        <div>
            <div style="font-weight: 600; color: #333; font-size: 18px;">
                <?php echo sanitize($trip['from_city'] . ' → ' . $trip['to_city']); ?>
            </div>
            <div style="color: #666; margin-top: 5px;">
                <?php echo formatDateTime($trip['departure_time']); ?> • Koltuk <?php echo $trip['seat_number']; ?>
            </div>
            <div style="color: #666; font-size: 13px; margin-top: 3px;">
                <?php echo sanitize($trip['company_name']); ?> • <?php echo sanitize($trip['plate_number']); ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 12px; color: #666;">Bilet Kodu</div>
            <div style="background: rgba(233,69,96,0.1); color: #e94560; padding: 5px 12px; border-radius: 5px; font-weight: 600; font-family: monospace;">
                <?php echo sanitize($trip['booking_code']); ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <a href="tickets.php" class="btn btn-secondary" style="margin-top: 10px;">Tüm Biletlerim →</a>
</div>
<?php endif; ?>

<!-- Popüler Güzergahlar -->
<?php if (!empty($popular_routes)): ?>
<div class="card">
    <h3 class="card-title">🔥 Popüler Güzergahlar</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <?php foreach ($popular_routes as $route): ?>
        <a href="search.php?from=<?php echo $route['from_city_id']; ?>&to=<?php echo $route['to_city_id']; ?>&date=<?php echo date('Y-m-d'); ?>" 
           style="display: block; padding: 20px; background: #f8f9fa; border: 1px solid #e1e4e8; border-radius: 10px; text-decoration: none; transition: all 0.3s ease;"
           onmouseover="this.style.background='rgba(233,69,96,0.1)';"
           onmouseout="this.style.background='#f8f9fa';">
            <div style="font-weight: 600; color: #333;">
                <?php echo sanitize($route['from_city'] . ' → ' . $route['to_city']); ?>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                <span style="color: #666; font-size: 13px;"><?php echo $route['schedule_count']; ?> sefer</span>
                <span style="color: #e94560; font-weight: 600;"><?php echo formatMoney($route['min_price']); ?>'den</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
