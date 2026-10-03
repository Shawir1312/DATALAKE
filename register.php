<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'form';

// Action: Cancel OTP / reset
if (isset($_GET['action']) && $_GET['action'] === 'reset_otp') {
    unset($_SESSION['pending_reg']);
    header("Location: register.php");
    exit;
}

// Action: Resend OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend_otp') {
    if (!empty($_SESSION['pending_reg'])) {
        $new_otp = (string)random_int(100000, 999999);
        $_SESSION['pending_reg']['otp'] = $new_otp;
        $_SESSION['pending_reg']['expires'] = time() + 300;
        $success = "Kode OTP baru telah dikirimkan ke nomor WhatsApp Anda.";
        $step = 'otp';
    } else {
        header("Location: register.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = "Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $form_action = $_POST['form_action'] ?? 'register';

        if ($form_action === 'verify_otp') {
            // Processing OTP Verification
            $entered_otp = trim($_POST['otp'] ?? '');
            $pending = $_SESSION['pending_reg'] ?? null;

            if (!$pending) {
                $error = "Data pendaftaran tidak ditemukan atau sesi telah berakhir. Silakan daftar kembali.";
                $step = 'form';
            } elseif (time() > $pending['expires']) {
                $error = "Kode OTP telah kedaluwarsa. Silakan klik 'Kirim Ulang OTP'.";
                $step = 'otp';
            } elseif ($entered_otp !== $pending['otp']) {
                $error = "Kode OTP salah. Pastikan Anda memasukkan 6 digit kode yang benar.";
                $step = 'otp';
            } else {
                // OTP is correct! Create user account in database
                $pdo = get_db_connection();

                // Double check if username or email was taken in the meantime
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkStmt->execute([$pending['username'], $pending['email']]);
                if ($checkStmt->fetch()) {
                    $error = "Username atau email tersebut sudah terdaftar.";
                    unset($_SESSION['pending_reg']);
                    $step = 'form';
                } else {
                    $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
                    $totalUsers = (int)$countStmt->fetchColumn();
                    $role = ($totalUsers === 0) ? 'admin' : 'user';

                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $timeFunc = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (name, username, email, phone, password, role, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'active', $timeFunc)
                    ");
                    $insertStmt->execute([
                        $pending['name'],
                        $pending['username'],
                        $pending['email'],
                        $pending['phone'],
                        $pending['password'],
                        $role
                    ]);

                    $new_user_id = (int)$pdo->lastInsertId();

                    // Automatically initialize 3 Starlink Gen 3 V4 Kits (KIT********)
                    get_user_starlink_kits($new_user_id);

                    log_activity('User Registration via OTP', "Pengguna terverifikasi OTP: {$pending['username']} ({$pending['email']})");

                    unset($_SESSION['pending_reg']);

                    // Automatically log in the user
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['username'] = $pending['username'];
                    $_SESSION['role'] = $role;
                    $_SESSION['name'] = $pending['name'];

                    set_flash('success', "Selamat datang, {$pending['name']}! Akun Dedicated PKS Starlink Enterprise Anda aktif.");
                    header("Location: dashboard.php");
                    exit;
                }
            }
        } else {
            // Step 1: Processing Registration Form
            $name = trim($_POST['name'] ?? '');
            $username = trim(strtolower($_POST['username'] ?? ''));
            $email = trim(strtolower($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($name) || empty($username) || empty($email) || empty($password)) {
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
                    // Generate 6-digit OTP
                    $otp = (string)random_int(100000, 999999);
                    $_SESSION['pending_reg'] = [
                        'name' => $name,
                        'username' => $username,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => password_hash($password, PASSWORD_BCRYPT),
                        'otp' => $otp,
                        'expires' => time() + 300 // 5 minutes
                    ];

                    $step = 'otp';
                }
            }
        }
    }
}

if ($step === 'otp' && empty($_SESSION['pending_reg'])) {
    $step = 'form';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $step === 'otp' ? 'Verifikasi OTP' : 'Daftar Akun' ?> | Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    
    <!-- Base DLI Styles -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">
    <link rel="stylesheet" href="/assets/css/custom-auth.css">
    <style>
        .otp-input-wrap {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-code-input {
            width: 100%;
            max-width: 320px;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 12px;
            text-align: center;
            padding: 14px 20px;
            color: #00D2FF;
            background: #040b1a;
            border: 2px solid #00D2FF;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 210, 255, 0.25);
            outline: none;
            transition: all 0.2s ease;
        }
        .otp-code-input:focus {
            box-shadow: 0 0 30px rgba(0, 210, 255, 0.45);
            border-color: #38bdf8;
        }
        .otp-sim-banner {
            background: linear-gradient(135deg, rgba(37, 211, 102, 0.12) 0%, rgba(16, 185, 129, 0.08) 100%);
            border: 1px solid rgba(37, 211, 102, 0.4);
            border-radius: 12px;
            padding: 14px 18px;
            margin: 18px 0;
            text-align: left;
        }
        .otp-sim-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            color: #10b981;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .otp-sim-code {
            font-size: 22px;
            font-weight: 800;
            color: #047857;
            letter-spacing: 4px;
            background: #ffffff;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
            border: 1px dashed #10b981;
            margin: 4px 0;
        }
    </style>
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
            
            <?php if ($step === 'otp'): ?>
                <h1 class="auth-title">Verifikasi Kode OTP</h1>
                <p class="auth-sub">Masukkan 6 digit kode verifikasi untuk mengaktifkan akun Dedicated PKS Anda</p>
            <?php else: ?>
                <h1 class="auth-title">Daftar Akun Baru</h1>
                <p class="auth-sub">Akses Portal Klien Dedicated PKS Starlink Enterprise Indonesia</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:12px 16px; border-radius:8px; margin-bottom:18px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">check_circle</span>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'otp'): ?>
            <!-- STEP 2: VERIFIKASI OTP -->
            <?php $pending = $_SESSION['pending_reg'] ?? []; ?>
            
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:16px; font-size:13px; color:#475569; text-align:center;">
                Kode OTP telah dikirimkan ke WhatsApp: <strong><?= e($pending['phone'] ?? '-') ?></strong> dan Email: <strong><?= e($pending['email'] ?? '-') ?></strong>
            </div>

            <!-- Simulasi Notifikasi Gateway WhatsApp / SMS -->
            <div class="otp-sim-banner">
                <div class="otp-sim-badge">
                    <span class="material-symbols-outlined" style="font-size: 16px;">verified_user</span>
                    Gateway WhatsApp Data Lake Indonesia
                </div>
                <div style="font-size: 13px; color: #1e293b;">
                    Kode OTP Verifikasi Pendaftaran Anda:
                </div>
                <div class="otp-sim-code"><?= e($pending['otp'] ?? '') ?></div>
                <div style="font-size: 11.5px; color: #64748b;">
                    Berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.
                </div>
            </div>

            <form action="register.php?step=otp" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="form_action" value="verify_otp">

                <div class="form-group" style="text-align: center;">
                    <label for="otp" class="form-label" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Masukkan 6 Digit Kode OTP</label>
                    <div class="otp-input-wrap">
                        <input type="text" id="otp" name="otp" class="otp-code-input" 
                               maxlength="6" placeholder="••••••" pattern="[0-9]{6}" 
                               required autofocus autocomplete="one-time-code">
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit" style="background: linear-gradient(135deg, #00D2FF 0%, #0099cc 100%); color:#040b1a; font-weight:800;">
                    <span class="material-symbols-outlined" style="font-size: 20px;">verified</span>
                    Verifikasi OTP &amp; Masuk ke Dasbor
                </button>
            </form>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px; font-size:12.5px;">
                <a href="register.php?action=resend_otp" class="auth-link">
                    <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">replay</span>
                    Kirim Ulang OTP
                </a>
                <a href="register.php?action=reset_otp" style="color:#ef4444; text-decoration:none;">
                    Ubah Nomor / Data
                </a>
            </div>

        <?php else: ?>
            <!-- STEP 1: FORM PENDAFTARAN -->
            <form action="register.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="form_action" value="register">

                <div class="form-group">
                    <label for="name" class="form-label">Nama Lengkap / Perusahaan PKS *</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">badge</span>
                        <input type="text" id="name" name="name" class="form-input" 
                               placeholder="Contoh: PT Pratama Nusantara / Budi Hartono" 
                               value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="username" class="form-label">Username *</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined input-icon">person</span>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="klien_pks" 
                                   value="<?= e($_POST['username'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Nomor WhatsApp / HP *</label>
                        <div class="input-wrapper">
                            <span class="material-symbols-outlined input-icon">call</span>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   placeholder="08123456789" 
                                   value="<?= e($_POST['phone'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Alamat Email *</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">mail</span>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="admin@perusahaan.co.id" 
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

                <div style="background: rgba(0, 210, 255, 0.06); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 8px; padding: 10px 14px; margin: 4px 0 16px; font-size: 12px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="font-size: 18px; color: #0284c7;">verified_user</span>
                    <span>Kode verifikasi OTP 6-digit akan dikirimkan ke nomor WhatsApp Anda untuk keamanan akses PKS.</span>
                </div>

                <button type="submit" class="btn-auth-submit">
                    <span class="material-symbols-outlined" style="font-size: 20px;">send</span>
                    Lanjut ke Verifikasi OTP
                </button>
            </form>

            <div class="auth-footer">
                Sudah memiliki akun? <a href="login.php" class="auth-link">Masuk di sini</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
