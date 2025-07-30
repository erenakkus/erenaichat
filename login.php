<?php
// login.php - Giriş sayfası
session_start();
require_once 'config.php';
require_once 'auth.php';

// Kullanıcı zaten giriş yapmışsa sohbet sayfasına yönlendir
if (isLoggedIn()) {
    header("Location: chat.php");
    exit;
}

$error = "";

// Giriş formunun gönderilip gönderilmediğini kontrol etme
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    // Basit doğrulama
    if (empty($username) || empty($password)) {
        $error = "Lütfen kullanıcı adı ve şifre girin.";
    } else {
        // Kullanıcı girişi
        $result = login($username, $password);
        
        if ($result["success"]) {
            header("Location: chat.php");
            exit;
        } else {
            $error = $result["message"];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - AI Sohbet Asistanı</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="container">
        
        <div class="form-container">
            <h2>Giriş Yap</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" name="username" id="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
                </div>
            </form>
            
            <div class="form-footer">
                <p>Hesabınız yok mu? <a href="register.php">Kayıt Olun</a></p>
            </div>
        </div>
    </div>
</body>
</html>