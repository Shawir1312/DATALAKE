<?php
require_once __DIR__ . '/includes/auth.php';

$order_success = false;
$order_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_store_order'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $order_error = "Sesi telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $name = trim($_POST['buyer_name'] ?? '');
        $phone = trim($_POST['buyer_phone'] ?? '');
        $email = trim($_POST['buyer_email'] ?? '');
        $product = trim($_POST['product_name'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 1);
        $address = trim($_POST['shipping_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name) || empty($phone) || empty($product)) {
            $order_error = "Harap isi nama, nomor telepon/WhatsApp, dan produk yang dipesan.";
        } else {
            try {
                $pdo = get_db_connection();
                $package_title = "Pesanan Store: $product (Qty: $qty)";
                $message_full = "Alamat Pengiriman:\n$address\n\nCatatan Tambahan:\n$notes";

                $stmt = $pdo->prepare("
                    INSERT INTO inquiries (name, email, phone, service_package, location, message, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'new')
                ");
                $stmt->execute([$name, $email, $phone, $package_title, $address, $message_full]);
                log_activity('New Store Order', "Pesanan baru dari $name: $product (Qty: $qty)");
                $order_success = true;
            } catch (Exception $e) {
                $order_error = "Gagal memproses pesanan: " . $e->getMessage();
            }
        }
    }
}

// Product catalog data
$products = [
    [
        'id' => 'kit-gen3',
        'name' => 'Starlink Standard Kit (Gen 3 V4)',
        'category' => 'kit',
        'price' => 'Rp 5.900.000',
        'price_num' => 5900000,
        'image' => '/content/personal/refresh/home/kit-card.jpg',
        'badge' => 'Terpopuler & Best Seller',
        'desc' => 'Perangkat resmi Starlink generasi terbaru dengan jangkauan Wi-Fi 6 ultra-cepat, sudut pandang lebih lebar, dan ketahanan cuaca IP67.',
        'specs' => ['Kecepatan hingga 250+ Mbps', 'Router Wi-Fi 6 Tri-Band', 'Kabel Starlink 15 Meter', 'Kickstand & Power Supply']
    ],
    [
        'id' => 'kit-mini',
        'name' => 'Starlink Mini Kit (Portabel)',
        'category' => 'kit',
        'price' => 'Rp 5.900.000',
        'price_num' => 5900000,
        'image' => '/content/personal/refresh/our-product/accessories/router-mini.webp',
        'badge' => 'Portabel & Ringkas',
        'desc' => 'Konektivitas satelit berukuran ransel laptop (berat hanya 1.1kg) dengan router Wi-Fi terintegrasi di dalam antena.',
        'specs' => ['Kecepatan hingga 100+ Mbps', 'Built-in Wi-Fi Router', 'Mendukung USB-PD 100W', 'Ideal untuk Camping & Mobil']
    ],
    [
        'id' => 'router-gen3',
        'name' => 'Router Wi-Fi 6 Starlink Gen 3',
        'category' => 'accessories',
        'price' => 'Rp 2.100.000',
        'price_num' => 2100000,
        'image' => '/content/personal/refresh/our-product/accessories/router-mini.webp',
        'badge' => 'Aksesoris Resmi',
        'desc' => 'Router pengganti atau penambah node Mesh Wi-Fi 6 untuk memperluas jangkauan sinyal di rumah besar atau kantor.',
        'specs' => ['Wi-Fi 6 Dual & Tri-Band', '2 Port Ethernet LAN RJ45', 'Mesh Compatible', 'Jangkauan hingga 297 m²']
    ],
    [
        'id' => 'adapter-ethernet',
        'name' => 'Starlink Ethernet Adapter Gigabit',
        'category' => 'accessories',
        'price' => 'Rp 750.000',
        'price_num' => 750000,
        'image' => '/content/personal/refresh/our-product/accessories/router-mini.webp',
        'badge' => 'Koneksi Kabel LAN',
        'desc' => 'Adapter resmi untuk menghubungkan kabel LAN (RJ45) dari router Starlink ke switch, PC, atau access point eksternal.',
        'specs' => ['Kecepatan 1000 Mbps (Gigabit)', 'Plug & Play instan', 'Original Starlink Certified', 'Kompatibel Switch & Mikrotik']
    ],
    [
        'id' => 'pivot-mount',
        'name' => 'Starlink Pivot Mount (Bracket Atap/Dinding)',
        'category' => 'mount',
        'price' => 'Rp 1.100.000',
        'price_num' => 1100000,
        'image' => '/content/personal/refresh/home/kit-card.jpg',
        'badge' => 'Bracket Kokoh',
        'desc' => 'Dudukan atap miring atau dinding vertikal yang dapat disesuaikan kemiringannya agar antena selalu menghadap langit bebas halangan.',
        'specs' => ['Bahan Aluminium Heavy Duty', 'Tahan Karat & Angin Kencang', 'Sudut Kemiringan Fleksibel', 'Termasuk Baut Fischer Baja']
    ],
    [
        'id' => 'pipe-adapter',
        'name' => 'Starlink Pipe Adapter (Bracket Tiang Universal)',
        'category' => 'mount',
        'price' => 'Rp 950.000',
        'price_num' => 950000,
        'image' => '/content/personal/refresh/home/kit-card.jpg',
        'badge' => 'Pemasangan Tiang',
        'desc' => 'Adaptor untuk memasang antena Starlink pada tiang antena TV atau pipa bulat yang sudah ada.',
        'specs' => ['Diameter pipa hingga 64 mm', 'Kunci Baut Ganda Anti Selip', 'Cat Tahan Korosi', 'Instalasi Cepat']
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Store | Data Lake Indonesia - Authorized Distributor Starlink</title>
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
        .store-hero {
            background-color: #040b1a;
            background-image: 
                radial-gradient(circle at 50% 10%, rgba(0, 210, 255, 0.2) 0%, rgba(10, 31, 54, 0.6) 40%, rgba(4, 11, 26, 0.95) 80%),
                url('/content/personal/refresh/home/indonesia-network-hero.png');
            background-size: cover;
            background-position: center;
            padding: var(--space-16) 0 var(--space-12);
            color: #ffffff;
            text-align: center;
            position: relative;
        }

        .store-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(0, 210, 255, 0.15);
            border: 1px solid rgba(0, 210, 255, 0.3);
            border-radius: var(--radius-full);
            color: var(--color-tertiary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: var(--space-4);
        }

        .store-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: var(--space-3);
            letter-spacing: -0.5px;
        }

        .store-sub {
            max-width: 650px;
            margin: 0 auto;
            color: rgba(255, 255, 255, 0.8);
            font-size: var(--text-body-lg);
            line-height: 1.6;
        }

        .store-nav-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: var(--space-8);
            flex-wrap: wrap;
        }

        .store-tab-btn {
            padding: 10px 20px;
            border-radius: var(--radius-full);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
            font-size: 13.5px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: all 0.2s;
        }

        .store-tab-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .store-tab-btn.active {
            background: var(--color-tertiary);
            color: #002230;
            border-color: var(--color-tertiary);
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(0, 210, 255, 0.35);
        }

        .store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: var(--space-8);
            padding: var(--space-12) 0 var(--space-20);
        }

        .product-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
            box-shadow: var(--shadow-ambient);
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-float);
            border-color: rgba(0, 210, 255, 0.4);
        }

        .product-image-wrap {
            position: relative;
            height: 220px;
            background: #07162c;
            overflow: hidden;
        }

        .product-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .product-card:hover .product-image-wrap img {
            transform: scale(1.05);
        }

        .product-badge-tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(4, 11, 26, 0.85);
            color: var(--color-tertiary);
            backdrop-filter: blur(8px);
            padding: 4px 10px;
            border-radius: var(--radius-md);
            font-size: 11px;
            font-weight: 700;
            border: 1px solid rgba(0, 210, 255, 0.3);
            text-transform: uppercase;
        }

        .product-body {
            padding: var(--space-6);
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            color: var(--color-primary);
            margin-bottom: var(--space-2);
            line-height: 1.3;
        }

        .product-desc {
            font-size: 13.5px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: var(--space-4);
            flex: 1;
        }

        .product-specs {
            margin-bottom: var(--space-4);
            padding: 10px;
            background: #f8fafc;
            border-radius: var(--radius-md);
            font-size: 12px;
            color: #334155;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .product-spec-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .product-price-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: var(--space-4);
            padding-top: var(--space-3);
            border-top: 1px solid #f1f5f9;
        }

        .product-price-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #94a3b8;
        }

        .product-price-val {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            color: var(--color-primary);
        }

        .product-actions {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 8px;
        }

        .btn-order-wa {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            background: #25d366;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-order-wa:hover {
            background: #20bd5a;
        }

        .btn-order-form {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            background: var(--color-primary);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: var(--radius-md);
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-order-form:hover {
            background: #254b80;
        }

        /* Order Modal */
        .order-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 11, 26, 0.7);
            backdrop-filter: blur(4px);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .order-modal-backdrop.show {
            display: flex;
        }

        .order-modal {
            background: #ffffff;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 520px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            animation: modalEnter 0.2s ease-out;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="nav-wrapper" data-astro-cid-m6gy25n3>
        <nav class="nav container" data-astro-cid-m6gy25n3>
            <a href="/" class="nav-logo" data-astro-cid-m6gy25n3>
                <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="nav-logo-img" width="120" height="36" decoding="async">
            </a>

            <div class="nav-links" data-astro-cid-m6gy25n3>
                <a href="/" class="nav-link">Beranda</a>
                <a href="/store.php" class="nav-link" style="color: var(--color-primary); font-weight: 700;">Store</a>
                <a href="/#produk" class="nav-link">Produk Kami</a>
                <a href="/#mengapa-kami" class="nav-link">Why Starlink</a>
                <a href="/#keunggulan" class="nav-link">Layanan</a>
                <a href="/#konsultasi" class="nav-link">Konsultasi</a>
            </div>

            <div class="nav-actions" data-astro-cid-m6gy25n3>
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="btn btn-primary btn-sm" style="padding: 6px 14px; font-size: 13px; border-radius: 999px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">dashboard</span>
                        Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outlined" style="padding: 6px 14px; font-size: 13px; border-radius: 999px;">Masuk</a>
                    <a href="register.php" class="btn btn-primary" style="padding: 6px 14px; font-size: 13px; border-radius: 999px;">Daftar</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Store Hero Banner -->
    <section class="store-hero">
        <div class="container">
            <div class="store-badge">
                <span class="material-symbols-outlined" style="font-size: 16px;">storefront</span>
                Official Web Store PT Data Lake Indonesia
            </div>
            <h1 class="store-title">Katalog Perangkat Starlink Resmi</h1>
            <p class="store-sub">
                Beli Starlink Gen 3 V4, Starlink Mini, dan aksesoris resmi bergaransi lokal Indonesia dengan pengiriman langsung ke seluruh wilayah nusantara.
            </p>

            <div class="store-nav-tabs">
                <button class="store-tab-btn active" onclick="filterProducts('all', this)">Semua Produk</button>
                <button class="store-tab-btn" onclick="filterProducts('kit', this)">Starlink Kit</button>
                <button class="store-tab-btn" onclick="filterProducts('accessories', this)">Router &amp; Kabel</button>
                <button class="store-tab-btn" onclick="filterProducts('mount', this)">Bracket &amp; Dudukan</button>
            </div>
        </div>
    </section>

    <!-- Main Store Content -->
    <main class="container">
        <?php if ($order_success): ?>
            <div style="margin-top: 24px; padding: 18px 24px; border-radius: 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-symbols-outlined" style="font-size: 28px; color: #10b981;">check_circle</span>
                    <div>
                        <div style="font-weight: 800; font-size: 16px;">Pesanan Anda Telah Berhasil Terkirim!</div>
                        <div style="font-size: 13.5px; opacity: 0.9;">Tim sales Data Lake Indonesia akan segera menghubungi nomor WhatsApp Anda untuk konfirmasi stok dan pengiriman.</div>
                    </div>
                </div>
                <a href="<?= get_wa_url('Halo, saya baru saja melakukan pemesanan di web store Data Lake Indonesia.') ?>" target="_blank" class="btn btn-primary" style="background:#25d366; border:none; padding:8px 18px;">
                    Hubungi via WhatsApp
                </a>
            </div>
        <?php endif; ?>

        <?php if ($order_error): ?>
            <div style="margin-top: 24px; padding: 16px 20px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined">error</span>
                <span><?= e($order_error) ?></span>
            </div>
        <?php endif; ?>

        <div class="store-grid">
            <?php foreach ($products as $p): ?>
                <div class="product-card" data-category="<?= e($p['category']) ?>">
                    <div class="product-image-wrap">
                        <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
                        <span class="product-badge-tag"><?= e($p['badge']) ?></span>
                    </div>
                    <div class="product-body">
                        <h2 class="product-title"><?= e($p['name']) ?></h2>
                        <p class="product-desc"><?= e($p['desc']) ?></p>

                        <div class="product-specs">
                            <?php foreach ($p['specs'] as $spec): ?>
                                <div class="product-spec-item">
                                    <span class="material-symbols-outlined" style="font-size: 14px; color: #10b981;">check</span>
                                    <span><?= e($spec) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="product-price-row">
                            <span class="product-price-label">Harga Resmi</span>
                            <span class="product-price-val"><?= e($p['price']) ?></span>
                        </div>

                        <div class="product-actions">
                            <a href="<?= get_wa_url("Halo Data Lake Indonesia, saya ingin memesan: {$p['name']} ({$p['price']}). Mohon info ketersediaan stok & pengiriman.") ?>" 
                               target="_blank" class="btn-order-wa">
                                <span class="material-symbols-outlined" style="font-size: 16px;">chat</span>
                                Beli via WA
                            </a>
                            <button type="button" class="btn-order-form" onclick="openOrderModal('<?= e(addslashes($p['name'])) ?>', '<?= e($p['price']) ?>')">
                                <span class="material-symbols-outlined" style="font-size: 16px;">shopping_cart</span>
                                Pesan Online
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer footer-personal" data-astro-cid-l3trhy4j>
        <div class="container" data-astro-cid-l3trhy4j>
            <div class="footer-brand" data-astro-cid-l3trhy4j>
                <img src="/_astro/DLI-logo-navy.BFfOijbL.png" alt="Data Lake Indonesia" class="footer-brand-logo">
                <p class="footer-brand-desc">Authorized Distributor Starlink di Indonesia. Kami menghadirkan konektivitas satelit yang andal untuk rumah dan bisnis di seluruh penjuru negeri.</p>
            </div>

            <div class="footer-grid" data-astro-cid-l3trhy4j>
                <div class="footer-col">
                    <h4 class="footer-heading">Navigasi Utama</h4>
                    <ul class="footer-links">
                        <li><a href="/">Beranda</a></li>
                        <li><a href="/store.php">Official Store</a></li>
                        <li><a href="/#mengapa-kami">Tentang Kami</a></li>
                        <li><a href="/#konsultasi">Hubungi Kami</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4 class="footer-heading">Katalog Produk</h4>
                    <ul class="footer-links">
                        <li><a href="/store.php">Starlink Standard Kit</a></li>
                        <li><a href="/store.php">Starlink Mini Kit</a></li>
                        <li><a href="/store.php">Router &amp; Aksesoris</a></li>
                        <li><a href="/store.php">Mounting &amp; Bracket</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4 class="footer-heading">Portal Akun</h4>
                    <ul class="footer-links">
                        <?php if (is_logged_in()): ?>
                            <li><a href="dashboard.php" style="font-weight: 700; color: var(--color-primary);">Buka Dashboard</a></li>
                            <li><a href="logout.php" style="color: #BA1A1A;">Keluar</a></li>
                        <?php else: ?>
                            <li><a href="login.php" style="font-weight: 700; color: var(--color-primary);">Masuk</a></li>
                            <li><a href="register.php">Daftar Akun Baru</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="footer-copy">© 2026 PT Data Lake Indonesia. Hak cipta dilindungi</p>
            </div>
        </div>
    </footer>

    <!-- Order Modal -->
    <div class="order-modal-backdrop" id="orderModal">
        <div class="order-modal">
            <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-primary); margin: 0;">Formulir Pemesanan</h3>
                    <small style="color: #64748b;" id="modalProductSub">Produk</small>
                </div>
                <button type="button" onclick="closeOrderModal()" style="background:none; border:none; cursor:pointer; color:#64748b;">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form method="POST" action="store.php">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="submit_store_order" value="1">
                <input type="hidden" name="product_name" id="modalProductNameInput">

                <div style="padding: 24px; display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Nama Pembeli / Perusahaan *</label>
                        <input type="text" name="buyer_name" required placeholder="Contoh: Budi Santoso"
                               style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">No. WhatsApp *</label>
                            <input type="tel" name="buyer_phone" required placeholder="08123456789"
                                   style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Jumlah Unit</label>
                            <input type="number" name="quantity" value="1" min="1" max="100"
                                   style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Alamat Email</label>
                        <input type="email" name="buyer_email" placeholder="budi@domain.com"
                               style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Alamat Lengkap Pengiriman *</label>
                        <textarea name="shipping_address" required rows="2" placeholder="Nama Jalan, Kota/Kabupaten, Provinsi, Kode Pos..."
                                  style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;"></textarea>
                    </div>

                    <div>
                        <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Catatan (Opsional)</label>
                        <input type="text" name="notes" placeholder="Contoh: Butuh penawaran resmi / invoice atas nama PT..."
                               style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeOrderModal()" style="padding: 10px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-size: 13.5px; font-weight: 600;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; border-radius: 8px; font-size: 13.5px;">Kirim Pesanan Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating WhatsApp FAB with Dynamic Link -->
    <a href="<?= get_wa_url('Halo, saya ingin konsultasi mengenai pembelian perangkat Starlink di Store Resmi Data Lake.') ?>" 
       class="wa-fab" target="_blank" rel="noopener noreferrer" aria-label="Chat dengan kami di WhatsApp">
        <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.13h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.23 8.23 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.51.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29Z"></path>
        </svg>
    </a>

    <script>
    function filterProducts(category, btn) {
        document.querySelectorAll('.store-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.product-card').forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openOrderModal(productName, price) {
        document.getElementById('modalProductNameInput').value = productName;
        document.getElementById('modalProductSub').innerText = productName + ' (' + price + ')';
        document.getElementById('orderModal').classList.add('show');
    }

    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
    }
    </script>
</body>
</html>
