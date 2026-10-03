<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $username = trim(strtolower($_POST['username'] ?? ''));
    $email = trim(strtolower($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = "Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = "Harap isi semua kolom wajib yang ditandai bintang (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format alamat email tidak valid.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $error = "Username hanya boleh berupa huruf, angka, titik, atau garis bawah (3-30 karakter).";
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal terdiri dari 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi kata sandi tidak cocok.";
    } else {
        $pdo = get_db_connection();

        // Check if username or email already taken
        $checkStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['username'] === $username) {
                $error = "Username '$username' sudah terdaftar. Silakan pilih username lain.";
            } else {
                $error = "Alamat email '$email' sudah terdaftar. Silakan gunakan email lain atau masuk.";
            }
        } else {
            // Check count of users: if count is 0, make admin, else user
            $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
            $totalUsers = (int)$countStmt->fetchColumn();
            $role = ($totalUsers === 0) ? 'admin' : 'user';

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $timeFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, username, email, phone, password, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', $timeFunc)
            ");
            $insertStmt->execute([$name, $username, $email, $phone, $hashedPassword, $role]);

            // Auto-login or set flash and redirect to login
            log_activity('User Registration', "Pengguna baru terdaftar: $username ($email)");

            set_flash('success', 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.');
            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun | Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    
    <!-- Base DLI Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/custom-auth.css">
</head>
<body class="auth-page">

    <div class="auth-card" style="max-width: 520px;">
        <a href="/" class="back-home-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
            Kembali ke Beranda
        </a>

        <div class="auth-header">
            <div class="auth-logo">
                <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia">
            </div>
            <h1 class="auth-title">Daftar Akun Baru</h1>
            <p class="auth-sub">Bergabung dengan ekosistem konektivitas satelit Starlink terbaik di Indonesia</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap *</label>
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">badge</span>
                    <input type="text" id="name" name="name" class="form-input" 
                           placeholder="Contoh: Alexander Graham" 
                           value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label for="username" class="form-label">Username *</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">person</span>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="alexander" 
                               value="<?= e($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Nomor WhatsApp / HP</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">call</span>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="08123456789" 
                               value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Alamat Email *</label>
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">mail</span>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="nama@perusahaan.com" 
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div class="form-group">
                    <label for="password" class="form-label">Kata Sandi *</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">lock</span>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Min. 6 karakter" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Konfirmasi Sandi *</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">lock_reset</span>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Ulangi sandi" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-auth-submit">
                <span class="material-symbols-outlined" style="font-size: 20px;">person_add</span>
                Buat Akun Saya
            </button>
        </form>

        <div class="auth-footer">
            Sudah memiliki akun? <a href="login.php" class="auth-link">Masuk di sini</a>
        </div>
    </div>

</body>
</html>
