<?php
/**
 * Web Installer for PT Data Lake Indonesia
 * Fast, Easy Server Deployment Wizard
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$lockFile = __DIR__ . '/config/installed.lock';
$configFile = __DIR__ . '/config/config.php';
$isInstalled = file_exists($lockFile);

// Check if unlocking is requested via secret or manual
if (isset($_GET['force_unlock']) && $_GET['force_unlock'] === '1' && file_exists($lockFile)) {
    @unlink($lockFile);
    $isInstalled = false;
}

// 1. AJAX Test DB Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_db') {
    header('Content-Type: application/json');
    $db_type = $_POST['db_type'] ?? 'sqlite';

    if ($db_type === 'sqlite') {
        if (!extension_loaded('pdo_sqlite')) {
            echo json_encode(['success' => false, 'message' => 'Ekstensi PHP pdo_sqlite belum aktif di server Anda.']);
            exit;
        }
        $db_dir = __DIR__ . '/database';
        if (!is_dir($db_dir)) {
            @mkdir($db_dir, 0755, true);
        }
        if (!is_writable($db_dir)) {
            echo json_encode(['success' => false, 'message' => 'Folder database/ tidak memiliki izin tulis (writable). Jalankan: chmod 775 database']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'SQLite siap digunakan! (Zero-config).']);
        exit;
    } else {
        if (!extension_loaded('pdo_mysql')) {
            echo json_encode(['success' => false, 'message' => 'Ekstensi PHP pdo_mysql belum aktif di server Anda.']);
            exit;
        }
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? 'datalake_db');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';

        try {
            // Test connecting to MySQL server first
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]);

            // Check if DB exists
            $stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($name));
            $exists = $stmt->fetch();

            if ($exists) {
                echo json_encode(['success' => true, 'message' => "Koneksi sukses! Database '$name' ditemukan dan siap dipakai."]);
            } else {
                echo json_encode(['success' => true, 'message' => "Koneksi MySQL sukses! Database '$name' belum ada dan akan dibuat otomatis saat instalasi."]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Koneksi MySQL Gagal: ' . $e->getMessage()]);
        }
        exit;
    }
}

// 2. Perform Full Installation
$installSuccess = false;
$errorMessage = '';

// Server Requirements Check
$req_php = version_compare(PHP_VERSION, '7.4.0', '>=');
$req_pdo = extension_loaded('pdo');
$req_sqlite = extension_loaded('pdo_sqlite');
$req_mysql = extension_loaded('pdo_mysql');
$config_writable = is_writable(__DIR__ . '/config') || (!file_exists(__DIR__ . '/config') && is_writable(__DIR__));
$database_writable = is_writable(__DIR__ . '/database') || (!file_exists(__DIR__ . '/database') && is_writable(__DIR__));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install']) && !$isInstalled) {
    $db_type = $_POST['db_type'] ?? 'sqlite';
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = trim($_POST['db_port'] ?? '3306');
    $db_name = trim($_POST['db_name'] ?? 'datalake_db');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';

    $admin_name = trim($_POST['admin_name'] ?? 'Administrator Data Lake');
    $admin_user = trim(strtolower($_POST['admin_user'] ?? 'admin'));
    $admin_email = trim(strtolower($_POST['admin_email'] ?? 'admin@datalake.id'));
    $admin_phone = trim($_POST['admin_phone'] ?? '08170117800');
    $admin_pass = $_POST['admin_pass'] ?? 'admin123';

    if (empty($admin_user) || empty($admin_pass) || empty($admin_email)) {
        $errorMessage = 'Harap isi informasi Akun Administrator dengan lengkap.';
    } else {
        try {
            $pdo = null;

            if ($db_type === 'sqlite') {
                $db_dir = __DIR__ . '/database';
                if (!is_dir($db_dir)) {
                    mkdir($db_dir, 0755, true);
                }
                $sqlite_file = $db_dir . '/datalake.sqlite';
                $pdo = new PDO("sqlite:" . $sqlite_file);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec("PRAGMA foreign_keys = ON;");

                // Create SQLite tables
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        username TEXT UNIQUE NOT NULL,
                        email TEXT UNIQUE NOT NULL,
                        phone TEXT,
                        password TEXT NOT NULL,
                        role TEXT DEFAULT 'user',
                        status TEXT DEFAULT 'active',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_login DATETIME
                    );

                    CREATE TABLE IF NOT EXISTS inquiries (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL,
                        phone TEXT,
                        service_package TEXT,
                        location TEXT,
                        message TEXT,
                        status TEXT DEFAULT 'new',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS activity_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER,
                        action TEXT NOT NULL,
                        details TEXT,
                        ip_address TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS site_settings (
                        key TEXT PRIMARY KEY,
                        val TEXT
                    );

                    CREATE TABLE IF NOT EXISTS starlink_kits (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        kit_number TEXT NOT NULL,
                        model TEXT DEFAULT 'Starlink Standard Gen 3 V4',
                        plan_name TEXT DEFAULT 'Dedicated Business PKS Enterprise',
                        location TEXT DEFAULT 'Terminal Operasional',
                        status TEXT DEFAULT 'online',
                        ip_address TEXT DEFAULT '100.64.12.81',
                        sla_percent REAL DEFAULT 99.98,
                        download_speed INTEGER DEFAULT 285,
                        upload_speed INTEGER DEFAULT 45,
                        ping_ms INTEGER DEFAULT 24,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");
            } else {
                // MySQL
                $pdo_server = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdo_server->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Create MySQL tables
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        email VARCHAR(100) UNIQUE NOT NULL,
                        phone VARCHAR(30),
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(20) DEFAULT 'user',
                        status VARCHAR(20) DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        last_login TIMESTAMP NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS inquiries (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        email VARCHAR(100) NOT NULL,
                        phone VARCHAR(30),
                        service_package VARCHAR(100),
                        location VARCHAR(150),
                        message TEXT,
                        status VARCHAR(20) DEFAULT 'new',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        action VARCHAR(100) NOT NULL,
                        details TEXT,
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS site_settings (
                        `key` VARCHAR(50) PRIMARY KEY,
                        `val` TEXT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS starlink_kits (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        kit_number VARCHAR(30) NOT NULL,
                        model VARCHAR(100) DEFAULT 'Starlink Standard Gen 3 V4',
                        plan_name VARCHAR(100) DEFAULT 'Dedicated Business PKS Enterprise',
                        location VARCHAR(150) DEFAULT 'Terminal Operasional',
                        status VARCHAR(20) DEFAULT 'online',
                        ip_address VARCHAR(50) DEFAULT '100.64.12.81',
                        sla_percent DECIMAL(5,2) DEFAULT 99.98,
                        download_speed INT DEFAULT 285,
                        upload_speed INT DEFAULT 45,
                        ping_ms INT DEFAULT 24,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }

            // Insert / Upsert Admin Account
            $hashed_pass = password_hash($admin_pass, PASSWORD_BCRYPT);
            
            // Delete existing same username to avoid duplicate error
            $del = $pdo->prepare("DELETE FROM users WHERE username = ? OR email = ?");
            $del->execute([$admin_user, $admin_email]);

            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $timeSyntax = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            $ins = $pdo->prepare("
                INSERT INTO users (name, username, email, phone, password, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'admin', 'active', $timeSyntax)
            ");
            $ins->execute([$admin_name, $admin_user, $admin_email, $admin_phone, $hashed_pass]);

            // Seed sample inquiries if empty
            // Seed site_settings (WhatsApp & Store Title)
            if ($driver === 'sqlite') {
                $seedSettings = $pdo->prepare("INSERT OR REPLACE INTO site_settings (key, val) VALUES (?, ?)");
            } else {
                $seedSettings = $pdo->prepare("INSERT INTO site_settings (`key`, `val`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `val` = VALUES(`val`)");
            }
            $seedSettings->execute(['wa_number', !empty($admin_phone) ? $admin_phone : '08170117800']);
            $seedSettings->execute(['store_title', 'Data Lake Official Store']);


            // Write config/config.php
            $config_dir = __DIR__ . '/config';
            if (!is_dir($config_dir)) {
                mkdir($config_dir, 0755, true);
            }

            $configContent = "<?php\n";
            $configContent .= "/**\n * Configuration File generated by install.php\n * PT Data Lake Indonesia\n */\n\n";
            $configContent .= "define('DB_TYPE', " . var_export($db_type, true) . ");\n";
            $configContent .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
            $configContent .= "define('DB_PORT', " . var_export($db_port, true) . ");\n";
            $configContent .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
            $configContent .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
            $configContent .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
            $configContent .= "define('INSTALLED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n";

            file_put_contents($configFile, $configContent);

            // Create lock file
            file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s') . "\nAdmin: " . $admin_user);

            $installSuccess = true;
            $isInstalled = true;

        } catch (Exception $e) {
            $errorMessage = 'Instalasi Gagal: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Installer Server | Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <!-- Fonts -->
    <link rel="preload" href="/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Base Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/custom-auth.css">

    <style>
        .install-container {
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
        }
        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(0, 210, 255, 0.15);
            color: var(--color-tertiary);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .req-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: var(--space-6);
        }
        .req-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 13.5px;
        }
        .req-item:last-child {
            border-bottom: none;
        }
        .req-status-pass {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #4ade80;
            font-weight: 600;
        }
        .req-status-fail {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #f87171;
            font-weight: 600;
        }
        .db-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        .db-tab-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s;
        }
        .db-tab-btn:hover {
            border-color: rgba(0, 210, 255, 0.5);
            background: rgba(255, 255, 255, 0.1);
        }
        .db-tab-btn.active {
            border-color: var(--color-tertiary);
            background: rgba(0, 210, 255, 0.12);
            color: #ffffff;
        }
        .db-tab-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .db-tab-desc {
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.4;
        }
        .test-db-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--color-tertiary);
            border: 1px solid rgba(0, 210, 255, 0.4);
            border-radius: var(--radius-md);
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .test-db-btn:hover {
            background: rgba(0, 210, 255, 0.2);
        }
        .section-separator {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-tertiary);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding-bottom: 8px;
            margin-top: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body class="auth-page">

    <div class="install-container">
        <div class="auth-card" style="max-width: 100%;">
            
            <div class="auth-header">
                <div class="auth-logo">
                    <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia">
                </div>
                <div class="step-badge">
                    <span class="material-symbols-outlined" style="font-size: 16px;">terminal</span>
                    Server Deployment Wizard
                </div>
                <h1 class="auth-title">Instalasi Web PT Data Lake Indonesia</h1>
                <p class="auth-sub">Pasang dan konfigurasikan sistem portal pelanggan & admin Starlink ke server hosting Anda dengan mudah</p>
            </div>

            <?php if ($isInstalled && !$installSuccess): ?>
                <!-- ALREADY INSTALLED VIEW -->
                <div style="text-align: center; padding: 20px 0;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(0, 210, 255, 0.2); color: var(--color-tertiary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                        <span class="material-symbols-outlined" style="font-size: 36px;">lock</span>
                    </div>
                    <h2 style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 8px;">Aplikasi Sudah Terpasang!</h2>
                    <p style="color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.6; max-width: 480px; margin: 0 auto 24px;">
                        Wizard instalasi telah dikunci secara otomatis demi keamanan server. Anda dapat langsung masuk ke dasbor administrator atau beranda utama.
                    </p>

                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <a href="login.php" class="btn btn-primary" style="padding: 10px 24px; border-radius: 999px;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">login</span>
                            Masuk ke Admin
                        </a>
                        <a href="/" class="btn btn-inverted" style="padding: 10px 24px; border-radius: 999px;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">public</span>
                            Buka Beranda Web
                        </a>
                    </div>

                    <div style="margin-top: 32px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; font-size: 12px; color: rgba(255,255,255,0.5);">
                        Ingin menginstal ulang? Hapus file <code>config/installed.lock</code> atau <a href="install.php?force_unlock=1" class="auth-link" style="font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin membuka kunci instalasi?');">Klik di sini untuk buka kunci</a>.
                    </div>
                </div>

            <?php elseif ($installSuccess): ?>
                <!-- INSTALLATION COMPLETED SUCCESSFULLY -->
                <div style="text-align: center; padding: 20px 0;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(74, 222, 128, 0.2); color: #4ade80; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <span class="material-symbols-outlined" style="font-size: 42px;">verified</span>
                    </div>
                    <h2 style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 8px;">Instalasi Berhasil Selesai!</h2>
                    <p style="color: rgba(255,255,255,0.8); font-size: 14.5px; line-height: 1.6; max-width: 500px; margin: 0 auto 24px;">
                        Database, tabel, akun administrator, dan konfigurasi server telah berhasil disiapkan dan siap digunakan.
                    </p>

                    <div class="demo-credentials-box" style="max-width: 420px; margin: 0 auto 24px; text-align: left;">
                        <div class="demo-title">
                            <span class="material-symbols-outlined" style="font-size: 16px;">key</span>
                            Kredensial Login Administrator Anda:
                        </div>
                        <div style="margin-top: 6px;">Username: <strong><?= e($admin_user) ?></strong></div>
                        <div style="margin-top: 4px;">Password: <em>(Sesuai yang Anda masukkan)</em></div>
                        <div style="margin-top: 4px;">Database: <strong><?= strtoupper(e($db_type)) ?></strong></div>
                    </div>

                    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <a href="login.php" class="btn btn-primary" style="padding: 12px 28px; border-radius: 999px;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
                            Buka Dasbor Admin Sekarang
                        </a>
                        <a href="/" class="btn btn-inverted" style="padding: 12px 28px; border-radius: 999px;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">public</span>
                            Lihat Website
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- INSTALLATION FORM WIZARD -->

                <?php if ($errorMessage): ?>
                    <div class="alert alert-error">
                        <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                        <span><?= e($errorMessage) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Server Requirements Check Box -->
                <div class="req-card">
                    <div style="font-size: 12.5px; font-weight: 700; color: rgba(255,255,255,0.9); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-tertiary);">fact_check</span>
                        Pemeriksaan Kompatibilitas Server:
                    </div>
                    <div class="req-item">
                        <span>Versi PHP (Min. 7.4.0)</span>
                        <?php if ($req_php): ?>
                            <span class="req-status-pass"><span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span> PHP <?= PHP_VERSION ?></span>
                        <?php else: ?>
                            <span class="req-status-fail"><span class="material-symbols-outlined" style="font-size: 16px;">cancel</span> PHP <?= PHP_VERSION ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="req-item">
                        <span>PDO Extension</span>
                        <?php if ($req_pdo): ?>
                            <span class="req-status-pass"><span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span> Aktif</span>
                        <?php else: ?>
                            <span class="req-status-fail"><span class="material-symbols-outlined" style="font-size: 16px;">cancel</span> Tidak Aktif</span>
                        <?php endif; ?>
                    </div>
                    <div class="req-item">
                        <span>Database Driver (SQLite / MySQL)</span>
                        <?php if ($req_sqlite || $req_mysql): ?>
                            <span class="req-status-pass">
                                <span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span>
                                <?= ($req_sqlite ? 'SQLite ' : '') . ($req_mysql ? 'MySQL' : '') ?> Siap
                            </span>
                        <?php else: ?>
                            <span class="req-status-fail"><span class="material-symbols-outlined" style="font-size: 16px;">cancel</span> Driver DB Hilang</span>
                        <?php endif; ?>
                    </div>
                    <div class="req-item">
                        <span>Izin Tulis Folder <code>config/</code> & <code>database/</code></span>
                        <?php if ($config_writable && $database_writable): ?>
                            <span class="req-status-pass"><span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span> Writable (Bisa Ditulis)</span>
                        <?php else: ?>
                            <span class="req-status-fail"><span class="material-symbols-outlined" style="font-size: 16px;">cancel</span> Perlu chmod 775</span>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="install.php" method="POST" id="installForm" class="auth-form">
                    <input type="hidden" name="run_install" value="1">
                    <input type="hidden" name="db_type" id="db_type_input" value="sqlite">

                    <!-- Section: Database Type Selection -->
                    <div class="section-separator">
                        <span class="material-symbols-outlined" style="font-size: 18px;">database</span>
                        1. Pilihan Mesin Database
                    </div>

                    <div class="db-selector">
                        <div class="db-tab-btn active" id="tab_sqlite" onclick="selectDbType('sqlite')">
                            <div class="db-tab-title">
                                <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-tertiary);">bolt</span>
                                SQLite (1-Klik Cepat)
                            </div>
                            <div class="db-tab-desc">
                                Paling mudah! Tanpa perlu buat database manual di cPanel. Otomatis dibuat di file lokal.
                            </div>
                        </div>

                        <div class="db-tab-btn" id="tab_mysql" onclick="selectDbType('mysql')">
                            <div class="db-tab-title">
                                <span class="material-symbols-outlined" style="font-size: 18px; color: #60a5fa;">dns</span>
                                MySQL / MariaDB
                            </div>
                            <div class="db-tab-desc">
                                Cocok untuk hosting cPanel, phpMyAdmin, XAMPP, atau database server terpisah.
                            </div>
                        </div>
                    </div>

                    <!-- MySQL Config Panel (Hidden by default) -->
                    <div id="mysql_fields" style="display: none; background: rgba(0,0,0,0.2); padding: 16px; border-radius: var(--radius-md); margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.08);">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">Database Host</label>
                                <input type="text" name="db_host" id="db_host" class="form-input" value="127.0.0.1" placeholder="localhost">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Port</label>
                                <input type="text" name="db_port" id="db_port" class="form-input" value="3306" placeholder="3306">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label">Nama Database</label>
                            <input type="text" name="db_name" id="db_name" class="form-input" value="datalake_db" placeholder="datalake_db">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                            <div class="form-group">
                                <label class="form-label">Username DB</label>
                                <input type="text" name="db_user" id="db_user" class="form-input" value="root" placeholder="root">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password DB</label>
                                <input type="password" name="db_pass" id="db_pass" class="form-input" placeholder="(Kosongkan jika tidak ada)">
                            </div>
                        </div>

                        <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between;">
                            <button type="button" class="test-db-btn" onclick="testDbConnection()">
                                <span class="material-symbols-outlined" style="font-size: 16px;">cable</span>
                                Tes Koneksi Database
                            </button>
                            <span id="test_db_status" style="font-size: 12px; font-weight: 600;"></span>
                        </div>
                    </div>

                    <!-- Section: Admin Account Setup -->
                    <div class="section-separator">
                        <span class="material-symbols-outlined" style="font-size: 18px;">admin_panel_settings</span>
                        2. Akun Administrator
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap Administrator *</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined input-icon">badge</span>
                            <input type="text" name="admin_name" class="form-input" value="Administrator Data Lake" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Username Admin *</label>
                            <div class="input-wrapper">
                                <span class="material-symbols-outlined input-icon">person</span>
                                <input type="text" name="admin_user" class="form-input" value="admin" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. Telepon / WhatsApp</label>
                            <div class="input-wrapper">
                                <span class="material-symbols-outlined input-icon">call</span>
                                <input type="tel" name="admin_phone" class="form-input" value="08170117800">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Administrator *</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined input-icon">mail</span>
                            <input type="email" name="admin_email" class="form-input" value="admin@datalake.id" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kata Sandi Administrator *</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined input-icon">lock</span>
                            <input type="password" name="admin_pass" class="form-input" value="admin123" required>
                        </div>
                        <small style="color: rgba(255,255,255,0.5); font-size: 11.5px; margin-top: 4px;">
                            Bisa diganti atau gunakan default: <code>admin123</code>
                        </small>
                    </div>

                    <button type="submit" class="btn-auth-submit" style="margin-top: 20px;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">rocket_launch</span>
                        Pasang &amp; Jalankan Sekarang
                    </button>
                </form>

            <?php endif; ?>

            <div class="auth-footer">
                &copy; <?= date('Y') ?> PT Data Lake Indonesia &bull; Authorized Distributor Starlink
            </div>
        </div>
    </div>

    <script>
    function selectDbType(type) {
        document.getElementById('db_type_input').value = type;
        const tabSqlite = document.getElementById('tab_sqlite');
        const tabMysql = document.getElementById('tab_mysql');
        const mysqlFields = document.getElementById('mysql_fields');

        if (type === 'sqlite') {
            tabSqlite.classList.add('active');
            tabMysql.classList.remove('active');
            mysqlFields.style.display = 'none';
        } else {
            tabMysql.classList.add('active');
            tabSqlite.classList.remove('active');
            mysqlFields.style.display = 'block';
        }
    }

    function testDbConnection() {
        const statusSpan = document.getElementById('test_db_status');
        statusSpan.style.color = '#00D2FF';
        statusSpan.innerText = 'Menguji koneksi...';

        const formData = new FormData();
        formData.append('ajax_action', 'test_db');
        formData.append('db_type', 'mysql');
        formData.append('db_host', document.getElementById('db_host').value);
        formData.append('db_port', document.getElementById('db_port').value);
        formData.append('db_name', document.getElementById('db_name').value);
        formData.append('db_user', document.getElementById('db_user').value);
        formData.append('db_pass', document.getElementById('db_pass').value);

        fetch('install.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusSpan.style.color = '#4ade80';
                statusSpan.innerText = '✓ ' + data.message;
            } else {
                statusSpan.style.color = '#f87171';
                statusSpan.innerText = '✗ ' + data.message;
            }
        })
        .catch(err => {
            statusSpan.style.color = '#f87171';
            statusSpan.innerText = '✗ Gagal menguji: ' + err.message;
        });
    }
    </script>
</body>
</html>
