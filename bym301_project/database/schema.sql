-- Otobüs Bilet Satış Sistemi - Veritabanı Şeması
-- 12 Tablo, 12+ İlişki, 3 Tetikleyici

DROP DATABASE IF EXISTS bus_ticket_system;
CREATE DATABASE bus_ticket_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bus_ticket_system;

-- =====================
-- 1. KULLANICI TİPLERİ
-- =====================
CREATE TABLE user_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO user_types (id, name, description) VALUES
(1, 'admin', 'Sistem Yöneticisi'),
(2, 'sofor', 'Otobüs Şoförü'),
(3, 'yolcu', 'Yolcu');

-- =====================
-- 2. KULLANICILAR
-- =====================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_type_id INT NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    tc_no VARCHAR(11),
    gender ENUM('erkek', 'kadin') NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_type_id) REFERENCES user_types(id)
);

-- Örnek Kullanıcılar (Şifre: 123456 - ilk girişte otomatik hash'lenir)
INSERT INTO users (user_type_id, email, password, full_name, phone, tc_no) VALUES
(1, 'admin@test.com', '123456', 'Admin User', '5551234567', '12345678901'),
(2, 'sofor1@test.com', '123456', 'Ahmet Yılmaz', '5553234567', '12345678903'),
(2, 'sofor2@test.com', '123456', 'Mehmet Kaya', '5553234568', '12345678904'),
(3, 'yolcu@test.com', '123456', 'Ali Veli', '5554234567', '12345678905');

-- =====================
-- 3. ŞEHİRLER
-- =====================
CREATE TABLE cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    plate_code VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO cities (name, plate_code) VALUES
('İstanbul', '34'),
('Ankara', '06'),
('İzmir', '35'),
('Bursa', '16'),
('Antalya', '07'),
('Adana', '01'),
('Konya', '42'),
('Gaziantep', '27'),
('Mersin', '33'),
('Eskişehir', '26');

-- =====================
-- 4. OTOBÜS FİRMALARI
-- =====================
CREATE TABLE bus_companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    logo_url VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO bus_companies (name, phone, email) VALUES
('Metro Turizm', '4441455', 'info@metroturizm.com'),
('Kamil Koç', '4440562', 'info@kamilkoc.com'),
('Pamukkale', '4440535', 'info@pamukkale.com'),
('Ulusoy', '4441888', 'info@ulusoy.com');

-- =====================
-- 5. OTOBÜSLER
-- =====================
CREATE TABLE buses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    model VARCHAR(100),
    capacity INT NOT NULL DEFAULT 40,
    has_wifi TINYINT(1) DEFAULT 0,
    has_tv TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES bus_companies(id)
);

INSERT INTO buses (company_id, plate_number, model, capacity, has_wifi, has_tv) VALUES
(1, '34 ABC 123', 'Mercedes Travego', 40, 1, 1),
(1, '34 ABC 124', 'MAN Lions Coach', 45, 1, 1),
(2, '06 DEF 456', 'Neoplan Cityliner', 40, 1, 0),
(2, '06 DEF 457', 'Setra S 517 HD', 42, 1, 1),
(3, '35 GHI 789', 'Mercedes Tourismo', 40, 0, 1),
(4, '34 JKL 012', 'Volvo 9700', 44, 1, 1);

-- =====================
-- 6. GÜZERGAHLAR
-- =====================
CREATE TABLE routes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_city_id INT NOT NULL,
    to_city_id INT NOT NULL,
    distance_km INT,
    duration_minutes INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_city_id) REFERENCES cities(id),
    FOREIGN KEY (to_city_id) REFERENCES cities(id),
    UNIQUE KEY unique_route (from_city_id, to_city_id)
);

INSERT INTO routes (from_city_id, to_city_id, distance_km, duration_minutes) VALUES
(1, 2, 450, 300),  -- İstanbul -> Ankara
(2, 1, 450, 300),  -- Ankara -> İstanbul
(1, 3, 480, 360),  -- İstanbul -> İzmir
(3, 1, 480, 360),  -- İzmir -> İstanbul
(1, 4, 150, 120),  -- İstanbul -> Bursa
(4, 1, 150, 120),  -- Bursa -> İstanbul
(2, 3, 580, 420),  -- Ankara -> İzmir
(3, 2, 580, 420),  -- İzmir -> Ankara
(2, 5, 480, 360),  -- Ankara -> Antalya
(5, 2, 480, 360);  -- Antalya -> Ankara

-- =====================
-- 7. SEFERLER
-- =====================
CREATE TABLE schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    route_id INT NOT NULL,
    bus_id INT NOT NULL,
    driver_id INT,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('scheduled', 'departed', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (driver_id) REFERENCES users(id)
);

-- Önümüzdeki 7 gün için seferler
INSERT INTO schedules (route_id, bus_id, driver_id, departure_time, arrival_time, price) VALUES
-- İstanbul -> Ankara
(1, 1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 13 HOUR, 350.00),
(1, 2, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 14 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 19 HOUR, 350.00),
(1, 1, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 13 HOUR, 350.00),
-- Ankara -> İstanbul
(2, 3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 9 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 14 HOUR, 350.00),
(2, 4, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 15 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 20 HOUR, 350.00),
-- İstanbul -> İzmir
(3, 5, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 7 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 13 HOUR, 400.00),
(3, 5, 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 7 HOUR, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 13 HOUR, 400.00),
-- İstanbul -> Bursa
(5, 6, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 12 HOUR, 150.00),
(5, 6, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 16 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 18 HOUR, 150.00);

-- =====================
-- 8. KOLTUKLAR
-- =====================
CREATE TABLE seats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    seat_number INT NOT NULL,
    seat_type ENUM('window', 'aisle', 'middle') DEFAULT 'aisle',
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    UNIQUE KEY unique_seat (bus_id, seat_number)
);

-- Her otobüs için koltuklar oluştur
DELIMITER //
CREATE PROCEDURE generate_seats()
BEGIN
    DECLARE bus_id INT;
    DECLARE bus_capacity INT;
    DECLARE seat_num INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE bus_cursor CURSOR FOR SELECT id, capacity FROM buses;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN bus_cursor;
    
    bus_loop: LOOP
        FETCH bus_cursor INTO bus_id, bus_capacity;
        IF done THEN
            LEAVE bus_loop;
        END IF;
        
        SET seat_num = 1;
        WHILE seat_num <= bus_capacity DO
            INSERT INTO seats (bus_id, seat_number, seat_type) 
            VALUES (bus_id, seat_num, 
                CASE 
                    WHEN seat_num % 4 IN (1, 0) THEN 'window'
                    ELSE 'aisle'
                END
            );
            SET seat_num = seat_num + 1;
        END WHILE;
    END LOOP;
    
    CLOSE bus_cursor;
END//
DELIMITER ;

CALL generate_seats();
DROP PROCEDURE generate_seats;

-- =====================
-- 9. REZERVASYONLAR
-- =====================
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    seat_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    booking_code VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id),
    FOREIGN KEY (seat_id) REFERENCES seats(id)
);

-- =====================
-- 10. ÖDEMELER
-- =====================
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'cash') DEFAULT 'credit_card',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- =====================
-- 11. İPTALLER
-- =====================
CREATE TABLE cancellations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL UNIQUE,
    reason TEXT,
    refund_amount DECIMAL(10,2),
    cancelled_by INT,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (cancelled_by) REFERENCES users(id)
);

-- =====================
-- 12. SİSTEM LOGLARI
-- =====================
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =====================
-- 13. SEFER TALEPLERİ
-- =====================
CREATE TABLE trip_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    route_id INT NOT NULL,
    bus_id INT NOT NULL,
    departure_time DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id),
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
-- TETİKLEYİCİLER (TRIGGERS)
-- =====================

-- Trigger 1: Rezervasyon oluşturulduğunda koltuk durumunu güncelle ve log ekle
DELIMITER //
CREATE TRIGGER after_booking_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    -- Koltuk durumunu güncelle (bu sefer için)
    INSERT INTO logs (user_id, action, details, created_at)
    VALUES (NEW.user_id, 'BOOKING_CREATED', 
            CONCAT('Rezervasyon ID: ', NEW.id, ', Sefer: ', NEW.schedule_id, ', Koltuk: ', NEW.seat_id),
            NOW());
END//
DELIMITER ;

-- Trigger 2: Ödeme tamamlandığında rezervasyon durumunu güncelle
DELIMITER //
CREATE TRIGGER after_payment_complete
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        UPDATE bookings 
        SET status = 'confirmed' 
        WHERE id = NEW.booking_id;
        
        INSERT INTO logs (user_id, action, details, created_at)
        VALUES (
            (SELECT user_id FROM bookings WHERE id = NEW.booking_id),
            'PAYMENT_COMPLETED', 
            CONCAT('Ödeme ID: ', NEW.id, ', Tutar: ', NEW.amount, ' TL, Rezervasyon: ', NEW.booking_id),
            NOW()
        );
    END IF;
END//
DELIMITER ;

-- Trigger 3: Rezervasyon iptal edildiğinde log ve iptal kaydı ekle
DELIMITER //
CREATE TRIGGER after_booking_cancel
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        INSERT INTO logs (user_id, action, details, created_at)
        VALUES (NEW.user_id, 'BOOKING_CANCELLED', 
                CONCAT('Rezervasyon ID: ', NEW.id, ' iptal edildi'),
                NOW());
    END IF;
END//
DELIMITER ;

-- =====================
-- FONKSİYONLAR (FUNCTIONS)
-- =====================

-- Function 1: Seferin doluluk oranını hesapla
DELIMITER //
CREATE FUNCTION calculate_occupancy_rate(p_schedule_id INT)
RETURNS DECIMAL(5,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_seats INT;
    DECLARE booked_seats INT;
    DECLARE occupancy_rate DECIMAL(5,2);
    
    -- Toplam koltuk sayısını al
    SELECT b.capacity INTO total_seats
    FROM schedules s
    JOIN buses b ON s.bus_id = b.id
    WHERE s.id = p_schedule_id;
    
    -- Rezerve edilmiş koltuk sayısını al
    SELECT COUNT(*) INTO booked_seats
    FROM bookings
    WHERE schedule_id = p_schedule_id AND status != 'cancelled';
    
    -- Doluluk oranını hesapla
    IF total_seats > 0 THEN
        SET occupancy_rate = (booked_seats / total_seats) * 100;
    ELSE
        SET occupancy_rate = 0;
    END IF;
    
    RETURN occupancy_rate;
END//
DELIMITER ;

-- Function 2: Kullanıcının toplam harcamasını hesapla
DELIMITER //
CREATE FUNCTION get_user_total_spending(p_user_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_amount DECIMAL(10,2);
    
    SELECT COALESCE(SUM(p.amount), 0) INTO total_amount
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE b.user_id = p_user_id AND p.status = 'completed';
    
    RETURN total_amount;
END//
DELIMITER ;

-- Function 3: Sefer için kalan koltuk sayısını hesapla
DELIMITER //
CREATE FUNCTION get_available_seats_count(p_schedule_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_seats INT;
    DECLARE booked_seats INT;
    
    -- Toplam koltuk sayısını al
    SELECT b.capacity INTO total_seats
    FROM schedules s
    JOIN buses b ON s.bus_id = b.id
    WHERE s.id = p_schedule_id;
    
    -- Rezerve edilmiş koltuk sayısını al
    SELECT COUNT(*) INTO booked_seats
    FROM bookings
    WHERE schedule_id = p_schedule_id AND status != 'cancelled';
    
    RETURN COALESCE(total_seats - booked_seats, 0);
END//
DELIMITER ;

-- =====================
-- GÖRÜNÜMLER (VIEWS)
-- =====================

-- View 1: Aktif seferler ve detayları
CREATE VIEW v_active_schedules AS
SELECT 
    s.id AS schedule_id,
    fc.name AS from_city,
    tc.name AS to_city,
    bc.name AS company_name,
    b.plate_number,
    u.full_name AS driver_name,
    s.departure_time,
    s.arrival_time,
    s.price,
    s.status,
    b.capacity AS total_seats,
    (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status != 'cancelled') AS booked_seats,
    b.has_wifi,
    b.has_tv
FROM schedules s
JOIN routes r ON s.route_id = r.id
JOIN cities fc ON r.from_city_id = fc.id
JOIN cities tc ON r.to_city_id = tc.id
JOIN buses b ON s.bus_id = b.id
JOIN bus_companies bc ON b.company_id = bc.id
LEFT JOIN users u ON s.driver_id = u.id
WHERE s.status = 'scheduled' AND s.departure_time > NOW();

-- View 2: Kullanıcı rezervasyon özeti
CREATE VIEW v_user_bookings AS
SELECT 
    b.id AS booking_id,
    b.booking_code,
    u.full_name AS passenger_name,
    u.email AS passenger_email,
    u.phone AS passenger_phone,
    fc.name AS from_city,
    tc.name AS to_city,
    s.departure_time,
    s.arrival_time,
    se.seat_number,
    bc.name AS company_name,
    bs.plate_number,
    b.status AS booking_status,
    p.amount AS payment_amount,
    p.status AS payment_status,
    b.created_at AS booking_date
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN schedules s ON b.schedule_id = s.id
JOIN routes r ON s.route_id = r.id
JOIN cities fc ON r.from_city_id = fc.id
JOIN cities tc ON r.to_city_id = tc.id
JOIN seats se ON b.seat_id = se.id
JOIN buses bs ON s.bus_id = bs.id
JOIN bus_companies bc ON bs.company_id = bc.id
LEFT JOIN payments p ON b.id = p.booking_id;

-- View 3: Şoför sefer özeti
CREATE VIEW v_driver_schedules AS
SELECT 
    u.id AS driver_id,
    u.full_name AS driver_name,
    s.id AS schedule_id,
    fc.name AS from_city,
    tc.name AS to_city,
    s.departure_time,
    s.arrival_time,
    b.plate_number,
    bc.name AS company_name,
    s.status,
    (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id AND status = 'confirmed') AS passenger_count
FROM schedules s
JOIN users u ON s.driver_id = u.id
JOIN routes r ON s.route_id = r.id
JOIN cities fc ON r.from_city_id = fc.id
JOIN cities tc ON r.to_city_id = tc.id
JOIN buses b ON s.bus_id = b.id
JOIN bus_companies bc ON b.company_id = bc.id
WHERE u.user_type_id = 3;

-- View 4: Günlük satış raporu
CREATE VIEW v_daily_sales_report AS
SELECT 
    DATE(p.created_at) AS sale_date,
    COUNT(DISTINCT b.id) AS total_bookings,
    SUM(p.amount) AS total_revenue,
    COUNT(DISTINCT b.user_id) AS unique_customers,
    AVG(p.amount) AS average_ticket_price
FROM payments p
JOIN bookings b ON p.booking_id = b.id
WHERE p.status = 'completed'
GROUP BY DATE(p.created_at);

-- View 5: Güzergah istatistikleri
CREATE VIEW v_route_statistics AS
SELECT 
    r.id AS route_id,
    fc.name AS from_city,
    tc.name AS to_city,
    r.distance_km,
    r.duration_minutes,
    COUNT(DISTINCT s.id) AS total_schedules,
    COUNT(DISTINCT b.id) AS total_bookings,
    COALESCE(SUM(p.amount), 0) AS total_revenue
FROM routes r
JOIN cities fc ON r.from_city_id = fc.id
JOIN cities tc ON r.to_city_id = tc.id
LEFT JOIN schedules s ON r.id = s.route_id
LEFT JOIN bookings b ON s.id = b.schedule_id AND b.status != 'cancelled'
LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
WHERE r.is_active = 1
GROUP BY r.id, fc.name, tc.name, r.distance_km, r.duration_minutes;


