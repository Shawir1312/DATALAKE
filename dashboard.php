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

$tab = $_GET['tab'] ?? 'overview';
$error = get_flash('error');
$success = get_flash('success');

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
                    log_activity('Admin Add User', "Menambahkan user baru: $username ($role)");
                    set_flash('success', "Pengguna '$username' berhasil ditambahkan.");
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
                $_SESSION['user_name'] = $name;
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
}

// Fetch stats
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
    <title>Admin Dashboard | PT Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <!-- Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar Navigation -->
    <aside class="dash-sidebar" id="sidebar">
        <div class="dash-brand">
            <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="dash-brand-logo">
            <div class="dash-brand-text">Admin Panel</div>
        </div>

        <div class="dash-user-profile">
            <div class="dash-avatar">
                <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
            </div>
            <div class="dash-user-info">
                <div class="dash-user-name"><?= e($current_user['name']) ?></div>
                <span class="dash-role-badge"><?= e($current_user['role']) ?></span>
            </div>
        </div>

        <nav class="dash-nav">
            <div class="dash-nav-header">Menu Utama</div>
            <a href="dashboard.php?tab=overview" class="dash-nav-link <?= $tab === 'overview' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">dashboard</span>
                Ringkasan
            </a>

            <?php if ($is_admin): ?>
                <a href="dashboard.php?tab=users" class="dash-nav-link <?= $tab === 'users' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">group</span>
                    Kelola Pengguna
                    <span class="badge"><?= $totalUsers ?></span>
                </a>
                <a href="dashboard.php?tab=inquiries" class="dash-nav-link <?= $tab === 'inquiries' ? 'active' : '' ?>">
                    <span class="material-symbols-outlined">contact_support</span>
                    Leads & Konsultasi
                    <?php if ($newInquiries > 0): ?>
                        <span class="badge"><?= $newInquiries ?> baru</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

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
                        case 'profile': echo 'Pengaturan Profil & Keamanan'; break;
                        default: echo 'Ringkasan Dasbor Eksekutif'; break;
                    }
                    ?>
                </h1>
            </div>

            <div class="dash-topbar-actions">
                <div class="system-status-indicator">
                    <span class="status-dot"></span>
                    <span>Sistem Terhubung (<?= strtoupper(DB_TYPE) ?>)</span>
                </div>
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

            <!-- TAB 1: OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
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
                            <span class="stat-label">Versi Server & PHP</span>
                            <span class="stat-value">PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
                            <span class="stat-note">PDO Driver Siap</span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-amber">
                            <span class="material-symbols-outlined">dns</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Inquiries Section -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--accent-cyan-dark);">contact_mail</span>
                            Permintaan Konsultasi & Leads Terbaru
                        </h2>
                        <?php if ($is_admin): ?>
                            <a href="dashboard.php?tab=inquiries" class="btn-dash btn-dash-primary btn-dash-sm">
                                Lihat Semua Leads
                            </a>
                        <?php endif; ?>
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

            <!-- TAB 2: USERS MANAGEMENT (Admin Only) -->
            <?php elseif ($tab === 'users' && $is_admin): ?>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--primary-dli);">manage_accounts</span>
                            Daftar Pengguna & Admin (<?= count($usersList) ?>)
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
                                        <th>ID</th>
                                        <th>Nama Lengkap & Username</th>
                                        <th>Email & HP</th>
                                        <th>Peran</th>
                                        <th>Status</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usersList as $u): ?>
                                        <tr>
                                            <td>#<?= $u['id'] ?></td>
                                            <td>
                                                <div style="font-weight: 700; color: var(--primary-dli);"><?= e($u['name']) ?></div>
                                                <small style="color: #64748b;">@<?= e($u['username']) ?></small>
                                            </td>
                                            <td>
                                                <div><?= e($u['email']) ?></div>
                                                <small style="color: #64748b;"><?= e($u['phone'] ?: '-') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-<?= $u['role'] === 'admin' ? 'admin' : 'user' ?>">
                                                    <?= e($u['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>">
                                                    <?= e($u['status']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 12px; color: #64748b;"><?= e($u['created_at']) ?></td>
                                            <td>
                                                <?php if ($u['id'] !== $current_user_id): ?>
                                                    <div style="display: flex; gap: 6px;">
                                                        <!-- Toggle status form -->
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                                            <input type="hidden" name="status" value="<?= e($u['status']) ?>">
                                                            <button type="submit" class="btn-dash <?= $u['status'] === 'active' ? 'btn-dash-danger' : 'btn-dash-accent' ?> btn-dash-sm" 
                                                                    title="<?= $u['status'] === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>">
                                                                <span class="material-symbols-outlined" style="font-size: 14px;">
                                                                    <?= $u['status'] === 'active' ? 'block' : 'check' ?>
                                                                </span>
                                                            </button>
                                                        </form>

                                                        <!-- Delete user form -->
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini? Tindakan ini tidak dapat dibatalkan.');">
                                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="btn-dash btn-dash-danger btn-dash-sm" title="Hapus Pengguna">
                                                                <span class="material-symbols-outlined" style="font-size: 14px;">delete</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <small style="color: #64748b; font-style: italic;">(Akun Anda)</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB 3: INQUIRIES MANAGEMENT (Admin Only) -->
            <?php elseif ($tab === 'inquiries' && $is_admin): ?>
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">
                            <span class="material-symbols-outlined" style="color: var(--accent-cyan-dark);">chat</span>
                            Semua Permintaan Konsultasi & Pesanan Starlink (<?= count($inquiriesList) ?>)
                        </h2>
                    </div>
                    <div class="dash-card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Pemohon</th>
                                        <th>Kontak</th>
                                        <th>Paket & Lokasi</th>
                                        <th>Pesan</th>
                                        <th>Status Saat Ini</th>
                                        <th>Ubah Status & Kontak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inquiriesList as $inq): ?>
                                        <tr>
                                            <td style="font-size: 12px; color: #64748b; white-space: nowrap;"><?= e($inq['created_at']) ?></td>
                                            <td style="font-weight: 700;"><?= e($inq['name']) ?></td>
                                            <td>
                                                <div><?= e($inq['email']) ?></div>
                                                <small style="color: #64748b;"><?= e($inq['phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-user"><?= e($inq['service_package']) ?></span>
                                                <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?= e($inq['location'] ?: '-') ?></div>
                                            </td>
                                            <td style="max-width: 250px; font-size: 13px;"><?= nl2br(e($inq['message'] ?: '-')) ?></td>
                                            <td>
                                                <span class="badge-pill badge-<?= e($inq['status']) ?>">
                                                    <?= e($inq['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="update_inquiry">
                                                    <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                    <select name="status" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px;">
                                                        <option value="new" <?= $inq['status'] === 'new' ? 'selected' : '' ?>>Baru</option>
                                                        <option value="contacted" <?= $inq['status'] === 'contacted' ? 'selected' : '' ?>>Dihubungi</option>
                                                        <option value="resolved" <?= $inq['status'] === 'resolved' ? 'selected' : '' ?>>Selesai</option>
                                                    </select>
                                                    <button type="submit" class="btn-dash btn-dash-primary btn-dash-sm">Update</button>
                                                    <?php if (!empty($inq['phone'])): ?>
                                                        <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $inq['phone']) ?>&text=Halo%20<?= urlencode($inq['name']) ?>%2C%20kami%20dari%20PT%20Data%20Lake%20Indonesia%20ingin%20menindaklanjuti%20permintaan%20Starlink%20Anda." 
                                                           target="_blank" class="btn-dash btn-dash-accent btn-dash-sm" title="Chat WhatsApp">
                                                            WA
                                                        </a>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB 4: PROFILE & SECURITY SETTINGS -->
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
                                    <label class="form-label" style="color: #334155;">Nama Lengkap</label>
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
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal: Add User (Admin Only) -->
    <?php if ($is_admin): ?>
    <div class="dash-modal-backdrop" id="addUserModal">
        <div class="dash-modal">
            <div class="dash-modal-header">
                <h3 class="dash-modal-title">Tambah Pengguna Baru</h3>
                <button type="button" onclick="closeModal('addUserModal')" style="cursor: pointer; background:none; border:none; color:#64748b;">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST">
                <div class="dash-modal-body">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_user">

                    <div class="form-group">
                        <label class="form-label" style="color:#334155;">Nama Lengkap *</label>
                        <input type="text" name="name" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="Contoh: Sarah Connor" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label" style="color:#334155;">Username *</label>
                            <input type="text" name="username" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="sarah" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#334155;">Nomor Telepon</label>
                            <input type="tel" name="phone" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="08123456789">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="color:#334155;">Alamat Email *</label>
                        <input type="email" name="email" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="sarah@datalake.id" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label" style="color:#334155;">Kata Sandi Awal *</label>
                            <input type="password" name="password" class="form-input" style="color:#0f172a; border-color:#cbd5e1; padding-left:14px;" placeholder="Min. 6 karakter" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color:#334155;">Peran Akun</label>
                            <select name="role" style="height: 48px; width: 100%; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px; font-size: 14px;">
                                <option value="user">User Biasa</option>
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

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }

    function openModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    </script>
</body>
</html>
