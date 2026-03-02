-- =============================================
-- SEED DATA (ÖRNEK VERİLER)
-- Her tablo için en az 10 veri gereksinimi için.
-- =============================================

-- 1. Users (Mevcut kullanıcılara ek yolcu ve şoför)
INSERT INTO users (user_type_id, email, password, full_name, phone, tc_no) VALUES
(3, 'yolcu2@test.com', '123456', 'Ayşe Demir', '5551111111', '11111111111'),
(3, 'yolcu3@test.com', '123456', 'Fatma Çelik', '5552222222', '22222222222'),
(3, 'yolcu4@test.com', '123456', 'Hasan Yılmaz', '5553333333', '33333333333'),
(3, 'yolcu5@test.com', '123456', 'Zeynep Kaya', '5554444444', '44444444444'),
(3, 'yolcu6@test.com', '123456', 'Mustafa Şen', '5555555555', '55555555555'),
(2, 'sofor3@test.com', '123456', 'Kemal Sunal', '5556666666', '66666666666'),
(3, 'yolcu7@test.com', '123456', 'Şener Şen', '5558888888', '88888888888');

-- 2. Bus Companies (Mevcut 4 var, 6 tane daha ekleyelim)
INSERT INTO bus_companies (name, phone, email) VALUES
('Varan', '4445555', 'info@varan.com'),
('Nilüfer', '4446666', 'info@nilufer.com'),
('Efe Tur', '4447777', 'info@efetur.com'),
('Has Turizm', '4448888', 'info@has.com'),
('Özkaymak', '4449999', 'info@ozkaymak.com'),
('Ben Turizm', '4440000', 'info@ben.com');

-- 3. Buses (Mevcut 6 var, 4+ tane daha ekleyelim)
INSERT INTO buses (company_id, plate_number, model, capacity, has_wifi, has_tv) VALUES
(1, '34 YOL 001', 'Mercedes Travego', 46, 1, 1),
(2, '16 BUR 002', 'MAN Neoplan', 50, 1, 0),
(3, '41 KOC 003', 'Temsa Safir', 46, 0, 1),
(4, '31 HAT 004', 'Mercedes Tourismo', 40, 1, 1),
(5, '42 KON 005', 'Setra', 54, 1, 1);

-- 4. Schedules (Mevcut ~10 var, biraz daha serpiştirelim)
INSERT INTO schedules (route_id, bus_id, driver_id, departure_time, arrival_time, price) VALUES
(1, 1, 3, DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY) + INTERVAL 5 HOUR, 450.00),
(2, 2, 4, DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY) + INTERVAL 5 HOUR, 450.00),
(3, 3, 3, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY) + INTERVAL 6 HOUR, 500.00),
(4, 4, 4, DATE_ADD(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY) + INTERVAL 6 HOUR, 500.00),
(5, 5, 3, DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY) + INTERVAL 2 HOUR, 200.00);

-- Generate seats for new buses
DELIMITER //
CREATE PROCEDURE generate_more_seats()
BEGIN
    DECLARE bus_id INT;
    DECLARE bus_capacity INT;
    DECLARE seat_num INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE bus_cursor CURSOR FOR SELECT id, capacity FROM buses WHERE id > 6; -- New buses
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN bus_cursor;
    
    bus_loop: LOOP
        FETCH bus_cursor INTO bus_id, bus_capacity;
        IF done THEN
            LEAVE bus_loop;
        END IF;
        
        SET seat_num = 1;
        WHILE seat_num <= bus_capacity DO
            INSERT IGNORE INTO seats (bus_id, seat_number, seat_type) 
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

CALL generate_more_seats();
DROP PROCEDURE generate_more_seats;

-- 5. Trip Requests (Dummy data for 10+)
INSERT INTO trip_requests (driver_id, route_id, bus_id, departure_time, status) VALUES
(3, 1, 1, DATE_ADD(NOW(), INTERVAL 10 DAY), 'pending'),
(4, 2, 2, DATE_ADD(NOW(), INTERVAL 11 DAY), 'pending'),
(3, 3, 3, DATE_ADD(NOW(), INTERVAL 12 DAY), 'approved'),
(4, 4, 4, DATE_ADD(NOW(), INTERVAL 13 DAY), 'rejected'),
(3, 5, 5, DATE_ADD(NOW(), INTERVAL 14 DAY), 'pending'),
(3, 1, 1, DATE_ADD(NOW(), INTERVAL 15 DAY), 'pending'),
(4, 2, 2, DATE_ADD(NOW(), INTERVAL 16 DAY), 'approved'),
(3, 3, 3, DATE_ADD(NOW(), INTERVAL 17 DAY), 'pending'),
(4, 4, 4, DATE_ADD(NOW(), INTERVAL 18 DAY), 'rejected'),
(3, 5, 5, DATE_ADD(NOW(), INTERVAL 19 DAY), 'pending');

-- 6. Bookings & Payments (Hızlıca birkaç rezervasyon ve ödeme ekleyelim)
-- Not: Bu manuel ekleme log triggerlarını tetikler.
-- Uygun schedule_id ve seat_id bulmak zor olduğu için dinamik yapmak daha iyi ama
-- basitçe bilinen ID'lere ekleyelim.
