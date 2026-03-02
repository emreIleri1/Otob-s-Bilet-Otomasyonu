<?php
/**
 * Ortak Header
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

// Session timeout kontrolü (giriş yapılmışsa)
if (isLoggedIn()) {
    checkSessionTimeout();
}

$current_user = getCurrentUser();
$page_title = $page_title ?? 'Otobüs Bilet Sistemi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OtoBilet - Türkiye'nin en güvenilir otobüs bilet satış platformu. Hızlı, güvenli ve kolay bilet alımı.">
    <meta name="keywords" content="otobüs bileti, bilet al, online bilet, seyahat, ulaşım">
    <meta name="author" content="OtoBilet">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/x-icon" href="/bym301_project/favicon.ico">
    <title><?php echo sanitize($page_title); ?> | OtoBilet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            color: #333;
        }
        
        .navbar {
            background: #ffffff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #eee;
        }
        
        .navbar-brand {
            color: #333;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }
        
        .navbar-brand span {
            color: #e94560;
        }
        
        .navbar-nav {
            display: flex;
            gap: 20px;
            list-style: none;
        }
        
        .navbar-nav a {
            color: #555;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .navbar-nav a:hover {
            background: rgba(233, 69, 96, 0.1);
            color: #e94560;
        }
        
        .navbar-nav a.active {
            background: #e94560;
            color: #fff;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-badge {
            background: rgba(233, 69, 96, 0.1);
            color: #e94560;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .logout-btn {
            background: #e94560;
            color: #fff;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c73e54;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .card {
            background: #ffffff;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #e1e4e8;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        
        .card-title {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e94560;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e94560, #c73e54);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(233, 69, 96, 0.4);
        }
        
        .btn-secondary {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
        }
        
        .btn-secondary:hover {
            background: #dee2e6;
        }
        
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-success {
            background: #28a745;
            color: #fff;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: rgba(233, 69, 96, 0.05);
            color: #e94560;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            background: #fff;
            color: #333;
            font-size: 16px;
            transition: all 0.3s ease;
            min-height: 50px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }
        
        .form-control option {
            background: #fff;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e1e4e8;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #e94560;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(5, 50px);
            gap: 10px;
            justify-content: center;
        }
        
        .seat {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            background: #f1f3f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .seat.available {
            background: #fff;
            border: 2px solid #28a745;
            color: #28a745;
        }
        
        .seat.available:hover {
            background: #28a745;
            color: #fff;
        }
        
        .seat.occupied {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid #dc3545;
            color: #dc3545;
            cursor: not-allowed;
        }
        
        .seat.selected {
            background: #007bff;
            border: 2px solid #007bff;
            color: #fff;
        }
        
        .seat.corridor {
            background: transparent;
            border: none;
            cursor: default;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #333;
            font-size: 24px;
            cursor: pointer;
        }
    
    </style>
</head>
<body>
    <?php if ($current_user): ?>
    <nav class="navbar">
        <a href="/bym301_project/" class="navbar-brand">Oto<span>Bilet</span></a>
        
        <ul class="navbar-nav">
            <?php if ($current_user['user_type'] === 'admin'): ?>
                <li><a href="/bym301_project/admin/">Dashboard</a></li>
                <li><a href="/bym301_project/admin/users.php">Kullanıcılar</a></li>
                <li><a href="/bym301_project/admin/buses.php">Otobüsler</a></li>
                <li><a href="/bym301_project/admin/cities.php">Şehirler</a></li>
                <li><a href="/bym301_project/admin/routes.php">Güzergahlar</a></li>
                <li><a href="/bym301_project/admin/schedules.php">Seferler</a></li>
                <li><a href="/bym301_project/admin/trip_requests.php">Talepler</a></li>
                <li><a href="/bym301_project/admin/reports.php">Raporlar</a></li>
            <?php elseif ($current_user['user_type'] === 'sofor'): ?>
                <li><a href="/bym301_project/driver/">Dashboard</a></li>
                <li><a href="/bym301_project/driver/schedules.php">Seferlerim</a></li>
                <li><a href="/bym301_project/driver/request_trip.php">Talep Oluştur</a></li>
                <li><a href="/bym301_project/driver/passengers.php">Yolcu Listesi</a></li>
            <?php else: ?>
                <li><a href="/bym301_project/passenger/">Ana Sayfa</a></li>
                <li><a href="/bym301_project/passenger/search.php">Bilet Ara</a></li>
                <li><a href="/bym301_project/passenger/tickets.php">Biletlerim</a></li>
                <li><a href="/bym301_project/passenger/profile.php">Profilim</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="user-info">
            <span><?php echo sanitize($current_user['full_name']); ?></span>
            <span class="user-badge"><?php echo ucfirst($current_user['user_type']); ?></span>
            <a href="/bym301_project/auth/logout.php" class="logout-btn">Çıkış</a>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container">
