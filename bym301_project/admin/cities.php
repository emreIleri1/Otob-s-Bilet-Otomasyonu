<?php
/**
 * Admin - Şehir Yönetimi
 */
$page_title = 'Şehir Yönetimi';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $plate_code = sanitize($_POST['plate_code'] ?? '');
        
        if (!empty($name)) {
            $stmt = $db->prepare("SELECT id FROM cities WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->fetch()) {
                $message = 'Bu şehir zaten mevcut.';
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO cities (name, plate_code) VALUES (?, ?)");
                $stmt->execute([$name, $plate_code]);
                $message = 'Şehir eklendi.';
                $message_type = 'success';
            }
        }
    }
    
    if ($action === 'delete') {
        $city_id = intval($_POST['city_id'] ?? 0);
        
        // Güzergahlarda kullanılıyor mu kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM routes WHERE from_city_id = ? OR to_city_id = ?");
        $stmt->execute([$city_id, $city_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = 'Bu şehir güzergahlarda kullanılıyor, silinemez!';
            $message_type = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM cities WHERE id = ?");
            $stmt->execute([$city_id]);
            $message = 'Şehir silindi.';
            $message_type = 'success';
        }
    }
    
    if ($action === 'update') {
        $city_id = intval($_POST['city_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $plate_code = sanitize($_POST['plate_code'] ?? '');
        
        $stmt = $db->prepare("UPDATE cities SET name = ?, plate_code = ? WHERE id = ?");
        $stmt->execute([$name, $plate_code, $city_id]);
        $message = 'Şehir güncellendi.';
        $message_type = 'success';
    }
}

// Şehirler ve güzergah sayıları - Sorgu #4
$cities = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM routes WHERE from_city_id = c.id OR to_city_id = c.id) as route_count
    FROM cities c
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Şehir Yönetimi</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ Yeni Şehir</button>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $message_type); ?>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Şehir Adı</th>
                <th>Plaka Kodu</th>
                <th>Güzergah Sayısı</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cities as $city): ?>
            <tr>
                <td><?php echo $city['id']; ?></td>
                <td><?php echo sanitize($city['name']); ?></td>
                <td><?php echo sanitize($city['plate_code'] ?? '-'); ?></td>
                <td><?php echo $city['route_count']; ?></td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="editCity(<?php echo htmlspecialchars(json_encode($city)); ?>)">Düzenle</button>
                    <?php if ($city['route_count'] == 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu şehri silmek istediğinize emin misiniz?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="city_id" value="<?php echo $city['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Şehir Ekleme Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Yeni Şehir Ekle</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Şehir Adı</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Plaka Kodu</label>
                <input type="text" name="plate_code" class="form-control" maxlength="10">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Ekle</button>
        </form>
    </div>
</div>

<!-- Şehir Düzenleme Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #333;">Şehir Düzenle</h3>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="city_id" id="edit_city_id">
            <div class="form-group">
                <label>Şehir Adı</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Plaka Kodu</label>
                <input type="text" name="plate_code" id="edit_plate_code" class="form-control" maxlength="10">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Güncelle</button>
        </form>
    </div>
</div>

<?php ob_start(); ?>
<script>
function editCity(city) {
    document.getElementById('edit_city_id').value = city.id;
    document.getElementById('edit_name').value = city.name;
    document.getElementById('edit_plate_code').value = city.plate_code || '';
    document.getElementById('editModal').classList.add('active');
}
</script>
<?php 
$extra_js = ob_get_clean();
echo $extra_js;
require_once __DIR__ . '/../includes/footer.php'; 
?>
