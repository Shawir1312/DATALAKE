<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = "Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (empty($login_input) || empty($password)) {
        $error = "Silakan masukkan username/email dan password.";
    } else {
        $pdo = get_db_connection();
        // Allow login by either username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Akun Anda saat ini tidak aktif. Silakan hubungi administrator.";
            } else {
                // Success: store session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                // Update last login
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                $updateTime = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = $updateTime WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Log activity
                log_activity('User Login', 'Pengguna berhasil masuk ke sistem');

                set_flash('success', 'Selamat datang kembali, ' . $user['name'] . '!');
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Kombinasi username/email atau password tidak sesuai.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    
    <!-- Base DLI Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/custom-auth.css">
</head>
<body class="auth-page">

    <div class="auth-card">
        <a href="/" class="back-home-link">
            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
            Kembali ke Beranda
        </a>

        <div class="auth-header">
            <div class="auth-logo">
                <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia">
            </div>
            <h1 class="auth-title">Portal Pelanggan & Admin</h1>
            <p class="auth-sub">Masuk untuk mengelola layanan Starlink Anda atau dasbor administrator</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="material-symbols-outlined" style="font-size: 20px;">check_circle</span>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

            <div class="form-group">
                <label for="login_input" class="form-label">Username atau Email</label>
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">person</span>
                    <input type="text" id="login_input" name="login_input" class="form-input" 
                           placeholder="admin atau email@domain.com" 
                           value="<?= e($_POST['login_input'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <div class="form-row-between">
                    <label for="password" class="form-label">Kata Sandi</label>
                    <a href="https://api.whatsapp.com/send?phone=628170117800&text=Halo%20Data%20Lake%2C%20saya%20lupa%20kata%20sandi%20akun%20saya" target="_blank" class="auth-link" style="font-size: 12px;">Lupa Sandi?</a>
                </div>
                <div class="input-wrapper">
                    <span class="material-symbols-outlined input-icon">lock</span>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="••••••••" required>
                </div>
            </div>

            <div class="form-row-between" style="margin-top: -4px;">
                <label class="form-check">
                    <input type="checkbox" name="remember" checked>
                    <span>Ingat saya di perangkat ini</span>
                </label>
            </div>

            <button type="submit" class="btn-auth-submit">
                <span class="material-symbols-outlined" style="font-size: 20px;">login</span>
                Masuk ke Dasbor
            </button>
        </form>

        <div class="demo-credentials-box">
            <div class="demo-title">
                <span class="material-symbols-outlined" style="font-size: 16px;">key</span>
                Akun Demo Administrator:
            </div>
            <div>Username: <span class="demo-badge" onclick="fillDemo('admin', 'admin123')">admin</span></div>
            <div style="margin-top: 4px;">Password: <span class="demo-badge" onclick="fillDemo('admin', 'admin123')">admin123</span> <span style="font-size: 11px; opacity: 0.7;">(Klik untuk isi otomatis)</span></div>
        </div>

        <div class="auth-footer">
            Belum memiliki akun? <a href="register.php" class="auth-link">Daftar sekarang</a>
        </div>
    </div>

    <script>
    function fillDemo(username, password) {
        document.getElementById('login_input').value = username;
        document.getElementById('password').value = password;
    }
    </script>
</body>
</html>
