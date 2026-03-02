<?php
/**
 * Yardımcı Fonksiyonlar
 */

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateBookingCode() {
    return 'BK' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatTime($date) {
    return date('H:i', strtotime($date));
}

function formatDateTime($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' TL';
}

function getStatusBadge($status) {
    $badges = [
        'pending' => ['Beklemede', '#ffc107', '#000'],
        'confirmed' => ['Onaylandı', '#28a745', '#fff'],
        'cancelled' => ['İptal', '#dc3545', '#fff'],
        'scheduled' => ['Planlandı', '#17a2b8', '#fff'],
        'departed' => ['Yolda', '#007bff', '#fff'],
        'completed' => ['Tamamlandı', '#28a745', '#fff']
    ];
    
    $badge = $badges[$status] ?? ['Bilinmiyor', '#6c757d', '#fff'];
    return '<span style="background-color:'.$badge[1].';color:'.$badge[2].';padding:3px 8px;border-radius:4px;font-size:12px;">'.$badge[0].'</span>';
}

function logAction($db, $user_id, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $details, $ip]);
}

function showAlert($message, $type = 'info') {
    $colors = [
        'success' => ['#d4edda', '#155724', '#c3e6cb'],
        'error' => ['#f8d7da', '#721c24', '#f5c6cb'],
        'warning' => ['#fff3cd', '#856404', '#ffeeba'],
        'info' => ['#d1ecf1', '#0c5460', '#bee5eb']
    ];
    $c = $colors[$type] ?? $colors['info'];
    echo '<div style="background-color:'.$c[0].';color:'.$c[1].';border:1px solid '.$c[2].';padding:12px 20px;border-radius:5px;margin-bottom:15px;">'.$message.'</div>';
}

/**
 * TC Kimlik No doğrulama algoritması
 * @param string $tcno 11 haneli TC Kimlik No
 * @return bool
 */
function validateTcNo($tcno) {
    if (strlen($tcno) != 11 || !ctype_digit($tcno) || $tcno[0] == '0') {
        return false;
    }
    
    $digits = array_map('intval', str_split($tcno));
    
    // 10. hane kontrolü
    $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
    $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];
    $digit10 = ($oddSum * 7 - $evenSum) % 10;
    
    if ($digit10 != $digits[9]) {
        return false;
    }
    
    // 11. hane kontrolü
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += $digits[$i];
    }
    
    if ($sum % 10 != $digits[10]) {
        return false;
    }
    
    return true;
}

/**
 * E-posta format doğrulama
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Telefon numarası formatla ve doğrula
 * @param string $phone
 * @return string|false Formatlanmış numara veya false
 */
function validatePhone($phone) {
    // Sadece rakamları al
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Başında 0 varsa kaldır
    if (strlen($phone) == 11 && $phone[0] == '0') {
        $phone = substr($phone, 1);
    }
    
    // 10 haneli mi kontrol et (Türkiye formatı)
    if (strlen($phone) != 10) {
        return false;
    }
    
    // 5 ile başlamalı (GSM numarası)
    if ($phone[0] != '5') {
        return false;
    }
    
    return $phone;
}

/**
 * Süre hesaplama (dakika cinsinden)
 * @param string $departure Kalkış zamanı
 * @param string $arrival Varış zamanı
 * @return string Formatlanmış süre
 */
function formatDuration($departure, $arrival) {
    $minutes = (strtotime($arrival) - strtotime($departure)) / 60;
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . ' sa ' . $mins . ' dk';
}

/**
 * Şifre güvenlik kontrolü
 * @param string $password
 * @return array Hata mesajları veya boş dizi
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    }
    
    return $errors;
}

/**
 * Relatif zaman (örn: "2 saat önce")
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'az önce';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' dakika önce';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' saat önce';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' gün önce';
    } else {
        return formatDate($datetime);
    }
}

// =============================================
// VERİTABANI FONKSİYON WRAPPER'LARI
// SQL Function'larını PHP'den çağırmak için
// =============================================

/**
 * Sefer doluluk oranını hesapla (SQL Function kullanır)
 * @param PDO $db Veritabanı bağlantısı
 * @param int $schedule_id Sefer ID
 * @return float Doluluk oranı (%)
 */
function getOccupancyRate($db, $schedule_id) {
    $stmt = $db->prepare("SELECT calculate_occupancy_rate(?)");
    $stmt->execute([$schedule_id]);
    return floatval($stmt->fetchColumn());
}

/**
 * Kullanıcının toplam harcamasını getir (SQL Function kullanır)
 * @param PDO $db Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @return float Toplam harcama tutarı
 */
function getUserTotalSpending($db, $user_id) {
    $stmt = $db->prepare("SELECT get_user_total_spending(?)");
    $stmt->execute([$user_id]);
    return floatval($stmt->fetchColumn());
}

/**
 * Sefer için kalan koltuk sayısını getir (SQL Function kullanır)
 * @param PDO $db Veritabanı bağlantısı
 * @param int $schedule_id Sefer ID
 * @return int Boş koltuk sayısı
 */
function getAvailableSeatsCount($db, $schedule_id) {
    $stmt = $db->prepare("SELECT get_available_seats_count(?)");
    $stmt->execute([$schedule_id]);
    return intval($stmt->fetchColumn());
}

