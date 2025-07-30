<?php
// index.php - Ana sayfa
session_start();
require_once 'config.php';
require_once 'auth.php';

// Oturum kontrolü
if (isLoggedIn()) {
    header("Location: chat.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EREN AI Sohbet Asistanı</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <h1>EREN AI Sohbet Asistanına Hoş Geldiniz</h1>
            <p>Yapay zeka ile sohbet etmenin kolay yolu.</p>
            <div class="button-group">
                <a href="login.php" class="btn btn-primary">Giriş Yap</a>
                <a href="register.php" class="btn btn-secondary">Kayıt Ol</a>
            </div>
        </div>
    </div>
</body>
</html>