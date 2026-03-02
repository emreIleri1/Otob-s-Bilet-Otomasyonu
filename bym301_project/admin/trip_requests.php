<?php
/**
 * Admin - Sefer Talepleri
 */
$page_title = 'Sefer Talepleri';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();
$message = '';
$message_type = 'info';

// İşlem (Onay/Red)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($request_id > 0) {
        try {
            $stmt = $db->prepare("
                SELECT tr.*, r.duration_minutes
                FROM trip_requests tr
                INNER JOIN routes r ON tr.route_id = r.id
                WHERE tr.id = ? AND tr.status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                if ($action === 'approve') {
                    // Sefer Oluştur
                    $departure = strtotime($request['departure_time']);
                    $arrival = $departure + ($request['duration_minutes'] * 60);
                    $arrival_time = date('Y-m-d H:i:s', $arrival);
                    
                    // Varsayılan fiyat (routes tablosunda price yok)
                    $default_price = 350.00;
                    
                    $stmt = $db->prepare("
                        INSERT INTO schedules (route_id, bus_id, driver_id, departure_time, arrival_time, price, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
                    ");
                    
                    if ($stmt->execute([
                        $request['route_id'],
                        $request['bus_id'],
                        $request['driver_id'],
                        $request['departure_time'],
                        $arrival_time,
                        $default_price
                    ])) {
                        // Talebi Güncelle
                        $update = $db->prepare("UPDATE trip_requests SET status = 'approved' WHERE id = ?");
                        $update->execute([$request_id]);
                        
                        $message = 'Talep onaylandı ve sefer oluşturuldu.';
                        $message_type = 'success';
                        logAction($db, $_SESSION['user_id'], 'REQUEST_APPROVE', "Sefer talebi onaylandı: $request_id");
                    } else {
                        $message = 'Sefer oluşturulurken hata oluştu.';
                        $message_type = 'error';
                    }
                } elseif ($action === 'reject') {
                    $update = $db->prepare("UPDATE trip_requests SET status = 'rejected' WHERE id = ?");
                    $update->execute([$request_id]);
                    
                    $message = 'Talep reddedildi.';
                    $message_type = 'success';
                    logAction($db, $_SESSION['user_id'], 'REQUEST_REJECT', "Sefer talebi reddedildi: $request_id");
                }
            }
        } catch (PDOException $e) {
            $message = 'Veritabanı hatası: ' . $e->getMessage();
            $message_type = 'error';
            error_log("Trip request approval error: " . $e->getMessage());
        }
    }
}

// Bekleyen Talepler
$pending_requests = [];
$history_requests = [];

try {
    $pending_requests = $db->query("
        SELECT tr.*, u.full_name as driver_name, c1.name as from_city, c2.name as to_city, b.plate_number
        FROM trip_requests tr
        INNER JOIN users u ON tr.driver_id = u.id
        INNER JOIN routes r ON tr.route_id = r.id
        INNER JOIN cities c1 ON r.from_city_id = c1.id
        INNER JOIN cities c2 ON r.to_city_id = c2.id
        INNER JOIN buses b ON tr.bus_id = b.id
        WHERE tr.status = 'pending'
        ORDER BY tr.departure_time
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Son İşlenen Talepler (History)
    $history_requests = $db->query("
        SELECT tr.*, u.full_name as driver_name, c1.name as from_city, c2.name as to_city, b.plate_number
        FROM trip_requests tr
        INNER JOIN users u ON tr.driver_id = u.id
        INNER JOIN routes r ON tr.route_id = r.id
        INNER JOIN cities c1 ON r.from_city_id = c1.id
        INNER JOIN cities c2 ON r.to_city_id = c2.id
        INNER JOIN buses b ON tr.bus_id = b.id
        WHERE tr.status != 'pending'
        ORDER BY tr.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // trip_requests tablosu yoksa hata mesajı göster
    $message = 'Veritabanı hatası: trip_requests tablosu bulunamadı. Lütfen schema.sql dosyasını yeniden çalıştırın.';
    $message_type = 'error';
    error_log("Trip Requests Error: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1 class="page-title">Sefer Talepleri</h1>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<!-- Bekleyen Talepler -->
<div class="card" style="margin-bottom: 30px;">
    <h3 class="card-title">Bekleyen Talepler (<?php echo count($pending_requests); ?>)</h3>
    <?php if (empty($pending_requests)): ?>
        <p style="text-align: center; color: #666; padding: 20px;">Bekleyen talep yok.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Şoför</th>
                    <th>Güzergah</th>
                    <th>Otobüs</th>
                    <th>Talep Edilen Zaman</th>
                    <th>Oluşturulma</th>
                    <th style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_requests as $req): ?>
                <tr>
                    <td><?php echo sanitize($req['driver_name']); ?></td>
                    <td><?php echo sanitize($req['from_city'] . ' → ' . $req['to_city']); ?></td>
                    <td><?php echo sanitize($req['plate_number']); ?></td>
                    <td><?php echo formatDateTime($req['departure_time']); ?></td>
                    <td><?php echo formatDateTime($req['created_at']); ?></td>
                    <td style="text-align: right;">
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-sm btn-success">Onayla</button>
                        </form>
                        <form method="POST" style="display: inline-block; margin-left: 5px;" onsubmit="return confirm('Bu talebi reddetmek istediğinize emin misiniz?')">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-sm btn-danger">Reddet</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Geçmiş Talepler -->
<div class="card">
    <h3 class="card-title">Son İşlemler</h3>
    <table>
        <thead>
            <tr>
                <th>Şoför</th>
                <th>Güzergah</th>
                <th>Zaman</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history_requests as $req): ?>
            <tr>
                <td><?php echo sanitize($req['driver_name']); ?></td>
                <td><?php echo sanitize($req['from_city'] . ' → ' . $req['to_city']); ?></td>
                <td><?php echo formatDateTime($req['departure_time']); ?></td>
                <td>
                    <?php 
                    if ($req['status'] === 'approved') echo '<span style="color: #28a745;">Onaylandı</span>';
                    else echo '<span style="color: #dc3545;">Reddedildi</span>';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
