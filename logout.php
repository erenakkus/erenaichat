<?php
session_start(); // mevcut oturumu başlatır

session_destroy(); // oturumu yok eder

header('Location: login.php'); // kullanıcıyı giriş sayfasına yönlendirir
exit;
?>