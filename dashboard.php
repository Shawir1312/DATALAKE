<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = get_db_connection();
$current_user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Fetch current user details
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$current_user_id]);
$current_user = $userStmt->fetch();

if (!$current_user) {
    header("Location: logout.php");
    exit;
}

// User-specific Starlink Kits and PKS Billing History
$user_kits = get_user_starlink_kits($current_user_id);
$billing_history = get_pks_billing_history();

// Distinct dynamic base telemetry for each of the 3 Starlink kits (180 - 300 Mbps)
$kit_profiles = [
    ['min' => 268, 'max' => 298, 'up_min' => 45, 'up_max' => 52, 'ping_min' => 20, 'ping_max' => 24, 'jitter' => '1.4 ms'], // Terminal A
    ['min' => 192, 'max' => 236, 'up_min' => 34, 'up_max' => 40, 'ping_min' => 26, 'ping_max' => 32, 'jitter' => '2.1 ms'], // Terminal B
    ['min' => 238, 'max' => 278, 'up_min' => 39, 'up_max' => 46, 'ping_min' => 23, 'ping_max' => 29, 'jitter' => '1.8 ms'], // Terminal C
];

foreach ($user_kits as $idx => &$k) {
    $prof = $kit_profiles[$idx % count($kit_profiles)];
    $k['live_down'] = number_format($prof['min'] + (mt_rand(0, 100) / 100) * ($prof['max'] - $prof['min']), 1);
    $k['live_up'] = number_format($prof['up_min'] + (mt_rand(0, 100) / 100) * ($prof['up_max'] - $prof['up_min']), 1);
    $k['live_ping'] = mt_rand($prof['ping_min'], $prof['ping_max']);
    $k['live_jitter'] = $prof['jitter'];
}
unset($k);

$avg_live_down = !empty($user_kits) ? number_format(array_sum(array_column($user_kits, 'live_down')) / count($user_kits), 1) : '265.4';
$avg_live_up = !empty($user_kits) ? number_format(array_sum(array_column($user_kits, 'live_up')) / count($user_kits), 1) : '43.6';
$avg_live_ping = !empty($user_kits) ? round(array_sum(array_column($user_kits, 'live_ping')) / count($user_kits)) : '24';

// Determine tab
$tab = $_GET['tab'] ?? ($is_admin ? 'overview' : 'monitoring');
$error = get_flash('error');
$success = get_flash('success');

// Non-admin can only access client-specific tabs
if (!$is_admin && !in_array($tab, ['monitoring', 'telemetry', 'billing', 'pks', 'profile'])) {
    $tab = 'monitoring';
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash('error', 'Token keamanan CSRF tidak valid.');
        header("Location: dashboard.php?tab=" . urlencode($tab));
        exit;
    }

    // Admin-only: Add User
    if ($action === 'add_user') {
        if (!$is_admin) {
            set_flash('error', 'Hanya administrator yang dapat menambah pengguna.');
        } else {
            $name = trim($_POST['name'] ?? '');
            $username = trim(strtolower($_POST['username'] ?? ''));
            $email = trim(strtolower($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

            if (empty($name) || empty($username) || empty($email) || empty($password)) {
                set_flash('error', 'Semua kolom wajib harus diisi.');
            } elseif (strlen($password) < 6) {
                set_flash('error', 'Kata sandi minimal 6 karakter.');
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                if ($check->fetch()) {
                    set_flash('error', 'Username atau email tersebut sudah digunakan.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $time = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
                    $insert = $pdo->prepare("INSERT INTO users (name, username, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', $time)");
                    $insert->execute([$name, $username, $email, $phone, $hashed, $role]);
                    
                    $new_id = (int)$pdo->lastInsertId();
                    get_user_starlink_kits($new_id); // Initialize 3 Starlink kits for the new user

                    log_activity('Admin Add User', "Menambahkan user baru: $username ($role)");
                    set_flash('success', "Pengguna '$username' berhasil ditambahkan dan 3 Kit Starlink Dedicated telah dialokasikan.");
                }
            }
        }
        header("Location: dashboard.php?tab=users");
        exit;
    }

    // Admin-only: Delete User
    if ($action === 'delete_user') {
        if (!$is_admin) {
            set_flash('error', 'Hanya administrator yang dapat menghapus pengguna.');
        } else {
            $target_id = (int)($_POST['target_id'] ?? 0);
            if ($target_id === $current_user_id) {
                set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
            } else {
                $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $del->execute([$target_id]);
                log_activity('Admin Delete User', "Menghapus user ID: $target_id");
                set_flash('success', 'Pengguna berhasil dihapus.');
            }
        }
        header("Location: dashboard.php?tab=users");
        exit;
    }

    // Admin-only: Toggle User Status
    if ($action === 'toggle_status') {
        if (!$is_admin) {
            set_flash('error', 'Akses ditolak.');
        } else {
            $target_id = (int)($_POST['target_id'] ?? 0);
            $new_status = ($_POST['status'] === 'active') ? 'inactive' : 'active';
            if ($target_id === $current_user_id) {
                set_flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $target_id]);
                log_activity('Admin Update Status', "Mengubah status user ID $target_id menjadi $new_status");
                set_flash('success', "Status pengguna berhasil diubah menjadi $new_status.");
            }
        }
        header("Location: dashboard.php?tab=users");
        exit;
    }

    // Admin-only: Update Inquiry Status
    if ($action === 'update_inquiry') {
        if (!$is_admin) {
            set_flash('error', 'Akses ditolak.');
        } else {
            $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
            $new_status = $_POST['status'] ?? 'new';
            $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $inquiry_id]);
            log_activity('Update Inquiry', "Status inquiry #$inquiry_id diubah ke $new_status");
            set_flash('success', "Status permohonan berhasil diperbarui.");
        }
        header("Location: dashboard.php?tab=inquiries");
        exit;
    }

    // Update Profile
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            set_flash('error', 'Nama dan email tidak boleh kosong.');
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $current_user_id]);
            if ($check->fetch()) {
                set_flash('error', 'Alamat email sudah digunakan oleh pengguna lain.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $current_user_id]);
                $_SESSION['name'] = $name;
                $_SESSION['user_email'] = $email;
                log_activity('Update Profile', 'Memperbarui profil pengguna');
                set_flash('success', 'Profil Anda berhasil diperbarui.');
            }
        }
        header("Location: dashboard.php?tab=profile");
        exit;
    }

    // Update Password
    if ($action === 'update_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_pass, $current_user['password'])) {
            set_flash('error', 'Kata sandi saat ini tidak cocok.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('error', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('error', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $current_user_id]);
            log_activity('Update Password', 'Mengubah kata sandi akun');
            set_flash('success', 'Kata sandi berhasil diubah.');
        }
        header("Location: dashboard.php?tab=profile");
        exit;
    }

    // Admin-only: Update WhatsApp and Store Settings
    if ($action === 'update_contact_settings') {
        if (!$is_admin) {
            set_flash('error', 'Akses ditolak.');
        } else {
            $wa_number = trim($_POST['wa_number'] ?? '');
            $site_title = trim($_POST['site_title'] ?? '');
            if (!empty($wa_number)) {
                set_site_setting('wa_number', $wa_number);
            }
            if (!empty($site_title)) {
                set_site_setting('site_title', $site_title);
            }
            log_activity('Update Store Settings', "Mengubah kontak WhatsApp toko ke $wa_number");
            set_flash('success', 'Pengaturan kontak WhatsApp & Store berhasil disimpan!');
        }
        header("Location: dashboard.php?tab=profile");
        exit;
    }
}

// Fetch stats for admin
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalInquiries = (int)$pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
$newInquiries = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

// Fetch users list
$usersList = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();

// Fetch inquiries
$inquiriesList = $pdo->query("SELECT * FROM inquiries ORDER BY id DESC")->fetchAll();

// Fetch activity logs
$logsList = $pdo->query("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_admin ? 'Admin Dashboard' : 'Portal Klien Dedicated PKS' ?> | PT Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <!-- Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>">
</head>
<body class="dashboard-body">

    <!-- Sidebar Navigation -->
    <aside class="dash-sidebar" id="sidebar">
        <div class="dash-brand">
            <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="dash-brand-logo">
            <div class="dash-brand-text"><?= $is_admin ? 'NOC Admin Panel' : 'Portal Klien PKS' ?></div>
        </div>

        <div class="dash-user-profile">
            <div class="dash-avatar">
                <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
            </div>
            <div class="dash-user-info">
                <div class="dash-user-name"><?= e($current_user['name']) ?></div>
                <span class="dash-role-badge" style="<?= !$is_admin ? 'background: rgba(0, 210, 255, 0.2); color: #00D2FF; border: 1px solid #00D2FF;' : '' ?>">
                    <?= $is_admin ? 'ADMINISTRATOR' : 'KLIEN DEDICATED PKS' ?>
                </span>
            </div>
        </div>

        <nav class="dash-nav">
            <?php if ($is_admin): ?>
                <div class="dash-nav-header">Menu Administrator</div>
                <a href="dashboard.php?tab=overview" class="dash-nav-link <?= $tab === 'overview' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    Ringkasan Eksekutif
                </a>
                <a href="dashboard.php?tab=users" class="dash-nav-link <?= $tab === 'users' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">group</span>
                    Kelola Pengguna
                    <span class="badge"><?= $totalUsers ?></span>
                </a>
                <a href="dashboard.php?tab=inquiries" class="dash-nav-link <?= $tab === 'inquiries' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">contact_support</span>
                    Leads &amp; Konsultasi
                    <?php if ($newInquiries > 0): ?>
                        <span class="badge"><?= $newInquiries ?> baru</span>
                    <?php endif; ?>
                </a>

                <div class="dash-nav-header" style="margin-top: 14px;">Preview Klien PKS</div>
                <a href="dashboard.php?tab=monitoring" class="dash-nav-link <?= $tab === 'monitoring' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">satellite_alt</span>
                    Monitoring 3 Kit PKS
                </a>
                <a href="dashboard.php?tab=telemetry" class="dash-nav-link <?= $tab === 'telemetry' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">speed</span>
                    Statistik &amp; SLA Live
                </a>
                <a href="dashboard.php?tab=billing" class="dash-nav-link <?= $tab === 'billing' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">receipt_long</span>
                    Tagihan PKS 9 Jt
                    <span class="badge" style="background:#10b981;">Lunas</span>
                </a>
            <?php else: ?>
                <div class="dash-nav-header">Layanan PKS Dedicated</div>
                <a href="dashboard.php?tab=monitoring" class="dash-nav-link <?= $tab === 'monitoring' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">satellite_alt</span>
                    Status 3 Kit Starlink
                    <span class="badge" style="background: #10b981;">3 Aktif</span>
                </a>
                <a href="dashboard.php?tab=telemetry" class="dash-nav-link <?= $tab === 'telemetry' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">speed</span>
                    Statistik &amp; SLA Live
                </a>
                <a href="dashboard.php?tab=billing" class="dash-nav-link <?= $tab === 'billing' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">receipt_long</span>
                    Tagihan &amp; Invoice PKS
                    <span class="badge" style="background: #10b981;">Lunas</span>
                </a>
                <a href="dashboard.php?tab=pks" class="dash-nav-link <?= $tab === 'pks' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">assignment</span>
                    Dokumen Kontrak PKS
                </a>
            <?php endif; ?>

            <div class="dash-nav-header" style="margin-top: 14px;">Pengaturan</div>
            <a href="dashboard.php?tab=profile" class="dash-nav-link <?= $tab === 'profile' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">manage_accounts</span>
                Pengaturan Akun
            </a>
        </nav>

        <div class="dash-footer-nav">
            <a href="/" class="dash-nav-link" target="_blank">
                <span class="material-symbols-outlined">open_in_new</span>
                Lihat Website
            </a>
            <a href="logout.php" class="dash-nav-link" style="color: #ff8b8b;">
                <span class="material-symbols-outlined">logout</span>
                Keluar
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="dash-main">
        <!-- Topbar -->
        <header class="dash-topbar">
            <div class="dash-page-title-wrap">
                <button class="mobile-menu-trigger" onclick="toggleSidebar()" aria-label="Toggle menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="dash-page-title">
                    <?php
                    switch($tab) {
                        case 'users': echo 'Kelola Pengguna & Administrator'; break;
                        case 'inquiries': echo 'Permintaan Konsultasi & Leads Starlink'; break;
                        case 'monitoring': echo 'Monitoring Real-Time 3 Kit Starlink Gen 3 V4'; break;
                        case 'telemetry': echo 'Statistik Koneksi, Speedtest & SLA Live'; break;
                        case 'billing': echo 'Tagihan & Riwayat Pembayaran PKS (9 Jt/Bulan)'; break;
                        case 'pks': echo 'Kontrak Kerja Sama (PKS) Dedicated Enterprise'; break;
                        case 'profile': echo 'Pengaturan Profil & Keamanan Akun'; break;
                        default: echo $is_admin ? 'Ringkasan Dasbor Eksekutif' : 'Monitoring Starlink Dedicated PKS'; break;
                    }
                    ?>
                </h1>
            </div>

            <div class="dash-topbar-actions">
                <a href="/" class="btn-view-site" target="_blank">
                    <span class="material-symbols-outlined" style="font-size: 16px;">public</span>
                    Beranda DLI
                </a>
                <a href="logout.php" class="btn-dash btn-dash-danger btn-dash-sm" title="Keluar">
                    <span class="material-symbols-outlined" style="font-size: 16px;">logout</span>
                    Keluar
                </a>
            </div>
        </header>

        <!-- Dynamic Body Content -->
        <div class="dash-content">
            <?php if ($error): ?>
                <div class="dash-alert dash-alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="dash-alert dash-alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- TAB: MONITORING 3 KIT STARLINK GEN 3 V4 -->
            <?php if ($tab === 'monitoring'): ?>
                <!-- Hero Banner Klien PKS -->
                <div class="pks-hero-banner">
                    <div>
                        <div class="pks-badge">
                            <span class="material-symbols-outlined" style="font-size: 15px;">verified</span>
                            Kontrak PKS Dedicated Enterprise
                        </div>
                        <h2 style="margin: 0 0 6px; font-size: 22px; color: #ffffff;">
                            Status Operasional: 3 Kit Starlink Gen 3 V4 Aktif
                        </h2>
                        <div style="font-size: 13.5px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
                            <span>No. PKS: <strong style="color: #00D2FF;">PKS-DLI/STARLINK-DEDICATED/2025-0082</strong></span>
                            <span>Tagihan: <strong style="color: #10b981;">Rp 9.000.000 / Bulan (Semua LUNAS)</strong></span>
                            <span>SLA: <strong style="color: #38bdf8;">99.98% Guaranteed</strong></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div style="text-align: right;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8;">Status Jaringan NOC</div>
                            <div style="display: flex; align-items: center; gap: 6px; color: #10b981; font-weight: 700; font-size: 14px;">
                                <span class="status-dot" style="background: #10b981; box-shadow: 0 0 10px #10b981;"></span>
                                3 / 3 Kit Terhubung Optimal
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Metrik Utama Telemetri -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Total Kit Terpasang</span>
                            <span class="stat-value">3 Unit</span>
                            <span class="stat-note" style="color: #10b981;">100% Online Tanpa Obstruksi</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-cyan">
                            <span class="material-symbols-outlined">satellite_alt</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Rata-rata Download</span>
                            <span class="stat-value" id="metric-down"><?= $avg_live_down ?> Mbps</span>
                            <span class="stat-note">Dynamic Range: 180 – 300 Mbps</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-blue">
                            <span class="material-symbols-outlined">download</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Rata-rata Upload</span>
                            <span class="stat-value" id="metric-up"><?= $avg_live_up ?> Mbps</span>
                            <span class="stat-note">Dedicated Priority Bandwidth</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-amber">
                            <span class="material-symbols-outlined">upload</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Rata-rata Ping / SLA</span>
                            <span class="stat-value" id="metric-ping"><?= $avg_live_ping ?> ms</span>
                            <span class="stat-note" style="color: #10b981;">SLA Bulanan: 99.98%</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-purple">
                            <span class="material-symbols-outlined">network_check</span>
                        </div>
                    </div>
                </div>

                <!-- 3 Kit Starlink Cards -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin: 24px 0 16px;">
                    <h3 style="margin: 0; font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="color: #0284c7;">router</span>
                        Daftar 3 Kit Starlink Gen 3 V4 (Terminal A, B, C) (Read-Only)
                    </h3>
                    <span style="font-size: 12px; color: #64748b; background: #e2e8f0; padding: 4px 10px; border-radius: 999px;">
                        🔒 Parameter Dikelola NOC
                    </span>
                </div>

                <div class="pks-terminal-grid">
                    <?php foreach ($user_kits as $idx => $kit): 
                        $termLetter = chr(65 + $idx);
                    ?>
                        <div class="pks-terminal-card">
                            <div class="pks-terminal-header">
                                <div class="pks-terminal-title">
                                    <span class="material-symbols-outlined" style="color: #0099cc;">satellite_alt</span>
                                    Terminal <?= $termLetter ?>
                                </div>
                                <span class="badge-pill badge-active" style="background:#ecfdf5; color:#047857; font-weight:700;">
                                    ● Online
                                </span>
                            </div>

                            <div class="pks-kit-box">
                                <span><?= e($kit['kit_number']) ?></span>
                                <span class="pks-kit-lock">
                                    <span class="material-symbols-outlined" style="font-size: 13px;">lock</span>
                                    Read-Only
                                </span>
                            </div>

                            <div style="font-size: 13px; margin-bottom: 8px; color: #334155; line-height: 1.6;">
                                <div><strong>Hardware:</strong> Starlink Standard Gen 3 (V4)</div>
                                <div><strong>Lokasi:</strong> <?= e($kit['location']) ?></div>
                                <div><strong>IP Gateway:</strong> <code><?= e($kit['ip_address']) ?></code></div>
                                <div><strong>Sinyal &amp; Obstruksi:</strong> <span style="color:#10b981; font-weight:600;">100% (0.0% Obstruksi)</span></div>
                                <div><strong>Satelit Aktif:</strong> 14 Satelit LEO Terkunci</div>
                            </div>

                            <div class="pks-telemetry-row">
                                <div class="pks-telemetry-item">
                                    <div class="val" id="kit-down-<?= $idx ?>"><?= $kit['live_down'] ?><small style="font-size:10px;">Mbps</small></div>
                                    <div class="lbl">Download</div>
                                </div>
                                <div class="pks-telemetry-item">
                                    <div class="val" id="kit-up-<?= $idx ?>"><?= $kit['live_up'] ?><small style="font-size:10px;">Mbps</small></div>
                                    <div class="lbl">Upload</div>
                                </div>
                                <div class="pks-telemetry-item">
                                    <div class="val" id="kit-ping-<?= $idx ?>"><?= $kit['live_ping'] ?><small style="font-size:10px;">ms</small></div>
                                    <div class="lbl">Ping</div>
                                </div>
                            </div>

                            <button type="button" onclick="runKitDiagnostic('<?= e($kit['kit_number']) ?>', 'Terminal <?= $termLetter ?>')" class="btn-dash btn-dash-sm" style="width: 100%; justify-content: center; background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1;">
                                <span class="material-symbols-outlined" style="font-size: 16px;">health_and_safety</span>
                                Jalankan Diagnostik Terminal
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Kebijakan Read-Only Alert -->
                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #0099cc; border-radius: 8px; padding: 16px 20px; margin-top: 10px; font-size: 13px; color: #334155; display: flex; gap: 14px; align-items: flex-start;">
                    <span class="material-symbols-outlined" style="color: #0099cc; font-size: 24px; flex-shrink: 0;">lock</span>
                    <div>
                        <strong style="color: #0f172a;">Kebijakan Keamanan Perangkat Dedicated PKS:</strong>
                        <p style="margin: 4px 0 0; line-height: 1.5;">
                            Nomor Kit (<code style="background:#e2e8f0; padding:2px 6px; border-radius:4px;">KIT********</code>), alokasi IP Dedicated, dan konfigurasi rute satelit dikelola dan diawasi secara penuh oleh <strong>Network Operations Center (NOC) PT Data Lake Indonesia</strong> untuk menjamin SLA 99.9%. Klien memiliki akses pemantauan (monitoring-only) dan tidak diperkenankan memodifikasi konfigurasi perangkat tanpa otorisasi tertulis NOC.
                        </p>
                    </div>
                </div>

            <!-- TAB: TELEMETRY (STATISTIK, SPEEDTEST, SLA LIVE) -->
            <?php elseif ($tab === 'telemetry'): ?>
                <!-- Hero Banner Pengujian Per-Terminal -->
                <div class="pks-hero-banner" style="background: linear-gradient(135deg, #07162c 0%, #0a2540 100%);">
                    <div>
                        <div class="pks-badge" style="background: rgba(0, 210, 255, 0.15); border-color: #00D2FF; color: #00D2FF;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">speed</span>
                            Speedtest Dedicated Multi-Terminal
                        </div>
                        <h2 style="margin: 0 0 6px; font-size: 22px; color: #ffffff;">
                            Pengujian Bandwidth Real-Time Per-Starlink (Terminal A, B, C)
                        </h2>
                        <div style="font-size: 13.5px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 16px;">
                            <span>Hardware: <strong style="color: #ffffff;">Starlink Standard Gen 3 (V4)</strong></span>
                            <span>Rentang Bandwidth: <strong style="color: #38bdf8;">180 – 300 Mbps (Independen)</strong></span>
                            <span>Target SLA: <strong style="color: #10b981;">99.98% Guaranteed</strong></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #10b981; font-weight: 700; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 6px 14px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: otpPulse 1.5s infinite;"></span>
                            3 Terminal Online &amp; Siap Diuji
                        </span>
                    </div>
                </div>

                <!-- 3 Terminal Dedicated Speedtest Cards (Terminal A, Terminal B, Terminal C) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <?php foreach ($user_kits as $idx => $kit): 
                        $letter = chr(65 + $idx); // 'A', 'B', 'C'
                    ?>
                        <div class="dash-card term-test-card" id="term-card-<?= $idx ?>" style="border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); position: relative; display: flex; flex-direction: column;">
                            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #00D2FF, #0284c7);"></div>
                            
                            <!-- Header Card -->
                            <div style="padding: 18px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 17px; font-weight: 800; color: #07162c; display: flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined" style="color: #0099cc; font-size: 22px;">satellite_alt</span>
                                        Terminal <?= $letter ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                        Kit: <strong style="font-family: monospace; color: #0284c7;"><?= e($kit['kit_number']) ?></strong>
                                    </div>
                                </div>
                                <span class="badge-pill badge-active" style="background: #ecfdf5; color: #047857; font-weight: 700; font-size: 11.5px;">
                                    ● Online
                                </span>
                            </div>

                            <!-- Body with Live Gauge & Metrics -->
                            <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                                <!-- Live Speed Readout Box -->
                                <div style="text-align: center; padding: 20px 14px; background: linear-gradient(135deg, #040d1a 0%, #071a33 100%); border-radius: 12px; color: #fff; margin-bottom: 18px; border: 1px solid rgba(0, 210, 255, 0.2); box-shadow: 0 6px 18px rgba(0,0,0,0.15); position: relative; overflow: hidden;" id="term-box-<?= $idx ?>">
                                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #38bdf8; margin-bottom: 4px;" id="term-status-<?= $idx ?>">
                                        Kecepatan Unduh Real-Time
                                    </div>
                                    <div style="font-size: 46px; font-weight: 800; color: #00D2FF; line-height: 1.05; letter-spacing: -1px;" id="term-down-<?= $idx ?>">
                                        <?= $kit['live_down'] ?>
                                    </div>
                                    <div style="font-size: 12px; color: #94a3b8; font-weight: 600; margin-top: 4px; text-transform: uppercase;" id="term-unit-<?= $idx ?>">
                                        Mbps Download (Terminal <?= $letter ?>)
                                    </div>
                                </div>

                                <!-- 4 Live Metrics Grid -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; text-align: center;">
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 8px;">
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Upload Speed</div>
                                        <div style="font-size: 18px; font-weight: 800; color: #d97706;" id="term-up-<?= $idx ?>"><?= $kit['live_up'] ?> <small style="font-size: 11px;">Mbps</small></div>
                                    </div>
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 8px;">
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Latensi (Ping)</div>
                                        <div style="font-size: 18px; font-weight: 800; color: #0284c7;" id="term-ping-<?= $idx ?>"><?= $kit['live_ping'] ?> <small style="font-size: 11px;">ms</small></div>
                                    </div>
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 8px;">
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Jitter / Packet Loss</div>
                                        <div style="font-size: 14px; font-weight: 700; color: #10b981;" id="term-jitter-<?= $idx ?>"><?= $kit['live_jitter'] ?> / 0%</div>
                                    </div>
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 8px;">
                                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">SLA Uptime</div>
                                        <div style="font-size: 14px; font-weight: 800; color: #0f172a;"><?= e($kit['sla_percent']) ?>%</div>
                                    </div>
                                </div>

                                <!-- Button to Test THIS Terminal -->
                                <button type="button" id="btnTestTerm_<?= $idx ?>" onclick="testSingleTerminal(<?= $idx ?>, '<?= $letter ?>')" class="btn-dash btn-dash-primary" style="width: 100%; justify-content: center; font-size: 14px; font-weight: 700; padding: 12px; margin-top: auto;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">speed</span>
                                    Cek Speedtest Terminal <?= $letter ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 24-Hour Traffic Curve & SLA 30-Day Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
                    <!-- Traffic Curve -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: #00D2FF;">analytics</span>
                                Grafik Throughput 24 Jam Terakhir
                            </h2>
                            <span style="font-size: 12px; color: #10b981; font-weight: 600;">● Real-time</span>
                        </div>
                        <div class="dash-card-body">
                            <!-- Visual SVG Chart -->
                            <svg viewBox="0 0 500 160" style="width: 100%; height: auto; overflow: visible;">
                                <defs>
                                    <linearGradient id="gradDown" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#00D2FF" stop-opacity="0.4"/>
                                        <stop offset="100%" stop-color="#00D2FF" stop-opacity="0.0"/>
                                    </linearGradient>
                                </defs>
                                <!-- Grid Lines -->
                                <line x1="0" y1="30" x2="500" y2="30" stroke="#f1f5f9" stroke-width="1" />
                                <line x1="0" y1="70" x2="500" y2="70" stroke="#f1f5f9" stroke-width="1" />
                                <line x1="0" y1="110" x2="500" y2="110" stroke="#f1f5f9" stroke-width="1" />
                                <line x1="0" y1="150" x2="500" y2="150" stroke="#f1f5f9" stroke-width="1" />
                                <!-- Area fill & stroke -->
                                <path d="M0,80 Q50,40 100,55 T200,45 T300,50 T400,35 T500,42 L500,150 L0,150 Z" fill="url(#gradDown)" />
                                <path d="M0,80 Q50,40 100,55 T200,45 T300,50 T400,35 T500,42" fill="none" stroke="#0099cc" stroke-width="3" />
                                <!-- Upload curve -->
                                <path d="M0,120 Q50,115 100,122 T200,118 T300,120 T400,112 T500,116" fill="none" stroke="#f59e0b" stroke-width="2" stroke-dasharray="4,4" />
                            </svg>
                            <div style="display: flex; justify-content: center; gap: 20px; margin-top: 14px; font-size: 12px;">
                                <span style="display: flex; align-items: center; gap: 6px; color: #0099cc; font-weight: 600;">
                                    <span style="display:inline-block; width:12px; height:3px; background:#0099cc;"></span> <span id="chartAvgDown">Download (Avg: <?= $avg_live_down ?> Mbps)</span>
                                </span>
                                <span style="display: flex; align-items: center; gap: 6px; color: #f59e0b; font-weight: 600;">
                                    <span style="display:inline-block; width:12px; height:3px; background:#f59e0b;"></span> <span id="chartAvgUp">Upload (Avg: <?= $avg_live_up ?> Mbps)</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 30-Day SLA Grid -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: #10b981;">verified_user</span>
                                Kepatuhan SLA Uptime 30 Hari (99.98%)
                            </h2>
                            <span style="font-size: 12px; color: #0f172a; font-weight: 700;">Target: 99.5%</span>
                        </div>
                        <div class="dash-card-body">
                            <div style="margin-bottom: 12px; font-size: 13px; color: #64748b;">
                                Seluruh 30 hari dalam periode berjalan mencatat ketersediaan 100% tanpa gangguan kritis:
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(15, 1fr); gap: 6px; margin-bottom: 16px;">
                                <?php for($d = 1; $d <= 30; $d++): ?>
                                    <div style="height: 32px; background: #10b981; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 10px; font-weight: 700;" title="Hari ke-<?= $d ?>: 100% Uptime (0 Gangguan)">
                                        <?= $d ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #166534;">
                                ✓ <strong>Garansi SLA Dedicated PKS Terpenuhi:</strong> Tidak ada potongan restitusi tagihan.
                            </div>
                        </div>
                    </div>
                </div>

            <!-- TAB: BILLING (TAGIHAN & INVOICE PKS 9 JT/BULAN - LUNAS) -->
            <?php elseif ($tab === 'billing'): ?>
                <!-- Summary Card -->
                <div class="pks-hero-banner" style="background: linear-gradient(135deg, #07162c 0%, #0f3d64 100%);">
                    <div>
                        <div class="pks-badge" style="background: rgba(16, 185, 129, 0.2); border-color: #10b981; color: #10b981;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">check_circle</span>
                            STATUS: SEMUA TAGIHAN TELAH LUNAS
                        </div>
                        <h2 style="margin: 0 0 6px; font-size: 24px; color: #ffffff;">
                            Tagihan Langganan: Rp 9.000.000 / Bulan
                        </h2>
                        <div style="font-size: 13.5px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
                            <span>Paket: <strong style="color: #ffffff;">Dedicated PKS Enterprise (3x Starlink Gen 3 V4)</strong></span>
                            <span>Riwayat Aktif: <strong style="color: #38bdf8;">Februari 2025 s/d Sekarang (21 Bulan)</strong></span>
                            <span>Total Pembayaran: <strong style="color: #10b981;">Rp 189.000.000 (Lunas)</strong></span>
                        </div>
                    </div>
                    <div>
                        <div class="invoice-seal" style="background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            ✓ LUNAS / VERIFIED
                        </div>
                    </div>
                </div>

                <!-- Table of Invoices -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--primary-dli);">receipt_long</span>
                            Riwayat Faktur &amp; Pembayaran PKS (Februari 2025 – Sekarang)
                        </h2>
                        <span style="font-size: 13px; color: #64748b;">
                            Total: <strong>21 Faktur</strong> (Semua Lunas)
                        </span>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>No. Faktur / Kwitansi</th>
                                        <th>Periode Tagihan</th>
                                        <th>Layanan Starlink</th>
                                        <th>Jumlah (IDR)</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Metode Pembayaran</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($billing_history as $bill): ?>
                                        <tr>
                                            <td style="font-family: monospace; font-weight: 700; color: #0284c7;">
                                                <?= e($bill['invoice_no']) ?>
                                            </td>
                                            <td style="font-weight: 600; color: #0f172a;">
                                                <?= e($bill['period']) ?>
                                            </td>
                                            <td>
                                                <?= e($bill['description']) ?>
                                            </td>
                                            <td style="font-weight: 700; color: #0f172a;">
                                                <?= e($bill['amount_formatted']) ?>
                                            </td>
                                            <td style="color: #64748b; font-size: 12.5px;">
                                                <?= e($bill['paid_date']) ?>
                                            </td>
                                            <td style="font-size: 12px; color: #475569;">
                                                <?= e($bill['payment_method']) ?>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-active" style="background: #ecfdf5; color: #047857; font-weight: 700;">
                                                    ✓ <?= e($bill['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" onclick="openInvoiceModal(<?= htmlspecialchars(json_encode($bill), ENT_QUOTES, 'UTF-8') ?>)" class="btn-dash btn-dash-primary btn-dash-sm" style="font-size: 12px;">
                                                    <span class="material-symbols-outlined" style="font-size: 15px;">receipt</span>
                                                    Lihat Kwitansi
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB: KONTRAK PKS DEDICATED (DOKUMEN RESMI RESELLER) -->
            <?php elseif ($tab === 'pks'): ?>
                <!-- Action Bar & Controls -->
                <div class="pks-hero-banner no-print" style="background: linear-gradient(135deg, #07162c 0%, #0d274c 100%); margin-bottom: 24px;">
                    <div>
                        <div class="pks-badge" style="background: rgba(16, 185, 129, 0.2); border-color: #10b981; color: #10b981;">
                            <span class="material-symbols-outlined" style="font-size: 15px;">verified</span>
                            PERJANJIAN KERJA SAMA (PKS) RESMI RESELLER
                        </div>
                        <h2 style="margin: 0 0 6px; font-size: 22px; color: #ffffff;">
                            Dokumen Kontrak PKS: PT Data Lake Indonesia &amp; PT Network Inovatif Solutions
                        </h2>
                        <div style="font-size: 13.5px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 16px;">
                            <span>No: <strong style="color: #00D2FF; font-family: monospace;">082/PKS/DLI-NIS/02/2025</strong></span>
                            <span>Masa Berlaku: <strong style="color: #ffffff;">36 Bulan (01 Feb 2025 – 01 Feb 2028)</strong></span>
                            <span>Status: <strong style="color: #10b981;">Tercatat Sah di Database DLI</strong></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <button type="button" onclick="window.print()" class="btn-dash btn-dash-primary" style="padding: 10px 18px; font-size: 13.5px; font-weight: 700;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">print</span>
                            Cetak / Unduh PDF Dokumen
                        </button>
                        <button type="button" onclick="openModal('verifyDocModal')" class="btn-dash" style="background: #ffffff; color: #07162c; border: 1px solid #cbd5e1; padding: 10px 18px; font-size: 13.5px; font-weight: 700;">
                            <span class="material-symbols-outlined" style="font-size: 18px; color: #10b981;">verified_user</span>
                            Cek Keaslian Dokumen
                        </button>
                    </div>
                </div>

                <!-- Printable Formal PKS Document Paper -->
                <div class="pks-document-paper" id="printablePksDoc">
                    
                    <!-- Kop Surat Resmi PT Data Lake Indonesia -->
                    <div class="pks-kop-surat">
                        <div style="display:flex; align-items:center; gap:16px;">
                            <img src="/logo/DLI-logo-navy.png" alt="PT Data Lake Indonesia" style="height:48px;">
                            <div>
                                <div style="font-weight:800; font-size:16px; color:#07162c; letter-spacing:0.5px;">PT DATA LAKE INDONESIA</div>
                                <div style="font-size:11.5px; color:#0284c7; font-weight:700;">Authorized Distributor &amp; Reseller Starlink Indonesia</div>
                                <div style="font-size:11px; color:#64748b;">Revenue Tower Lt. 16, District 8 SCBD, Jl. Jend. Sudirman Kav. 52-53, Jakarta Selatan 11530</div>
                            </div>
                        </div>
                        <div class="pks-kop-contact">
                            <div><strong>Telp:</strong> (021) 5082-0800 / <?= e(get_wa_number()) ?></div>
                            <div><strong>Email:</strong> legal@datalake.id</div>
                            <div><strong>Web:</strong> www.datalakeindonesia.com</div>
                        </div>
                    </div>

                    <!-- Judul Perjanjian -->
                    <div style="text-align:center; margin-bottom:28px;">
                        <h2 style="font-size:18px; font-weight:800; color:#07162c; text-transform:uppercase; margin:0 0 4px; letter-spacing:0.5px;">
                            PERJANJIAN KERJA SAMA
                        </h2>
                        <h3 style="font-size:15px; font-weight:700; color:#0284c7; text-transform:uppercase; margin:0 0 8px;">
                            PENYEDIAAN LAYANAN INTERNET UNTUK RESELLER
                        </h3>
                        <div style="font-size:13px; font-weight:700; color:#0f172a; font-family:monospace; background:#f1f5f9; display:inline-block; padding:4px 14px; border-radius:6px; border:1px solid #cbd5e1;">
                            Nomor: 082/PKS/DLI-NIS/02/2025
                        </div>
                    </div>

                    <!-- Pembukaan -->
                    <p style="text-align:justify; margin-bottom:16px; font-size:13.5px;">
                        Pada hari ini, <strong>Rabu, tanggal 05 Februari 2025</strong>, telah dibuat dan ditandatangani Perjanjian Kerja Sama Penyediaan Layanan Internet (selanjutnya disebut <strong>“Perjanjian”</strong>), oleh dan antara pihak-pihak di bawah ini:
                    </p>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px 20px; margin-bottom:24px; font-size:13.5px;">
                        <div style="margin-bottom:16px;">
                            <strong style="color:#0284c7; font-size:14px;">PIHAK PERTAMA</strong><br>
                            <strong>PT DATA LAKE INDONESIA</strong>, berkedudukan di Revenue Tower Lantai 16, District 8 SCBD, Jl. Sudirman Kav. 52-53, Senayan, Kebayoran Baru, Jakarta Selatan, DKI Jakarta 11530, dalam hal ini diwakili oleh:<br>
                            <div style="margin-left:16px; margin-top:4px;">
                                <strong>Nama:</strong> Nike P. Kosasih<br>
                                <strong>Jabatan:</strong> Direktur Utama
                            </div>
                            <div style="margin-top:4px;"><em style="color:#64748b; font-size:12.5px;">Selanjutnya disebut <strong>PIHAK PERTAMA</strong>.</em></div>
                        </div>

                        <div>
                            <strong style="color:#0284c7; font-size:14px;">PIHAK KEDUA</strong><br>
                            <strong>PT NETWORK INOVATIF SOLUTIONS</strong>, berkedudukan di Desa Lusuo, Kecamatan Morotai Utara, Pulau Morotai, Maluku Utara, dalam hal ini diwakili oleh:<br>
                            <div style="margin-left:16px; margin-top:4px;">
                                <strong>Nama:</strong> Mushawir Odegoa<br>
                                <strong>Jabatan:</strong> Direktur
                            </div>
                            <div style="margin-top:4px;"><em style="color:#64748b; font-size:12.5px;">Selanjutnya disebut <strong>PIHAK KEDUA</strong>.</em></div>
                        </div>
                    </div>

                    <p style="text-align:justify; margin-bottom:22px; font-size:13.5px;">
                        Para Pihak sepakat mengadakan kerja sama dengan ketentuan sebagai berikut:
                    </p>

                    <!-- PASAL 1 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 1</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">RUANG LINGKUP</div>
                        <p style="text-align:justify; font-size:13.5px;">
                            PIHAK PERTAMA menyediakan layanan akses Internet kepada PIHAK KEDUA untuk kebutuhan operasional dan reseller/jual kembali layanan Internet, sesuai ketentuan peraturan perundang-undangan yang berlaku.
                        </p>
                    </div>

                    <!-- PASAL 2 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 2</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">SPESIFIKASI LAYANAN</div>
                        <p style="text-align:justify; font-size:13.5px; margin-bottom:6px;">
                            Akses Internet menggunakan jaringan satelit Starlink.
                        </p>
                        <p style="text-align:justify; font-size:13.5px; margin-bottom:6px;">
                            Kapasitas layanan:
                        </p>
                        <ul style="margin-left:24px; font-size:13.5px; margin-bottom:8px; list-style-type:disc;">
                            <li>Download: <strong>300 Mbps</strong></li>
                            <li>Upload: <strong>45 Mbps</strong></li>
                            <li>Rasio download dan upload adalah <strong>20 : 3</strong>.</li>
                        </ul>
                        <p style="text-align:justify; font-size:13.5px;">
                            Lokasi layanan: <strong>Desa Lusuo, Kecamatan Morotai Utara, Pulau Morotai, Maluku Utara</strong>.
                        </p>
                    </div>

                    <!-- PASAL 3 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 3</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">PENGGUNAAN UNTUK RESELLER</div>
                        <ol style="margin-left:24px; font-size:13.5px; text-align:justify;">
                            <li style="margin-bottom:6px;">PIHAK KEDUA menggunakan layanan untuk kegiatan usaha dan jual kembali/reseller akses Internet kepada pelanggan PIHAK KEDUA.</li>
                            <li style="margin-bottom:6px;">PIHAK KEDUA bertanggung jawab atas kegiatan dan pelanggan reseller yang berada di bawah pengelolaannya.</li>
                            <li>Penggunaan layanan wajib mengikuti ketentuan layanan, perizinan, dan peraturan perundang-undangan yang berlaku.</li>
                        </ol>
                    </div>

                    <!-- PASAL 4 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 4</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">BIAYA DAN PEMBAYARAN</div>
                        <ol style="margin-left:24px; font-size:13.5px; text-align:justify;">
                            <li style="margin-bottom:6px;">Biaya layanan sebesar <strong>Rp 9.000.000,- (Sembilan Juta Rupiah)</strong> per bulan.</li>
                            <li style="margin-bottom:6px;">Pajak dikenakan sesuai ketentuan perpajakan yang berlaku.</li>
                            <li>Pembayaran dilakukan paling lambat <strong>14 (empat belas)</strong> hari kalender sejak invoice diterbitkan.</li>
                        </ol>
                    </div>

                    <!-- PASAL 5 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 5</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">MASA BERLANGGANAN</div>
                        <ol style="margin-left:24px; font-size:13.5px; text-align:justify;">
                            <li style="margin-bottom:6px;">Masa berlangganan adalah <strong>36 (tiga puluh enam) bulan</strong> sejak tanggal aktivasi layanan (01 Februari 2025 sampai dengan 01 Februari 2028).</li>
                            <li>Perpanjangan dapat dilakukan berdasarkan kesepakatan tertulis Para Pihak.</li>
                        </ol>
                    </div>

                    <!-- PASAL 6 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 6</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">GANGGUAN DAN PEMELIHARAAN</div>
                        <ol style="margin-left:24px; font-size:13.5px; text-align:justify;">
                            <li style="margin-bottom:6px;">PIHAK PERTAMA menangani gangguan yang berada dalam tanggung jawabnya.</li>
                            <li style="margin-bottom:6px;">Gangguan akibat kondisi satelit, cuaca ekstrem, force majeure, perangkat PIHAK KEDUA, atau faktor di luar kendali PIHAK PERTAMA tidak dianggap sebagai kelalaian PIHAK PERTAMA.</li>
                            <li>Pemeliharaan dapat dilakukan untuk menjaga keamanan dan kualitas layanan.</li>
                        </ol>
                    </div>

                    <!-- PASAL 7 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 7</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">KEWAJIBAN PARA PIHAK</div>
                        <ol style="margin-left:24px; font-size:13.5px; text-align:justify;">
                            <li style="margin-bottom:6px;">PIHAK PERTAMA wajib menyediakan layanan sesuai spesifikasi yang disepakati dan menangani gangguan layanan.</li>
                            <li>PIHAK KEDUA wajib membayar biaya layanan tepat waktu, menjaga perangkat, serta menggunakan layanan sesuai hukum dan ketentuan yang berlaku.</li>
                        </ol>
                    </div>

                    <!-- PASAL 8 -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 8</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">PENYELESAIAN PERSELISIHAN</div>
                        <p style="text-align:justify; font-size:13.5px;">
                            Setiap perselisihan diselesaikan terlebih dahulu melalui musyawarah. Apabila tidak tercapai kesepakatan, Para Pihak dapat menempuh penyelesaian melalui mekanisme hukum yang berlaku di Republik Indonesia.
                        </p>
                    </div>

                    <!-- PASAL 9 -->
                    <div style="margin-bottom:28px;">
                        <div style="font-weight:800; font-size:14px; color:#07162c; text-align:center; margin-bottom:2px;">PASAL 9</div>
                        <div style="font-weight:700; font-size:13px; color:#0284c7; text-align:center; margin-bottom:8px; text-transform:uppercase;">PENUTUP</div>
                        <p style="text-align:justify; font-size:13.5px;">
                            Perjanjian ini dibuat dalam 2 (dua) rangkap asli, masing-masing mempunyai kekuatan hukum yang sama dan ditandatangani Para Pihak dalam keadaan sadar dan tanpa paksaan.
                        </p>
                    </div>

                    <!-- Penandatanganan & QR Code Tanda Tangan Resmi -->
                    <div style="margin-top:24px; padding-top:20px; border-top:1px solid #e2e8f0;">
                        <div style="text-align:right; font-size:13.5px; margin-bottom:20px; color:#475569;">
                            Jakarta Selatan, 05 Februari 2025
                        </div>

                        <div class="pks-signature-grid">
                            <!-- PIHAK PERTAMA -->
                            <div style="border:1px solid #cbd5e1; border-radius:10px; padding:20px 16px; background:#f8fafc; position:relative;">
                                <div style="font-weight:800; font-size:13px; color:#64748b; text-transform:uppercase;">PIHAK PERTAMA</div>
                                <div style="font-weight:800; font-size:15px; color:#07162c; margin-bottom:12px;">PT DATA LAKE INDONESIA</div>


                                <!-- Real QR Code with Data Lake Logo in Center -->
                                <div onclick="window.open('/verify-doc.php?doc=082%2FPKS%2FDLI-NIS%2F02%2F2025','_blank')" style="cursor:pointer; display:inline-block; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:8px; box-shadow:0 4px 12px rgba(0,0,0,0.08); transition:transform 0.2s;" title="Scan QR / klik untuk verifikasi keaslian dokumen di database">
                                    <div style="position:relative; width:120px; height:120px; margin:0 auto;">
                                        <canvas id="qrcode-pihak1" style="width:120px;height:120px;"></canvas>
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:26px; height:26px; background:#ffffff; border-radius:50%; border:1.5px solid #0099cc; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 4px rgba(0,0,0,0.18); padding:2px;">
                                            <img src="/logo/DLI-logo-navy.png" alt="DLI" style="width:100%; height:auto; border-radius:50%;">
                                        </div>
                                    </div>
                                    <div style="font-size:9px; color:#0284c7; font-family:monospace; font-weight:700; margin-top:4px; letter-spacing:0.5px;">
                                        082/PKS/DLI-NIS/02/2025
                                    </div>
                                </div>

                                <div style="margin-top:14px; font-weight:800; font-size:14.5px; color:#07162c; text-decoration:underline;">
                                    Nike P. Kosasih
                                </div>
                                <div style="font-size:12.5px; color:#64748b; font-weight:600;">
                                    Direktur Utama
                                </div>
                            </div>

                            <!-- PIHAK KEDUA -->
                            <div style="border:1px solid #cbd5e1; border-radius:10px; padding:20px 16px; background:#f8fafc; position:relative;">
                                <div style="font-weight:800; font-size:13px; color:#64748b; text-transform:uppercase;">PIHAK KEDUA</div>
                                <div style="font-weight:800; font-size:15px; color:#07162c; margin-bottom:12px;">PT NETWORK INOVATIF SOLUTIONS</div>


                                <!-- Real QR Code PIHAK KEDUA with DLI Logo in Center -->
                                <div onclick="window.open('/verify-doc.php?doc=082%2FPKS%2FDLI-NIS%2F02%2F2025','_blank')" style="cursor:pointer; display:inline-block; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:8px; box-shadow:0 4px 12px rgba(0,0,0,0.08); transition:transform 0.2s;" title="Scan QR / klik untuk verifikasi keaslian dokumen di database">
                                    <div style="position:relative; width:120px; height:120px; margin:0 auto;">
                                        <canvas id="qrcode-pihak2" style="width:120px;height:120px;"></canvas>
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:26px; height:26px; background:#ffffff; border-radius:50%; border:1.5px solid #059669; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 4px rgba(0,0,0,0.18); padding:2px;">
                                            <img src="/logo/DLI-logo-navy.png" alt="DLI" style="width:100%; height:auto; border-radius:50%;">
                                        </div>
                                    </div>
                                    <div style="font-size:9px; color:#059669; font-family:monospace; font-weight:700; margin-top:4px; letter-spacing:0.5px;">
                                        082/PKS/DLI-NIS/02/2025
                                    </div>
                                </div>

                                <div style="margin-top:14px; font-weight:800; font-size:14.5px; color:#07162c; text-decoration:underline;">
                                    Mushawir Odegoa
                                </div>
                                <div style="font-size:12.5px; color:#64748b; font-weight:600;">
                                    Direktur
                                </div>
                            </div>
                        </div>

                        <!-- Sudut Bawah Dokumen PKS: Cek Keaslian Dokumen Database -->
                        <div class="pks-doc-footer">
                            <div style="font-size:11px; color:#64748b; line-height:1.5;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span class="material-symbols-outlined" style="font-size:15px; color:#0284c7;">verified_user</span>
                                    <span>Integritas Dokumen Terenkripsi &amp; Terarsip Resmi di Pangkalan Data PT Data Lake Indonesia</span>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <a href="https://datalakeindonesia.com/verify-doc.php?doc=082%2FPKS%2FDLI-NIS%2F02%2F2025" target="_blank" style="font-size:11.5px; color:#047857; text-decoration:none; font-weight:700; display:inline-flex; align-items:center; gap:5px; background:#ecfdf5; padding:6px 14px; border-radius:6px; border:1px solid #a7f3d0; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                                    <span class="material-symbols-outlined" style="font-size:16px; color:#10b981;">verified</span>
                                    Cek Keaslian Dokumen Database
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- TAB: OVERVIEW (Admin Only) -->
            <?php elseif ($tab === 'overview' && $is_admin): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Total Pengguna</span>
                            <span class="stat-value"><?= $totalUsers ?></span>
                            <span class="stat-note"><?= $activeUsers ?> akun aktif</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-blue">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Permintaan Layanan</span>
                            <span class="stat-value"><?= $totalInquiries ?></span>
                            <span class="stat-note" style="color: <?= $newInquiries > 0 ? '#d97706' : '#10b981' ?>;">
                                <?= $newInquiries ?> permohonan baru
                            </span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-cyan">
                            <span class="material-symbols-outlined">support_agent</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Konektivitas Satelit</span>
                            <span class="stat-value">Aktif</span>
                            <span class="stat-note">Starlink Authorized</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-purple">
                            <span class="material-symbols-outlined">satellite_alt</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Klien PKS Dedicated</span>
                            <span class="stat-value">Rp 9 Jt/bln</span>
                            <span class="stat-note" style="color:#10b981;">Semua Lunas</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-amber">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Inquiries Section -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--accent-cyan-dark);">contact_mail</span>
                            Permintaan Konsultasi &amp; Leads Terbaru
                        </h2>
                        <a href="dashboard.php?tab=inquiries" class="btn-dash btn-dash-primary btn-dash-sm">
                            Lihat Semua Leads
                        </a>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Nama Pemohon</th>
                                        <th>Kontak (Email / HP)</th>
                                        <th>Paket Pilihan</th>
                                        <th>Lokasi</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inquiriesList)): ?>
                                        <tr><td colspan="6" style="text-align:center; padding: 24px;">Belum ada permintaan masuk.</td></tr>
                                    <?php else: ?>
                                        <?php foreach (array_slice($inquiriesList, 0, 5) as $inq): ?>
                                            <tr>
                                                <td style="font-weight: 700;"><?= e($inq['name']) ?></td>
                                                <td>
                                                    <div><?= e($inq['email']) ?></div>
                                                    <small style="color: #64748b;"><?= e($inq['phone']) ?></small>
                                                </td>
                                                <td><span class="badge-pill badge-user"><?= e($inq['service_package']) ?></span></td>
                                                <td><?= e($inq['location'] ?: '-') ?></td>
                                                <td>
                                                    <span class="badge-pill badge-<?= e($inq['status']) ?>">
                                                        <?= e($inq['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($inq['phone'])): ?>
                                                        <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $inq['phone']) ?>&text=Halo%20<?= urlencode($inq['name']) ?>%2C%20terima%20kasih%20telah%20menghubungi%20PT%20Data%20Lake%20Indonesia." 
                                                           target="_blank" class="btn-dash btn-dash-accent btn-dash-sm" style="font-size: 11px;">
                                                            <span class="material-symbols-outlined" style="font-size: 14px;">chat</span> WA
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Logs -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: #6366f1;">history</span>
                            Riwayat Aktivitas Sistem
                        </h2>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>User</th>
                                        <th>Aktivitas</th>
                                        <th>Detail</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logsList as $log): ?>
                                        <tr>
                                            <td style="white-space: nowrap; color: #64748b; font-size: 12px;"><?= e($log['created_at']) ?></td>
                                            <td style="font-weight: 600;"><?= e($log['username'] ?: 'Sistem/Tamu') ?></td>
                                            <td><span class="badge-pill badge-user"><?= e($log['action']) ?></span></td>
                                            <td><?= e($log['details']) ?></td>
                                            <td><code><?= e($log['ip_address']) ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB: USERS MANAGEMENT (Admin Only) -->
            <?php elseif ($tab === 'users' && $is_admin): ?>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--primary-dli);">manage_accounts</span>
                            Daftar Pengguna &amp; Admin (<?= count($usersList) ?>)
                        </h2>
                        <button onclick="openModal('addUserModal')" class="btn-dash btn-dash-primary">
                            <span class="material-symbols-outlined">person_add</span>
                            Tambah Pengguna Baru
                        </button>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Telepon / WA</th>
                                        <th>Peran</th>
                                        <th>Status</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usersList as $u): ?>
                                        <tr>
                                            <td style="font-weight: 700;"><?= e($u['name']) ?></td>
                                            <td><code><?= e($u['username']) ?></code></td>
                                            <td><?= e($u['email']) ?></td>
                                            <td><?= e($u['phone'] ?: '-') ?></td>
                                            <td><span class="badge-pill badge-<?= e($u['role']) ?>"><?= strtoupper(e($u['role'])) ?></span></td>
                                            <td><span class="badge-pill badge-<?= e($u['status']) ?>"><?= ucfirst(e($u['status'])) ?></span></td>
                                            <td style="font-size: 12px; color: #64748b;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                            <td>
                                                <?php if ($u['id'] !== $current_user_id): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn-dash btn-dash-danger btn-dash-sm" title="Hapus Pengguna">
                                                            <span class="material-symbols-outlined" style="font-size: 14px;">delete</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <small style="color: #64748b;">(Akun Anda)</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB: INQUIRIES & LEADS (Admin Only) -->
            <?php elseif ($tab === 'inquiries' && $is_admin): ?>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--primary-dli);">contact_mail</span>
                            Semua Permohonan &amp; Leads Starlink (<?= count($inquiriesList) ?>)
                        </h2>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Lengkap</th>
                                        <th>Email</th>
                                        <th>Nomor WhatsApp / HP</th>
                                        <th>Paket Layanan</th>
                                        <th>Lokasi Pemasangan</th>
                                        <th>Pesan / Catatan</th>
                                        <th>Status Permohonan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inquiriesList as $inq): ?>
                                        <tr>
                                            <td>#<?= $inq['id'] ?></td>
                                            <td style="font-weight: 700;"><?= e($inq['name']) ?></td>
                                            <td><a href="mailto:<?= e($inq['email']) ?>"><?= e($inq['email']) ?></a></td>
                                            <td>
                                                <strong><?= e($inq['phone']) ?></strong>
                                            </td>
                                            <td><span class="badge-pill badge-user"><?= e($inq['service_package']) ?></span></td>
                                            <td><?= e($inq['location'] ?: '-') ?></td>
                                            <td style="max-width: 200px; font-size: 12.5px;"><?= e($inq['message'] ?: '-') ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="update_inquiry">
                                                    <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                    <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px;">
                                                        <option value="new" <?= $inq['status'] === 'new' ? 'selected' : '' ?>>Baru (New)</option>
                                                        <option value="contacted" <?= $inq['status'] === 'contacted' ? 'selected' : '' ?>>Dihubungi</option>
                                                        <option value="completed" <?= $inq['status'] === 'completed' ? 'selected' : '' ?>>Selesai</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if (!empty($inq['phone'])): ?>
                                                    <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $inq['phone']) ?>&text=Halo%20<?= urlencode($inq['name']) ?>%2C%20kami%20dari%20PT%20Data%20Lake%20Indonesia%20ingin%20menindaklanjuti%20permintaan%20Starlink%20Anda." 
                                                       target="_blank" class="btn-dash btn-dash-accent btn-dash-sm" title="Chat WhatsApp">
                                                        <span class="material-symbols-outlined" style="font-size: 14px;">chat</span> WhatsApp
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB: PROFILE & SECURITY SETTINGS -->
            <?php elseif ($tab === 'profile'): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
                    <!-- Profile Info -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: var(--primary-dli);">person</span>
                                Informasi Profil
                            </h2>
                        </div>
                        <div class="dash-card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="color: #334155;">Username</label>
                                    <input type="text" class="form-input" style="background:#f1f5f9; color:#64748b; border-color:#e2e8f0; padding-left:14px;" value="<?= e($current_user['username']) ?>" disabled>
                                    <small style="color: #64748b;">Username tidak dapat diubah</small>
                                </div>

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="color: #334155;">Nama Lengkap / Perusahaan</label>
                                    <input type="text" name="name" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" value="<?= e($current_user['name']) ?>" required>
                                </div>

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="color: #334155;">Alamat Email</label>
                                    <input type="email" name="email" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" value="<?= e($current_user['email']) ?>" required>
                                </div>

                                <div class="form-group" style="margin-bottom: 24px;">
                                    <label class="form-label" style="color: #334155;">Nomor Telepon / WhatsApp</label>
                                    <input type="tel" name="phone" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" value="<?= e($current_user['phone']) ?>">
                                </div>

                                <button type="submit" class="btn-dash btn-dash-primary">
                                    Simpan Perubahan Profil
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: var(--primary-dli);">lock</span>
                                Ubah Kata Sandi
                            </h2>
                        </div>
                        <div class="dash-card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="update_password">

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="color: #334155;">Kata Sandi Saat Ini</label>
                                    <input type="password" name="current_password" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="••••••••" required>
                                </div>

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label" style="color: #334155;">Kata Sandi Baru</label>
                                    <input type="password" name="new_password" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="Min. 6 karakter" required>
                                </div>

                                <div class="form-group" style="margin-bottom: 24px;">
                                    <label class="form-label" style="color: #334155;">Ulangi Kata Sandi Baru</label>
                                    <input type="password" name="confirm_password" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="Ulangi sandi baru" required>
                                </div>

                                <button type="submit" class="btn-dash btn-dash-accent">
                                    Perbarui Kata Sandi
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Detail Kontrak & Perangkat PKS (Read-Only) -->
                    <?php if (!$is_admin): ?>
                    <div class="dash-card" style="grid-column: 1 / -1;">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: #0284c7;">lock</span>
                                Alokasi Perangkat Starlink &amp; Kontrak PKS (Read-Only)
                            </h2>
                            <span class="badge" style="background:#e2e8f0; color:#334155;">Dikelola NOC</span>
                        </div>
                        <div class="dash-card-body">
                            <p style="font-size: 13.5px; color: #475569; margin-bottom: 16px;">
                                Rincian perangkat dan nomor kit yang terdaftar di bawah ini bersifat tetap dan dipantau langsung oleh Network Operations Center (NOC) PT Data Lake Indonesia:
                            </p>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                                <?php foreach($user_kits as $idx => $kit): ?>
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <strong>Terminal <?= $idx + 1 ?></strong>
                                            <span style="color:#10b981; font-weight:700; font-size:12px;">● Online</span>
                                        </div>
                                        <div style="font-family:monospace; font-weight:700; color:#0284c7; margin-bottom:4px;">
                                            No. KIT: <?= e($kit['kit_number']) ?>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            Lokasi: <?= e($kit['location']) ?><br>
                                            Model: Starlink Standard Gen 3 (V4)
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_admin): ?>
                    <!-- WhatsApp & Store Settings (Admin Only) -->
                    <div class="dash-card" style="grid-column: 1 / -1;">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">
                                <span class="material-symbols-outlined" style="color: #25d366;">chat</span>
                                Pengaturan WhatsApp Resmi &amp; Web Store
                            </h2>
                        </div>
                        <div class="dash-card-body">
                            <p style="font-size: 13.5px; color: #64748b; margin-bottom: 20px;">
                                Nomor WhatsApp yang diisi di sini akan otomatis diterapkan ke seluruh tombol chat WhatsApp di Landing Page, Web Store, dan Login tanpa perlu mengubah kode.
                            </p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="update_contact_settings">

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    <div class="form-group">
                                        <label class="form-label" style="color: #334155;">Nomor WhatsApp Resmi (CS / Sales)</label>
                                        <input type="text" name="wa_number" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" value="<?= e(get_wa_number()) ?>" placeholder="08170117800 atau 62817..." required>
                                        <small style="color: #64748b;">Gunakan format 08xx atau 628xx</small>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" style="color: #334155;">Judul Web Store</label>
                                        <input type="text" name="site_title" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" value="<?= e(get_site_setting('site_title', 'Data Lake Official Store')) ?>" placeholder="Data Lake Official Store">
                                    </div>
                                </div>

                                <button type="submit" class="btn-dash btn-dash-primary">
                                    Simpan Pengaturan WhatsApp &amp; Toko
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Portal Wrapper (Prevents Flexbox Side-by-Side Glitch) -->
    <div id="modalPortal" style="display: contents;">
        <!-- Modal Tambah Pengguna Baru (Admin Only) -->
        <?php if ($is_admin): ?>
        <div class="dash-modal" id="addUserModal" style="display: none !important;">
            <div class="dash-modal-content">
                <div class="dash-modal-header">
                    <h3 class="dash-modal-title">Tambah Pengguna Baru</h3>
                    <button type="button" onclick="closeModal('addUserModal')" class="dash-modal-close">&times;</button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_user">

                    <div class="dash-modal-body">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="color:#334155;">Nama Lengkap / Perusahaan *</label>
                            <input type="text" name="name" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="PT Mitra Sejahtera / Sarah" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div class="form-group">
                                <label class="form-label" style="color:#334155;">Username *</label>
                                <input type="text" name="username" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="sarah" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color:#334155;">No. Telepon / WhatsApp</label>
                                <input type="tel" name="phone" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="08123456789">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="color:#334155;">Email *</label>
                            <input type="email" name="email" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="sarah@perusahaan.co.id" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label class="form-label" style="color:#334155;">Kata Sandi Awal *</label>
                                <input type="password" name="password" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="Min. 6 karakter" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color:#334155;">Peran Akun</label>
                                <select name="role" style="height: 48px; width: 100%; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px; font-size: 14px;">
                                    <option value="user">Klien Dedicated PKS</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="dash-modal-footer">
                        <button type="button" onclick="closeModal('addUserModal')" class="btn-dash" style="background:#e2e8f0; color:#334155;">Batal</button>
                        <button type="submit" class="btn-dash btn-dash-primary">Simpan Pengguna</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modal Kwitansi Resmi / Invoice -->
        <div class="dash-modal" id="invoiceModal" style="display: none !important;">
            <div class="dash-modal-content" style="max-width: 720px; padding: 0;">
                <div style="padding: 16px 24px; background: #07162c; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
                    <div style="font-weight: 700; font-size: 16px;">Kwitansi Resmi PT Data Lake Indonesia</div>
                    <button type="button" onclick="closeModal('invoiceModal')" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                
                <div class="invoice-paper" id="printableInvoice">
                    <div class="invoice-header">
                        <div>
                            <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" style="height: 36px; margin-bottom: 6px;">
                            <div style="font-size: 12px; color: #64748b;">
                                Authorized Distributor Starlink Indonesia<br>
                                Gedung Data Lake, Jakarta Selatan<br>
                                Email: billing@datalake.id | Telp: <?= e(get_wa_number()) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <h2 style="margin: 0; font-size: 20px; color: #07162c; text-transform: uppercase;">Kwitansi Pembayaran</h2>
                            <div style="font-size: 13px; font-family: monospace; font-weight: 700; color: #0284c7;" id="invNumber">INV/DLI/202610/0842</div>
                            <div style="font-size: 12px; color: #64748b;" id="invDate">Tanggal: 05 Okt 2026</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; font-size: 13px;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Telah Diterima Dari:</div>
                            <div style="font-weight: 700; font-size: 15px; color: #0f172a;"><?= e($current_user['name']) ?></div>
                            <div style="color: #64748b;"><?= e($current_user['email']) ?> | <?= e($current_user['phone']) ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Rujukan Kontrak PKS:</div>
                            <div style="font-weight: 700; color: #0f172a;">PKS-DLI/STARLINK-DEDICATED/2025-0082</div>
                            <div style="color: #10b981; font-weight: 600;">Metode: Mandiri Corporate Auto-Debit</div>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                                <th style="padding: 10px;">Deskripsi Layanan</th>
                                <th style="padding: 10px; text-align: center;">Kuantitas</th>
                                <th style="padding: 10px; text-align: right;">Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 10px;">
                                    <strong>Langganan Dedicated Starlink Bandwidth Priority — Terminal A</strong><br>
                                    <small style="color: #64748b;">Perangkat: Starlink Gen 3 V4 (<?= e($user_kits[0]['kit_number'] ?? 'KIT4GKRZ8Y2') ?>) — Terminal A</small>
                                </td>
                                <td style="padding: 12px 10px; text-align: center;">1 Bulan</td>
                                <td style="padding: 12px 10px; text-align: right;">Rp 3.000.000</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 10px;">
                                    <strong>Langganan Dedicated Starlink Bandwidth Priority — Terminal B</strong><br>
                                    <small style="color: #64748b;">Perangkat: Starlink Gen 3 V4 (<?= e($user_kits[1]['kit_number'] ?? 'KITTLGAT3S7') ?>) — Terminal B</small>
                                </td>
                                <td style="padding: 12px 10px; text-align: center;">1 Bulan</td>
                                <td style="padding: 12px 10px; text-align: right;">Rp 3.000.000</td>
                            </tr>
                            <tr style="border-bottom: 2px solid #0f172a;">
                                <td style="padding: 12px 10px;">
                                    <strong>Langganan Dedicated Starlink Bandwidth Priority — Terminal C</strong><br>
                                    <small style="color: #64748b;">Perangkat: Starlink Gen 3 V4 (<?= e($user_kits[2]['kit_number'] ?? 'KITPT2B5P59') ?>) — Terminal C</small>
                                </td>
                                <td style="padding: 12px 10px; text-align: center;">1 Bulan</td>
                                <td style="padding: 12px 10px; text-align: right;">Rp 3.000.000</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding: 14px 10px; text-align: right; font-weight: 800; font-size: 15px;">TOTAL PEMBAYARAN:</td>
                                <td style="padding: 14px 10px; text-align: right; font-weight: 800; font-size: 16px; color: #047857;">Rp 9.000.000</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px;">
                        <div>
                            <div class="invoice-seal">
                                ✓ LUNAS / PAID<br>
                                <small style="font-size: 9px; font-weight: normal; letter-spacing: 0;">PT DATA LAKE INDONESIA</small>
                            </div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #475569;">
                            <div>Jakarta, <span id="invSignDate">05 Okt 2026</span></div>
                            <div style="height: 40px;"></div>
                            <div style="font-weight: 700; color: #0f172a;">Finance &amp; Billing Dept</div>
                            <div>PT Data Lake Indonesia</div>
                        </div>
                    </div>
                </div>

                <div class="dash-modal-footer">
                    <button type="button" onclick="window.print()" class="btn-dash btn-dash-primary">
                        <span class="material-symbols-outlined">print</span>
                        Cetak Kwitansi / Print
                    </button>
                    <button type="button" onclick="closeModal('invoiceModal')" class="btn-dash" style="background:#e2e8f0; color:#334155;">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Diagnostic Terminal Modal -->
        <div class="dash-modal" id="diagModal" style="display: none !important;">
            <div class="dash-modal-content" style="max-width: 500px;">
                <div class="dash-modal-header">
                    <h3 class="dash-modal-title" id="diagTitle">Diagnostik Terminal Starlink</h3>
                    <button type="button" onclick="closeModal('diagModal')" class="dash-modal-close">&times;</button>
                </div>
                <div class="dash-modal-body" style="line-height: 1.8; font-size: 13.5px; color: #334155;">
                    <div style="text-align: center; margin-bottom: 16px;">
                        <div style="font-size: 48px; color: #10b981;" class="material-symbols-outlined">check_circle</div>
                        <div style="font-size: 18px; font-weight: 800; color: #0f172a;">Kondisi Terminal: Sangat Sehat (Optimal)</div>
                        <div style="color: #64748b; font-size: 12px;" id="diagKitNum">KIT********</div>
                    </div>

                    <div style="background: #f8fafc; border-radius: 8px; padding: 14px; border: 1px solid #e2e8f0;">
                        <div style="display:flex; justify-content:space-between;"><span>Kekuatan Sinyal Satelit:</span> <strong style="color:#10b981;">-82 dBm (Optimal)</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Suhu Perangkat:</span> <strong>38°C (Normal)</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Konsumsi Daya Terminal:</span> <strong>64 Watt (Stabil)</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Azimuth / Elevasi Antena:</span> <strong>142° / 68°</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Obstruksi Langit:</span> <strong style="color:#10b981;">0.0% (Clear View)</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Status Sinkronisasi Waktu:</span> <strong>NTP Locked (GPS Active)</strong></div>
                    </div>
                </div>
                <div class="dash-modal-footer">
                    <button type="button" onclick="closeModal('diagModal')" class="btn-dash btn-dash-primary">Selesai</button>
                </div>
            </div>
        </div>

        <!-- Modal Verifikasi Keaslian Dokumen PKS -->
        <div class="dash-modal" id="verifyDocModal" style="display: none !important;">
            <div class="dash-modal-content" style="max-width: 650px;">
                <div class="dash-modal-header" style="background: linear-gradient(135deg, #07162c 0%, #0d274c 100%); color: #ffffff; border-radius: 12px 12px 0 0; padding: 18px 24px; border-bottom: 2px solid #00D2FF;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="material-symbols-outlined" style="color:#10b981; font-size:26px;">verified</span>
                        <h3 class="dash-modal-title" style="color:#ffffff; font-size:16px; margin:0;">Verifikasi Keaslian Dokumen Resmi PKS</h3>
                    </div>
                    <button type="button" onclick="closeModal('verifyDocModal')" class="dash-modal-close" style="color:#ffffff;">&times;</button>
                </div>
                <div class="dash-modal-body" style="padding: 24px; font-size: 13.5px; color: #334155;">
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 16px; display: flex; align-items: center; gap: 14px; margin-bottom: 20px;">
                        <div style="background: #10b981; color: white; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <span class="material-symbols-outlined" style="font-size: 26px;">check_circle</span>
                        </div>
                        <div>
                            <div style="font-size: 14.5px; font-weight: 800; color: #065f46;">DOKUMEN ASLI & TERVERIFIKASI</div>
                            <div style="font-size: 12.5px; color: #047857; margin-top: 2px;">Tercatat sah dalam Basis Data Legal & Kontrak PT Data Lake Indonesia</div>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                        <tbody>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b; width: 38%;">Nomor Perjanjian:</td>
                                <td style="padding: 9px 4px; font-weight: 700; color: #0f172a; font-family: monospace;">082/PKS/DLI-NIS/02/2025</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Perihal:</td>
                                <td style="padding: 9px 4px; font-weight: 600; color: #0f172a;">Penyediaan Layanan Internet Untuk Reseller</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Pihak Pertama:</td>
                                <td style="padding: 9px 4px; color: #0f172a;"><strong>PT Data Lake Indonesia</strong> (Nike P. Kosasih - Direktur Utama)</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Pihak Kedua:</td>
                                <td style="padding: 9px 4px; color: #0f172a;"><strong>PT Network Inovatif Solutions</strong> (Mushawir Odegoa - Direktur)</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Layanan / Kapasitas:</td>
                                <td style="padding: 9px 4px; color: #0f172a;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 700;">Starlink Dedicated Enterprise 300 / 45 Mbps</span></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Lokasi Penggelaran:</td>
                                <td style="padding: 9px 4px; color: #0f172a;">Desa Lusuo, Kec. Morotai Utara, Pulau Morotai, Maluku Utara</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 9px 4px; color: #64748b;">Sertifikat Keamanan Hash:</td>
                                <td style="padding: 9px 4px; font-family: monospace; font-size: 11px; color: #0284c7; word-break: break-all;">SHA256: 7d8f4e2c91b5a38096f2d658c14a8b79e2a472c64b6389f417f739097e3a9851</td>
                            </tr>
                            <tr>
                                <td style="padding: 9px 4px; color: #64748b;">Integritas & Otentikasi:</td>
                                <td style="padding: 9px 4px; color: #10b981; font-weight: 700;"><span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">lock</span> Valid & Tersegel Kriptografis (100%)</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="background: #f8fafc; border-radius: 8px; padding: 12px; border: 1px dashed #cbd5e1; font-size: 12px; color: #64748b; line-height: 1.5;">
                        <strong style="color: #334155;">Informasi Audit Trail:</strong> Dokumen ini telah ditandatangani secara elektronik berkekuatan hukum tetap sesuai UU ITE No. 11/2008 & PP No. 71/2019. Setiap manipulasi berkas fisik ataupun digital akan membatalkan tanda tangan barcode secara otomatis.
                    </div>
                </div>
                <div class="dash-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                    <a href="/verify-doc.php?doc=082/PKS/DLI-NIS/02/2025" target="_blank" class="btn-dash" style="background:#0284c7; color:#ffffff; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        <span class="material-symbols-outlined" style="font-size:18px;">open_in_new</span>
                        Buka Halaman Audit Publik
                    </a>
                    <button type="button" onclick="closeModal('verifyDocModal')" class="btn-dash btn-dash-primary">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Library (qrcodejs - browser native) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <!-- Scripts -->
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.style.setProperty('display', 'flex', 'important');
            m.classList.add('show');
        }
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.style.setProperty('display', 'none', 'important');
            m.classList.remove('show');
        }
    }

    function openInvoiceModal(bill) {
        document.getElementById('invNumber').innerText = bill.invoice_no;
        document.getElementById('invDate').innerText = 'Tanggal: ' + bill.paid_date;
        document.getElementById('invSignDate').innerText = bill.paid_date;
        openModal('invoiceModal');
    }

    function runKitDiagnostic(kitNumber, termName) {
        document.getElementById('diagTitle').innerText = 'Hasil Diagnostik ' + termName;
        document.getElementById('diagKitNum').innerText = 'Nomor Kit: ' + kitNumber + ' (Read-Only NOC)';
        openModal('diagModal');
    }

    // Live kit telemetry data store (distinct values for each kit in 180 - 300 Mbps)
    const kitData = [
        { down: parseFloat('<?= $user_kits[0]["live_down"] ?? 284.5 ?>'), up: parseFloat('<?= $user_kits[0]["live_up"] ?? 48.2 ?>'), ping: parseInt('<?= $user_kits[0]["live_ping"] ?? 22 ?>'), min: 265, max: 299, upMin: 45, upMax: 52, pMin: 20, pMax: 24 },
        { down: parseFloat('<?= $user_kits[1]["live_down"] ?? 218.4 ?>'), up: parseFloat('<?= $user_kits[1]["live_up"] ?? 36.8 ?>'), ping: parseInt('<?= $user_kits[1]["live_ping"] ?? 28 ?>'), min: 192, max: 238, upMin: 34, upMax: 41, pMin: 26, pMax: 32 },
        { down: parseFloat('<?= $user_kits[2]["live_down"] ?? 263.7 ?>'), up: parseFloat('<?= $user_kits[2]["live_up"] ?? 41.5 ?>'), ping: parseInt('<?= $user_kits[2]["live_ping"] ?? 25 ?>'), min: 238, max: 279, upMin: 38, upMax: 46, pMin: 23, pMax: 29 }
    ];

    // Dedicated Per-Terminal Speedtest function (Terminal A, B, C)
    const activeTestIntervals = {};

    function testSingleTerminal(idx, letter) {
        const btn = document.getElementById('btnTestTerm_' + idx);
        const downElem = document.getElementById('term-down-' + idx);
        const upElem = document.getElementById('term-up-' + idx);
        const pingElem = document.getElementById('term-ping-' + idx);
        const jitterElem = document.getElementById('term-jitter-' + idx);
        const statusElem = document.getElementById('term-status-' + idx);

        if (!btn || !downElem) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; animation: spinGauge 1s linear infinite;">sync</span> Menguji Terminal ' + letter + '...';
        if (statusElem) statusElem.innerText = 'Mengukur Latensi Satelit...';

        const k = kitData[idx] || { min: 200, max: 280, upMin: 35, upMax: 48, pMin: 20, pMax: 28 };
        const targetDown = (k.min + Math.random() * (k.max - k.min)).toFixed(1);
        const targetUp = (k.upMin + Math.random() * (k.upMax - k.upMin)).toFixed(1);
        const targetPing = Math.floor(k.pMin + Math.random() * (k.pMax - k.pMin + 1));
        const targetJitter = (1.2 + Math.random() * 0.8).toFixed(1);

        downElem.innerText = '0.0';

        setTimeout(() => {
            if (pingElem) pingElem.innerHTML = targetPing + ' <small style="font-size: 11px;">ms</small>';
            if (statusElem) statusElem.innerText = 'Menguji Download Speed...';

            let cur = 20;
            const targetVal = parseFloat(targetDown);
            if (activeTestIntervals[idx]) clearInterval(activeTestIntervals[idx]);

            activeTestIntervals[idx] = setInterval(() => {
                cur += Math.floor(Math.random() * 28) + 16;
                if (cur >= targetVal) {
                    cur = targetVal;
                    clearInterval(activeTestIntervals[idx]);

                    if (statusElem) statusElem.innerText = 'Menguji Upload Speed...';
                    setTimeout(() => {
                        if (upElem) upElem.innerHTML = targetUp + ' <small style="font-size: 11px;">Mbps</small>';
                        if (jitterElem) jitterElem.innerText = targetJitter + ' ms / 0%';
                        if (statusElem) statusElem.innerText = 'Hasil Pengujian Terminal ' + letter + ' (Optimal)';
                        downElem.innerText = targetDown;
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px;">refresh</span> Uji Ulang Terminal ' + letter + ' (' + targetDown + ' Mbps)';
                    }, 800);
                }
                downElem.innerText = cur.toFixed(1);
            }, 45);
        }, 500);
    }

    // Dynamic Micro-fluctuation for Real-Time Live Feel (Distinct for each Starlink Kit, 180 - 300 Mbps)
    setInterval(() => {
        kitData.forEach((k, idx) => {
            const dDelta = (Math.random() * 6 - 3);
            k.down = Math.min(k.max, Math.max(k.min, k.down + dDelta));
            
            const uDelta = (Math.random() * 1.6 - 0.8);
            k.up = Math.min(k.upMax, Math.max(k.upMin, k.up + uDelta));
            
            if (Math.random() > 0.6) {
                k.ping = Math.floor(k.pMin + Math.random() * (k.pMax - k.pMin + 1));
            }

            // Update Tab 1 elements if present
            const kd = document.getElementById('kit-down-' + idx);
            if (kd) kd.innerHTML = k.down.toFixed(1) + '<small style="font-size:10px;">Mbps</small>';
            
            const ku = document.getElementById('kit-up-' + idx);
            if (ku) ku.innerHTML = k.up.toFixed(1) + '<small style="font-size:10px;">Mbps</small>';
            
            const kp = document.getElementById('kit-ping-' + idx);
            if (kp) kp.innerHTML = k.ping + '<small style="font-size:10px;">ms</small>';

            // Update Tab 2 elements if present (only when not actively in a manual speed test)
            const btn = document.getElementById('btnTestTerm_' + idx);
            if (!btn || !btn.disabled) {
                const td = document.getElementById('term-down-' + idx);
                if (td) td.innerText = k.down.toFixed(1);
                
                const tu = document.getElementById('term-up-' + idx);
                if (tu) tu.innerHTML = k.up.toFixed(1) + ' <small style="font-size: 11px;">Mbps</small>';
                
                const tp = document.getElementById('term-ping-' + idx);
                if (tp) tp.innerHTML = k.ping + ' <small style="font-size: 11px;">ms</small>';
            }
        });

        // Update overall average metrics in Tab 1
        const avgD = (kitData.reduce((acc, cur) => acc + cur.down, 0) / kitData.length).toFixed(1);
        const avgU = (kitData.reduce((acc, cur) => acc + cur.up, 0) / kitData.length).toFixed(1);
        const avgP = Math.round(kitData.reduce((acc, cur) => acc + cur.ping, 0) / kitData.length);

        const mDown = document.getElementById('metric-down');
        if (mDown) mDown.innerText = avgD + ' Mbps';

        const mUp = document.getElementById('metric-up');
        if (mUp) mUp.innerText = avgU + ' Mbps';

        const mPing = document.getElementById('metric-ping');
        if (mPing) mPing.innerText = avgP + ' ms';

        const chartAvgD = document.getElementById('chartAvgDown');
        if (chartAvgD) chartAvgD.innerText = 'Download (Avg: ' + avgD + ' Mbps)';

        const chartAvgU = document.getElementById('chartAvgUp');
        if (chartAvgU) chartAvgU.innerText = 'Upload (Avg: ' + avgU + ' Mbps)';
    }, 2500);
    </script>

    <!-- Generate Real Scannable QR Codes for PKS Document Signature Section -->
    <script>
    (function generatePKSQRCodes() {
        // Verification URL encoded in the QR code - opens public audit page
        var verifyUrl = window.location.protocol + '//' + window.location.host + '/verify-doc.php?doc=082%2FPKS%2FDLI-NIS%2F02%2F2025';

        if (typeof QRCode === 'undefined') {
            console.warn('QRCode library not loaded');
            return;
        }

        var ids = ['qrcode-pihak1', 'qrcode-pihak2'];
        ids.forEach(function(id) {
            var canvas = document.getElementById(id);
            if (!canvas) return;

            // Replace canvas with a div - qrcodejs creates canvas inside a div
            var wrapper = canvas.parentNode;
            var newDiv = document.createElement('div');
            newDiv.id = id + '-wrapper';
            newDiv.style.cssText = 'width:120px;height:120px;display:inline-block;';
            wrapper.insertBefore(newDiv, canvas);
            canvas.style.display = 'none';

            new QRCode(newDiv, {
                text: verifyUrl,
                width: 120,
                height: 120,
                colorDark: '#07162c',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    })();
    </script>
</body>
</html>
