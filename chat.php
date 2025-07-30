<?php
// chat.php - Sohbet sayfası
session_start();
require_once 'config.php';
require_once 'auth.php';
require_once 'openai.php';

// Kullanıcı giriş yapmamışsa giriş sayfasına yönlendir
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$active_conversation_id = null;

// AJAX mesaj işleme
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'send_message') {
    $response = ['success' => false];
    
    if (isset($_POST['message']) && !empty($_POST['message']) && isset($_POST['conversation_id'])) {
        $user_message = trim($_POST['message']);
        $conversation_id = (int)$_POST['conversation_id'];
        
        // Sohbetin kullanıcıya ait olup olmadığını kontrol etme
        $stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conversation_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Kullanıcı mesajını kaydetme
            $message_id = saveMessage($conversation_id, $user_message, 1);
            
            // Başarılı yanıt
            $response = [
                'success' => true,
                'message_id' => $message_id,
                'message' => $user_message,
                'time' => date("H:i")
            ];
            
            // Sohbet başlığını güncelleme
            updateConversationTitle($conversation_id, $user_message);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// AJAX AI yanıtı alma
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_ai_response') {
    $response = ['success' => false];
    
    if (isset($_POST['conversation_id'])) {
        $conversation_id = (int)$_POST['conversation_id'];
        
        // Sohbetin kullanıcıya ait olup olmadığını kontrol etme
        $stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conversation_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Sohbet geçmişini alma
            $conversation_history = getConversationHistory($conversation_id);
            
            // Son kullanıcı mesajını al
            $last_user_message = "";
            for ($i = count($conversation_history) - 1; $i >= 0; $i--) {
                if ($conversation_history[$i]['is_user'] == 1) {
                    $last_user_message = $conversation_history[$i]['content'];
                    break;
                }
            }
            
            // OpenAI API'ye mesaj gönderme
            $ai_response = sendMessage($last_user_message, $conversation_history);
            
            if (!isset($ai_response['error'])) {
                // AI yanıtını kaydetme
                $message_id = saveMessage($conversation_id, $ai_response['response'], 0);
                
                $response = [
                    'success' => true,
                    'message_id' => $message_id,
                    'message' => $ai_response['response'],
                    'time' => date("H:i")
                ];
            } else {
                $response['error'] = $ai_response['error'];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Dosya yükleme işlemi
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'upload_file') {
    $response = ['success' => false];
    
    if (isset($_FILES['file']) && isset($_POST['conversation_id'])) {
        $conversation_id = (int)$_POST['conversation_id'];
        
        // Sohbetin kullanıcıya ait olup olmadığını kontrol etme
        $stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conversation_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $upload_dir = 'uploads/';
            
            // Uploads dizini yoksa oluştur
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Benzersiz dosya adı oluştur
            $filename = uniqid() . '_' . basename($_FILES['file']['name']);
            $target_file = $upload_dir . $filename;
            
            // Dosyayı yükle
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                // Dosya bilgilerini veritabanına kaydet
                $file_path = $target_file;
                $file_name = $_FILES['file']['name'];
                $file_type = $_FILES['file']['type'];
                $file_size = $_FILES['file']['size'];
                
                $stmt = $conn->prepare("INSERT INTO attachments (conversation_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $conversation_id, $file_path, $file_name, $file_type, $file_size);
                
                if ($stmt->execute()) {
                    $attachment_id = $stmt->insert_id;
                    
                    // Dosya hakkında mesaj oluştur
                    $file_message = "Dosya yüklendi: " . $file_name;
                    $message_id = saveMessage($conversation_id, $file_message, 1, $attachment_id);
                    
                    $response = [
                        'success' => true,
                        'message_id' => $message_id,
                        'message' => $file_message,
                        'file_name' => $file_name,
                        'file_path' => $file_path,
                        'time' => date("H:i")
                    ];
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Tema değiştirme işlemi
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'toggle_theme') {
    if (isset($_SESSION['theme']) && $_SESSION['theme'] == 'dark') {
        $_SESSION['theme'] = 'light';
    } else {
        $_SESSION['theme'] = 'dark';
    }
    
    echo json_encode(['success' => true, 'theme' => $_SESSION['theme']]);
    exit;
}

// Yeni sohbet oluşturma
if (isset($_POST['new_conversation'])) {
    $title = "Yeni Sohbet " . date("Y-m-d H:i:s");
    $active_conversation_id = createConversation($user_id, $title);
    
    if (!$active_conversation_id) {
        $error = "Yeni sohbet oluşturulurken bir hata oluştu.";
    }
}
// Sohbet seçme
elseif (isset($_GET['conversation_id'])) {
    $active_conversation_id = (int)$_GET['conversation_id'];
    
    // Sohbetin kullanıcıya ait olup olmadığını kontrol etme
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $active_conversation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        $active_conversation_id = null;
    }
}

// Kullanıcının sohbetlerini alma
$conversations = getUserConversations($user_id);

// Aktif sohbet varsa mesajlarını al
$messages = [];
if ($active_conversation_id) {
    $messages = getConversationHistory($active_conversation_id);
}

// Tema kontrolü
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
$current_theme = $_SESSION['theme'];

// Sohbet başlığını içeriğe göre güncelleme fonksiyonu
function updateConversationTitle($conversation_id, $message) {
    global $conn;
    
    // Mevcut başlığı kontrol et
    $stmt = $conn->prepare("SELECT title FROM conversations WHERE id = ?");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversation = $result->fetch_assoc();
    
    // Eğer hala default başlıksa (Yeni Sohbet ile başlıyorsa) güncelle
    if (strpos($conversation['title'], 'Yeni Sohbet') === 0) {
        // Mesajın ilk 30 karakterini al
        $new_title = substr($message, 0, 30);
        if (strlen($message) > 30) {
            $new_title .= '...';
        }
        
        // Başlığı güncelle
        $stmt = $conn->prepare("UPDATE conversations SET title = ? WHERE id = ?");
        $stmt->bind_param("si", $new_title, $conversation_id);
        $stmt->execute();
    }
}

// saveMessage fonksiyonunu güncelle (attachment_id için)
function saveMessage($conversation_id, $content, $is_user, $attachment_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, content, is_user, attachment_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $conversation_id, $content, $is_user, $attachment_id);
    $stmt->execute();
    
    return $stmt->insert_id;
}
?>

<!DOCTYPE html>
<html lang="tr" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sohbet - EREN AI Sohbet Asistanı</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Dark mode stilleri */
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --sidebar-bg: #f5f5f5;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --primary-color: #4a6cf7;
            --secondary-color: #e6e6e6;
            --hover-color: #f0f0f0;
            --message-user-bg: #e1f5fe;
            --message-ai-bg: #f5f5f5;
        }
        
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --sidebar-bg: #1e1e1e;
            --card-bg: #2d2d2d;
            --border-color: #444444;
            --primary-color: #6d8eff;
            --secondary-color: #3a3a3a;
            --hover-color: #383838;
            --message-user-bg: #2c4356;
            --message-ai-bg: #2d2d2d;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .sidebar {
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
        }
        
        .conversation-item {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }
        
        .conversation-item:hover {
            background-color: var(--hover-color);
        }
        
        .message-container {
            background-color: var(--bg-color);
        }
        
        .user-message {
            background-color: var(--message-user-bg);
        }
        
        .ai-message {
            background-color: var(--message-ai-bg);
        }
        
        .message-input textarea {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }
        
        .btn {
            background-color: var(--primary-color);
        }
        
        .btn-outline {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Mobil için açılır menü */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1000;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 18px;
            cursor: pointer;
        }
        
        /* Dosya yükleme */
        .file-upload {
            display: flex;
            align-items: center;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .file-upload input {
            display: none;
        }
        
        .file-upload i {
            font-size: 20px;
            color: var(--primary-color);
        }
        
        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background-color: var(--card-bg);
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        
        .file-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .file-info .remove-file {
            color: red;
            cursor: pointer;
        }
        
        /* Tema değiştirme butonu */
        .theme-toggle {
            margin-right: 10px;
            cursor: pointer;
            color: var(--primary-color);
            font-size: 20px;
        }
        
        /* Responsive tasarım */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100%;
                width: 280px;
                z-index: 999;
                transition: left 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .chat-container {
                width: 100%;
                margin-left: 0;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 998;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Mobil menü toggle butonu -->
        <button class="mobile-menu-toggle" id="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Sohbetlerim</h2>
                <form method="post">
                    <button type="submit" name="new_conversation" class="btn btn-primary btn-block">Yeni Sohbet</button>
                </form>
            </div>
            
            <div class="conversation-list">
                <?php if (empty($conversations)): ?>
                    <div class="empty-state">
                        <p>Henüz sohbet bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="?conversation_id=<?php echo $conversation['id']; ?>" 
                           class="conversation-item <?php echo ($active_conversation_id == $conversation['id']) ? 'active' : ''; ?>">
                            <div class="conversation-title"><?php echo htmlspecialchars($conversation['title']); ?></div>
                            <div class="conversation-date"><?php echo date("d.m.Y H:i", strtotime($conversation['created_at'])); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <span><?php echo $_SESSION['username']; ?></span>
                </div>
                <a href="logout.php" class="btn btn-outline">Çıkış Yap</a>
            </div>
        </div>
        
        <div class="chat-container">
            <?php if ($active_conversation_id): ?>
                <div class="chat-header">
                    <div class="theme-toggle" id="theme-toggle">
                        <i class="fas <?php echo $current_theme == 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </div>
                </div>
                
                <div class="message-container" id="message-container">
                    <?php if (empty($messages)): ?>
                        <div class="welcome-message">
                            <h3>Yeni bir sohbete başladınız</h3>
                            <p>EREN AI asistanına bir soru sorun veya dosya yükleyerek analiz isteyin.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['is_user'] ? 'user-message' : 'ai-message'; ?>">
                                <div class="message-content">
                                    <?php 
                                    // Eğer mesajda dosya eki varsa göster
                                    if (!empty($message['attachment_id'])) {
                                        $attachment = getAttachmentById($message['attachment_id']);
                                        if ($attachment) {
                                            echo '<div class="file-attachment">';
                                            echo '<i class="fas fa-file"></i> ';
                                            echo '<a href="' . htmlspecialchars($attachment['file_path']) . '" target="_blank">';
                                            echo htmlspecialchars($attachment['file_name']);
                                            echo '</a>';
                                            echo ' (' . formatFileSize($attachment['file_size']) . ')';
                                            echo '</div>';
                                        }
                                    }
                                    
                                    echo nl2br(htmlspecialchars($message['content'])); 
                                    ?>
                                </div>
                                <div class="message-time">
                                    <?php echo date("H:i", strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="message-input">
                    <div id="file-preview" class="file-preview" style="display: none;">
                        <div class="file-info">
                            <span id="selected-file-name"></span>
                            <span class="remove-file" id="remove-file"><i class="fas fa-times"></i></span>
                        </div>
                    </div>
                    
                    <form id="message-form">
                        <input type="hidden" name="conversation_id" id="conversation_id" value="<?php echo $active_conversation_id; ?>">
                        <div class="input-row">
                            <label class="file-upload">
                                <input type="file" id="file-upload" name="file">
                                <i class="fas fa-paperclip"></i>
                            </label>
                            <textarea name="message" id="message-input" placeholder="Bir mesaj yazın veya dosya ekleyin..." required></textarea>
                            <button type="submit" class="btn btn-primary">Gönder</button>
                        </div>
                    </form>
                </div>
                
                <script>
                    // Mesaj alanına otomatik odaklanma
                    document.getElementById('message-input').focus();
                    
                    // Sayfayı mesajların en altına kaydırma
                    const messageContainer = document.getElementById('message-container');
                    messageContainer.scrollTop = messageContainer.scrollHeight;
                    
                    // Mesaj gönderme işlemi
                    document.getElementById('message-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const messageInput = document.getElementById('message-input');
                        const message = messageInput.value.trim();
                        const conversationId = document.getElementById('conversation_id').value;
                        const fileInput = document.getElementById('file-upload');
                        
                        // Dosya veya mesaj yoksa işlem yapma
                        if (message === '' && fileInput.files.length === 0) return;
                        
                        // Dosya gönderme işlemi
                        if (fileInput.files.length > 0) {
                            uploadFile(fileInput.files[0], conversationId);
                            // Dosya önizlemeyi temizle
                            document.getElementById('file-preview').style.display = 'none';
                            document.getElementById('selected-file-name').textContent = '';
                            fileInput.value = '';
                        }
                        
                        // Metin mesajı gönderme
                        if (message !== '') {
                            // Kullanıcı mesajını UI'a ekle
                            addMessageToUI(message, true);
                            
                            // Input alanını temizle
                            messageInput.value = '';
                            
                            // Mesajı AJAX ile gönder
                            sendMessageAJAX(message, conversationId);
                        }
                    });
                    
                    // Enter tuşu ile formu gönderme
                    document.getElementById('message-input').addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            document.getElementById('message-form').dispatchEvent(new Event('submit'));
                        }
                    });
                    
                    // Dosya yükleme önizleme
                    document.getElementById('file-upload').addEventListener('change', function(e) {
                        const fileInput = e.target;
                        if (fileInput.files.length > 0) {
                            const fileName = fileInput.files[0].name;
                            document.getElementById('selected-file-name').textContent = fileName;
                            document.getElementById('file-preview').style.display = 'block';
                        }
                    });
                    
                    // Dosya seçimini iptal etme
                    document.getElementById('remove-file').addEventListener('click', function() {
                        document.getElementById('file-upload').value = '';
                        document.getElementById('file-preview').style.display = 'none';
                    });
                    
                    // Tema değiştirme
                    document.getElementById('theme-toggle').addEventListener('click', function() {
                        const themeIcon = this.querySelector('i');
                        const formData = new FormData();
                        formData.append('ajax_action', 'toggle_theme');
                        
                        fetch('chat.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.documentElement.setAttribute('data-theme', data.theme);
                                // Tema ikonu değiştir
                                if (data.theme === 'dark') {
                                    themeIcon.classList.remove('fa-moon');
                                    themeIcon.classList.add('fa-sun');
                                } else {
                                    themeIcon.classList.remove('fa-sun');
                                    themeIcon.classList.add('fa-moon');
                                }
                            }
                        });
                    });
                    
                    // Mobil menü toggle
                    document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
                        document.getElementById('sidebar').classList.toggle('active');
                        document.getElementById('sidebar-overlay').classList.toggle('active');
                    });
                    
                    // Sidebar overlay tıklama
                    document.getElementById('sidebar-overlay').addEventListener('click', function() {
                        document.getElementById('sidebar').classList.remove('active');
                        document.getElementById('sidebar-overlay').classList.remove('active');
                    });
                    
                    // Mesajı UI'a ekleyen fonksiyon
                    function addMessageToUI(message, isUser) {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${isUser ? 'user-message' : 'ai-message'}`;
                        
                        const messageContent = document.createElement('div');
                        messageContent.className = 'message-content';
                        messageContent.innerHTML = message.replace(/\n/g, '<br>');
                        
                        const messageTime = document.createElement('div');
                        messageTime.className = 'message-time';
                        messageTime.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        messageDiv.appendChild(messageContent);
                        messageDiv.appendChild(messageTime);
                        
                        document.getElementById('message-container').appendChild(messageDiv);
                        
                        // Scroll to bottom
                        messageContainer.scrollTop = messageContainer.scrollHeight;
                        
                        return messageDiv;
                    }
                    
                    // Dosya yükleme fonksiyonu
                    function uploadFile(file, conversationId) {
                        const formData = new FormData();
                        formData.append('ajax_action', 'upload_file');
                        formData.append('file', file);
                        formData.append('conversation_id', conversationId);
                        
                        // Dosya yükleniyor mesajı
                        const loadingMsg = addMessageToUI('Dosya yükleniyor...', true);
                        
                        fetch('chat.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Yükleniyor mesajını kaldır
                            messageContainer.removeChild(loadingMsg);
                            
                            if (data.success) {
                                // Dosya mesajını göster
                                const fileMsg = `<div class="file-attachment">
                                    <i class="fas fa-file"></i> 
                                    <a href="${data.file_path}" target="_blank">${data.file_name}</a>
                                </div>
                                ${data.message}`;
                                
                                addMessageToUI(fileMsg, true);
                                
                                // AI yanıtını al
                                getAIResponseAJAX(conversationId);
                            } else {
                                addMessageToUI('Dosya yüklenirken bir hata oluştu.', true);
                            }
                        })
                        .catch(error => {
                            // Yükleniyor mesajını kaldır
                            messageContainer.removeChild(loadingMsg);
                            console.error('Dosya yükleme hatası:', error);
                            addMessageToUI('Dosya yüklenirken bir bağlantı hatası oluştu.', true);
                        });
                    }
                    
                    // AI yanıtını akıcı şekilde ekleyen fonksiyon
                    function streamAIResponse(message) {
                        const aiMessageDiv = addMessageToUI('', false);
                        const contentDiv = aiMessageDiv.querySelector('.message-content');
                        
                        let index = 0;
                        const characters = message.split('');
                        
                        // Cursor animasyonu için span
                        const cursor = document.createElement('span');
                        cursor.className = 'cursor';
                        cursor.textContent = '▋';
                        contentDiv.appendChild(cursor);
                        
                        // Karakterleri teker teker ekle
                        const interval = setInterval(() => {
                            if (index < characters.length) {
                                if (characters[index] === '\n') {
                                    contentDiv.insertBefore(document.createElement('br'), cursor);
                                } else {
                                    contentDiv.insertBefore(document.createTextNode(characters[index]), cursor);
                                }
                                index++;
                                messageContainer.scrollTop = messageContainer.scrollHeight;
                            } else {
                                clearInterval(interval);
                                contentDiv.removeChild(cursor);
                            }
                        }, 15); // Karakter gecikme hızı (ms)
                    }
                    
                    // AJAX ile mesaj gönderme
                    function sendMessageAJAX(message, conversationId) {
                        // Form verilerini oluştur
                        const formData = new FormData();
                        formData.append('ajax_action', 'send_message');
                        formData.append('message', message);
                        formData.append('conversation_id', conversationId);
                        
                        // Mesajı gönder
                        fetch('chat.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Mesaj başarıyla gönderildi, şimdi AI yanıtını al
                                getAIResponseAJAX(conversationId);
                            }
                        })
                        .catch(error => {
                            console.error('Mesaj gönderme hatası:', error);
                        });
                    }
                    
                    // AJAX ile AI yanıtını alma
                    function getAIResponseAJAX(conversationId) {
                        // AI yanıt yükleniyor göstergesi
                        const loadingDiv = addMessageToUI('<em>EREN AI yanıt yazıyor...</em>', false);
                        
                        // Form verilerini oluştur
                        const formData = new FormData();
                        formData.append('ajax_action', 'get_ai_response');
                        formData.append('conversation_id', conversationId);
                        
                        // AI yanıtını al
                        fetch('chat.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Yükleniyor mesajını kaldır
                            document.getElementById('message-container').removeChild(loadingDiv);
                            
                            if (data.success) {
                                // AI yanıtını akıcı şekilde göster
                                streamAIResponse(data.message);
                            } else {
                                // Hata durumunda
                                addMessageToUI('Üzgünüm, yanıt alınırken bir hata oluştu.', false);
                            }
                        })
                        .catch(error => {
                            // Yükleniyor mesajını kaldır
                            document.getElementById('message-container').removeChild(loadingDiv);
                            console.error('AI yanıtı alma hatası:', error);
                            addMessageToUI('Üzgünüm, bir bağlantı hatası oluştu.', false);
                        });
                    }
                    
                    // Stil için CSS ekleme
                    const style = document.createElement('style');
                    style.textContent = `
                        .cursor {
                            display: inline-block;
                            width: 3px;
                            background-color: currentColor;
                            animation: blink 1s infinite;
                            margin-left: 2px;
                            vertical-align: middle;
                        }
                        
                        @keyframes blink {
                            0%, 100% { opacity: 1; }
                            50% { opacity: 0; }
                        }
                        
                        .input-row {
                            display: flex;
                            align-items: center;
                        }
                        
                        .file-attachment {
                            padding: 8px;
                            margin-bottom: 8px;
                            background-color: var(--secondary-color);
                            border-radius: 5px;
                            display: inline-block;
                        }
                    `;
                    document.head.appendChild(style);
                </script>
            <?php else: ?>
                <div class="empty-chat-state">
                    <h2>Sohbete Başlayın</h2>
                    <p>Yeni bir sohbet başlatmak veya mevcut bir sohbeti seçmek için sol taraftaki menüyü kullanın.</p>
                    <form method="post">
                        <button type="submit" name="new_conversation" class="btn btn-primary">Yeni Sohbet Başlat</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Dosya boyutu formatlandırma fonksiyonu
    function formatFileSize($size) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    // Dosya ekini ID ile alma fonksiyonu
    function getAttachmentById($attachment_id) {
        global $conn;
        
        $stmt = $conn->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->bind_param("i", $attachment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    // Kullanıcının sohbetlerini alma fonksiyonu
function getUserConversations($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    return $conversations;
}

// Yeni sohbet oluşturma fonksiyonu
function createConversation($user_id, $title) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $title);
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    }
    
    return false;
}

// Sohbet geçmişini alma fonksiyonu
function getConversationHistory($conversation_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT m.*, a.id as attachment_id FROM messages m 
                          LEFT JOIN attachments a ON m.attachment_id = a.id 
                          WHERE m.conversation_id = ? 
                          ORDER BY m.created_at ASC");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    return $messages;
}
    ?>
</body>
</html>