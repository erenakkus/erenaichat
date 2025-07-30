<?php
// verify.php - Hesap doğrulama sayfası
session_start();
require_once 'config.php';
require_once 'auth.php';

$message = "";

// Token kontrolü
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    if (verifyAccount($token)) {
        $message = "Hesabınız başarıyla doğrulandı. Artık giriş yapabilirsiniz.";
    } else {
        $message = "Geçersiz veya süresi dolmuş doğrulama bağlantısı.";
    }
} else {
    $message = "Doğrulama tokeni eksik.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Doğrulama - EREN AI Sohbet Asistanı</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Hesap Doğrulama</h2>
            
            <div class="alert <?php echo strpos($message, "başarıyla") !== false ? "alert-success" : "alert-danger"; ?>">
                <?php echo $message; ?>
            </div>
            
            <div class="form-footer">
                <a href="login.php" class="btn btn-primary btn-block">Giriş Sayfasına Git</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// logout.php - Çıkış sayfası
session_start();
require_once 'auth.php';

// Çıkış yapma işlemi
logout();

// Ana sayfaya yönlendirme
header("Location: index.php");
exit;
?>