<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <h2>Admin Panel</h2>
            </div>
            
            <nav>
                <ul>
                    <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <span class="icon">📊</span> Ana Pano
                    </a></li>
                    <li><a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <span class="icon">👥</span> Kullanıcılar
                    </a></li>
                    <li><a href="chats.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'chats.php' ? 'active' : ''; ?>">
                        <span class="icon">💬</span> Sohbetler
                    </a></li>
                    <li><a href="uploads.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'uploads.php' ? 'active' : ''; ?>">
                        <span class="icon">📁</span> Yüklemeler
                    </a></li>
                    <li><a href="statistics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>">
                        <span class="icon">📈</span> İstatistikler
                    </a></li>
                    <li><a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <span class="icon">⚙️</span> Ayarlar
                    </a></li>
                </ul>
            </nav>
            
            <div class="user-info">
                <div class="avatar">
                    <span><?php echo substr($_SESSION['username'], 0, 1); ?></span>
                </div>
                <div class="user-details">
                    <p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <a href="logout.php">Çıkış Yap</a>
                </div>
            </div>
        </aside>
        
        <main class="content">
            <header class="top-header">
                <div class="menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <div class="search">
                    <input type="text" placeholder="Arama yap...">
                </div>
                
                <div class="notifications">
                    <span class="icon">🔔</span>
                    <span class="badge">5</span>
                </div>
            </header>