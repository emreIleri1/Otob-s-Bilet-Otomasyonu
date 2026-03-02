<?php
/**
 * Veritabanı Bağlantı Ayarları
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'bus_ticket_system';
    private $username = 'root';
    private $password = 'mysql';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch(PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
        
        return $this->conn;
    }
}
