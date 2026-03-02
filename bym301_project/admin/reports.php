<?php
/**
 * Admin - Raporlar
 */
$page_title = 'Raporlar';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

// Gelir Raporu - JOIN Sorgusu #7
$revenue_by_company = $db->query("
    SELECT bc.name as company_name, 
           COUNT(DISTINCT s.id) as schedule_count,
           COUNT(bk.id) as booking_count,
           COALESCE(SUM(p.amount), 0) as total_revenue
    FROM bus_companies bc
    LEFT JOIN buses b ON bc.id = b.company_id
    LEFT JOIN schedules s ON b.id = s.bus_id
    LEFT JOIN bookings bk ON s.id = bk.schedule_id AND bk.status = 'confirmed'
    LEFT JOIN payments p ON bk.id = p.booking_id AND p.status = 'completed'
    GROUP BY bc.id
    ORDER BY total_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Popüler Güzergahlar (View kullanımı)
$popular_routes = $db->query("
    SELECT from_city, to_city, total_bookings as booking_count, total_revenue
    FROM v_route_statistics
    WHERE total_bookings > 0
    ORDER BY total_bookings DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Aylık Gelir
$monthly_revenue = $db->query("
    SELECT DATE_FORMAT(p.created_at, '%Y-%m') as month,
           COUNT(p.id) as payment_count,
           SUM(p.amount) as total_revenue
    FROM payments p
    WHERE p.status = 'completed'
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// Günlük istatistikler (Son 7 gün) - View kullanımı
$daily_stats = $db->query("
    SELECT sale_date as booking_date, total_bookings as booking_count, total_revenue as revenue
    FROM v_daily_sales_report
    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY sale_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Genel Toplam
$totals = $db->query("
    SELECT 
        COUNT(DISTINCT bk.id) as total_bookings,
        COUNT(DISTINCT CASE WHEN bk.status = 'confirmed' THEN bk.id END) as confirmed_bookings,
        COUNT(DISTINCT CASE WHEN bk.status = 'cancelled' THEN bk.id END) as cancelled_bookings,
        COALESCE(SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END), 0) as total_revenue
    FROM bookings bk
    LEFT JOIN payments p ON bk.id = p.booking_id
")->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Raporlar</h1>
</div>

<!-- Özet Kartları -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($totals['total_bookings']); ?></div>
        <div class="stat-label">Toplam Rezervasyon</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($totals['confirmed_bookings']); ?></div>
        <div class="stat-label">Onaylı Rezervasyon</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($totals['cancelled_bookings']); ?></div>
        <div class="stat-label">İptal Edilen</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($totals['total_revenue'], 0, ',', '.'); ?> ₺</div>
        <div class="stat-label">Toplam Gelir</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Firma Bazlı Gelir -->
    <div class="card">
        <h3 class="card-title">Firma Bazlı Gelir</h3>
        <table>
            <thead>
                <tr>
                    <th>Firma</th>
                    <th>Sefer</th>
                    <th>Rezervasyon</th>
                    <th>Gelir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($revenue_by_company as $row): ?>
                <tr>
                    <td><?php echo sanitize($row['company_name']); ?></td>
                    <td><?php echo $row['schedule_count']; ?></td>
                    <td><?php echo $row['booking_count']; ?></td>
                    <td><strong><?php echo formatMoney($row['total_revenue']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Popüler Güzergahlar -->
    <div class="card">
        <h3 class="card-title">Popüler Güzergahlar</h3>
        <table>
            <thead>
                <tr>
                    <th>Güzergah</th>
                    <th>Rezervasyon</th>
                    <th>Gelir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($popular_routes as $row): ?>
                <tr>
                    <td><?php echo sanitize($row['from_city'] . ' → ' . $row['to_city']); ?></td>
                    <td><?php echo $row['booking_count']; ?></td>
                    <td><strong><?php echo formatMoney($row['total_revenue']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
    <!-- Aylık Gelir -->
    <div class="card">
        <h3 class="card-title">Aylık Gelir</h3>
        <table>
            <thead>
                <tr>
                    <th>Ay</th>
                    <th>Ödeme Sayısı</th>
                    <th>Gelir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_revenue as $row): ?>
                <tr>
                    <td><?php echo $row['month']; ?></td>
                    <td><?php echo $row['payment_count']; ?></td>
                    <td><strong><?php echo formatMoney($row['total_revenue']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($monthly_revenue)): ?>
                <tr><td colspan="3" style="text-align: center; color: rgba(255,255,255,0.5);">Veri yok</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Son 7 Gün -->
    <div class="card">
        <h3 class="card-title">Son 7 Gün</h3>
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Rezervasyon</th>
                    <th>Gelir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_stats as $row): ?>
                <tr>
                    <td><?php echo formatDate($row['booking_date']); ?></td>
                    <td><?php echo $row['booking_count']; ?></td>
                    <td><strong><?php echo formatMoney($row['revenue']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($daily_stats)): ?>
                <tr><td colspan="3" style="text-align: center; color: rgba(255,255,255,0.5);">Veri yok</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
