<?php
// auth.php - Kimlik doğrulama işlevleri
require_once 'config.php';

// Kullanıcı kaydı
function register($username, $email, $password) {
    global $conn;
    
    // Şifreyi güvenli bir şekilde hash'leme
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Doğrulama tokeni oluşturma
    $verification_token = bin2hex(random_bytes(32));
    
    // Kullanıcıyı veritabanına ekleme
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, verification_token) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $verification_token);
    
    if ($stmt->execute()) {
        // Doğrulama e-postası gönderme
        sendVerificationEmail($email, $verification_token);
        return true;
    } else {
        return false;
    }
}

// Doğrulama e-postası gönderme 
function sendVerificationEmail($email, $token) {
    $subject = "Hesap Doğrulama";
    $message = "Hesabınızı doğrulamak için aşağıdaki bağlantıya tıklayın:\n";
    $message .= "http://domain.com/verify.php?token=$token";
    $headers = "From: noreply@domain.com";
    
    mail($email, $subject, $message, $headers);
}

// Hesap doğrulama
function verifyAccount($token) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return true;
    } else {
        return false;
    }
}

// Kullanıcı girişi
function login($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, password, is_verified FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if ($user['is_verified'] == 0) {
            return ["success" => false, "message" => "Hesabınız henüz doğrulanmamış."];
        }
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            return ["success" => true, "user" => $user];
        } else {
            return ["success" => false, "message" => "Geçersiz şifre."];
        }
    } else {
        return ["success" => false, "message" => "Kullanıcı adı bulunamadı."];
    }
}

// Çıkış yapma
function logout() {
    // Oturum değişkenlerini temizleme
    $_SESSION = array();
    
    // Oturum tanımlama bilgisini yok etme
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Oturumu yok etme
    session_destroy();
}

// Kullanıcının giriş yapıp yapmadığını kontrol etme
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}
?>