<?php
// config.php - Veritabanı bağlantı bilgileri ve OpenAI ayarları

// OpenAI API ayarları
$config = [
    'openai_api_key' => 'sk-apikey',
    'openai_model' => 'gpt-4-turbo',
    'openai_max_tokens' => 4000,
    'openai_temperature' => 0.8
];

// Veritabanı sabitleri
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'databaseusername');
define('DB_PASSWORD', 'databasepassword');
define('DB_NAME', 'databasename');

// Veritabanı bağlantısı oluşturma
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

// Veritabanını oluşturma
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === FALSE) {
    die("Veritabanı oluşturma hatası: " . $conn->error);
}

$conn->select_db(DB_NAME);

// Kullanıcılar tablosunu oluşturma
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(100),
    UNIQUE KEY (verification_token)
)";
if ($conn->query($sql) === FALSE) {
    die("Kullanıcılar tablosu oluşturma hatası: " . $conn->error);
}

// Sohbetler tablosunu oluşturma
$sql = "CREATE TABLE IF NOT EXISTS conversations (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === FALSE) {
    die("Sohbetler tablosu oluşturma hatası: " . $conn->error);
}

// Mesajlar tablosunu oluşturma
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT(11) UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_user TINYINT(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === FALSE) {
    die("Mesajlar tablosu oluşturma hatası: " . $conn->error);
}

// Oturum başlatma
session_start();
?>
