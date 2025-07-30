<?php
// Veritabanı bağlantısı
$host = 'localhost';
$db_name = 'databasename';
$username = 'databaseusername';
$password = 'password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Session başlat
session_start();

// Admin olmayan kullanıcıları engelle
function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}
?>