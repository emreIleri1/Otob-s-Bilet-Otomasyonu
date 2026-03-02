<?php
/**
 * Şoför - Sefer Talebi
 */
$page_title = 'Yeni Sefer Talebi';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['sofor']);

$database = new Database();
$db = $database->getConnection();
$driver_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

// Talep İşleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_id = intval($_POST['route_id'] ?? 0);
    $bus_id = intval($_POST['bus_id'] ?? 0);
    $departure_time = sanitize($_POST['departure_time'] ?? '');
    
    if ($route_id > 0 && $bus_id > 0 && $departure_time) {
        // Tarih kontrolü (Geçmişe talep açılamaz)
        if (strtotime($departure_time) < time()) {
            $message = 'Geçmiş bir tarihe sefer talebi oluşturulamaz.';
            $message_type = 'error';
        } else {
            // Müsaitlik kontrolü (Basit)
            // Otobüs o saatte başka seferde mi?
            $stmt = $db->prepare("
                SELECT id FROM schedules 
                WHERE bus_id = ? 
                AND (
                    (departure_time <= ? AND arrival_time >= ?) 
                )
                AND status != 'cancelled'
            ");
            // Not: arrival_time olmadığı için tam çakışma kontrolü zor, basitçe o anki seferleri kontrol ediyoruz.
            // Daha detaylı kontrol için route süresini ekleyip arrival_time tahmini yapmak gerekir.
            // Şimdilik sadece talep oluşturuyoruz.
            
            $stmt = $db->prepare("INSERT INTO trip_requests (driver_id, route_id, bus_id, departure_time, status) VALUES (?, ?, ?, ?, 'pending')");
            if ($stmt->execute([$driver_id, $route_id, $bus_id, $departure_time])) {
                $message = 'Sefer talebiniz başarıyla oluşturuldu ve yönetici onayına gönderildi.';
                $message_type = 'success';
            } else {
                $message = 'Talep oluşturulurken bir hata oluştu.';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'Lütfen tüm alanları doldurun.';
        $message_type = 'error';
    }
}

// Güzergahları Getir
try {
    $routes_stmt = $db->query("
        SELECT r.id, c1.name as from_city, c2.name as to_city, r.duration_minutes 
        FROM routes r 
        INNER JOIN cities c1 ON r.from_city_id = c1.id 
        INNER JOIN cities c2 ON r.to_city_id = c2.id 
        WHERE r.is_active = 1
        ORDER BY c1.name, c2.name
    ");
    $routes = $routes_stmt ? $routes_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $routes = [];
    error_log("Routes query error: " . $e->getMessage());
}

// Otobüsleri Getir
try {
    $buses_stmt = $db->query("
        SELECT b.*, bc.name as company_name 
        FROM buses b 
        INNER JOIN bus_companies bc ON b.company_id = bc.id 
        ORDER BY b.plate_number
    ");
    $buses = $buses_stmt ? $buses_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $buses = [];
    error_log("Buses query error: " . $e->getMessage());
}

// Geçmiş Taleplerim
$requests = [];
try {
    $my_requests = $db->prepare("
        SELECT tr.*, c1.name as from_city, c2.name as to_city, b.plate_number
        FROM trip_requests tr
        INNER JOIN routes r ON tr.route_id = r.id
        INNER JOIN cities c1 ON r.from_city_id = c1.id
        INNER JOIN cities c2 ON r.to_city_id = c2.id
        INNER JOIN buses b ON tr.bus_id = b.id
        WHERE tr.driver_id = ?
        ORDER BY tr.created_at DESC
    ");
    if ($my_requests && $my_requests->execute([$driver_id])) {
        $requests = $my_requests->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $requests = [];
    error_log("Requests query error: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1 class="page-title">Yeni Sefer Talebi</h1>
    <a href="schedules.php" class="btn btn-secondary">← Seferlerim</a>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
    <!-- Talep Formu -->
    <div class="card" style="background: #fff; border: 1px solid #e1e4e8;">
        <h3 class="card-title">Talep Oluştur</h3>
        <form method="POST">
            <div class="form-group">
                <label>Güzergah</label>
                <select name="route_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($routes as $route): ?>
                        <option value="<?php echo $route['id']; ?>">
                            <?php echo sanitize($route['from_city'] . ' → ' . $route['to_city']); ?> 
                            (<?php echo floor($route['duration_minutes']/60) . 's ' . ($route['duration_minutes']%60) . 'dk'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Otobüs</label>
                <select name="bus_id" class="form-control" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?php echo $bus['id']; ?>">
                            <?php echo sanitize($bus['plate_number']); ?> - <?php echo sanitize($bus['company_name']); ?> 
                            (<?php echo $bus['capacity']; ?> Kişilik)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Kalkış Zamanı</label>
                <input type="datetime-local" name="departure_time" class="form-control" required min="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Talep Gönder</button>
        </form>
    </div>
    
    <!-- Taleplerim Listesi -->
    <div class="card" style="background: #fff; border: 1px solid #e1e4e8;">
        <h3 class="card-title">Taleplerim</h3>
        <?php if (empty($requests)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">Henüz bir talep oluşturmadınız.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Güzergah</th>
                        <th>Otobüs</th>
                        <th>Talep Edilen Zaman</th>
                        <th>Durum</th>
                        <th>Oluşturulma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?php echo sanitize($req['from_city'] . ' → ' . $req['to_city']); ?></td>
                        <td><?php echo sanitize($req['plate_number']); ?></td>
                        <td><?php echo formatDateTime($req['departure_time']); ?></td>
                        <td>
                            <?php 
                            if ($req['status'] === 'pending') echo '<span style="color: #ffc107; font-weight: bold;">Beklemede</span>';
                            elseif ($req['status'] === 'approved') echo '<span style="color: #28a745; font-weight: bold;">Onaylandı</span>';
                            else echo '<span style="color: #dc3545; font-weight: bold;">Reddedildi</span>';
                            ?>
                        </td>
                        <td style="font-size: 12px; color: #666;"><?php echo formatDateTime($req['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
