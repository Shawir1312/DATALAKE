<?php
require_once __DIR__ . '/includes/auth.php';

$inquiry_success = false;
$inquiry_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $inquiry_error = "Sesi telah kedaluwarsa. Silakan coba lagi.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $package = trim($_POST['package'] ?? 'Starlink Standard');
        $location = trim($_POST['location'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($phone)) {
            $inquiry_error = "Harap isi nama dan nomor telepon/WhatsApp Anda.";
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("
                    INSERT INTO inquiries (name, email, phone, service_package, location, message, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'new')
                ");
                $stmt->execute([$name, $email, $phone, $package, $location, $message]);
                log_activity('New Inquiry Submission', "Permintaan konsultasi dari $name ($phone)");
                $inquiry_success = true;
            } catch (Exception $e) {
                $inquiry_error = "Terjadi kesalahan sistem saat menyimpan permintaan Anda.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PT Data Lake Indonesia | Authorized Distributor &amp; Reseller Starlink Indonesia</title>
    
    <meta name="description" content="PT Data Lake Indonesia adalah Authorized Distributor resmi Starlink di Indonesia. Solusi internet satelit enterprise, dedicated bandwidth, perangkat resmi bergaransi, dan dukungan NOC 24/7 di seluruh Indonesia.">
    <meta name="keywords" content="PT Data Lake Indonesia, Data Lake Indonesia, Starlink Indonesia, Distributor Starlink Indonesia, Reseller Starlink Indonesia, Internet Satelit Morotai, Starlink Enterprise, Nike P. Kosasih, Beli Starlink Resmi">
    <meta name="author" content="PT Data Lake Indonesia">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://datalakeindonesia.com/">
    
    <!-- Google Search Console Official Verification -->
    <meta name="google-site-verification" content="o8fFW2wljic6fEyNenne2ct8r0NuYPVgor4enKW0qWE">
    
    <!-- Geo Meta Tags for Indonesian Local Search SEO -->
    <meta name="geo.region" content="ID-JK">
    <meta name="geo.placename" content="Jakarta Selatan">
    <meta name="geo.position" content="-6.2297;106.8075">
    <meta name="ICBM" content="-6.2297, 106.8075">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="PT Data Lake Indonesia">
    <meta property="og:title" content="PT Data Lake Indonesia | Authorized Distributor Starlink">
    <meta property="og:description" content="Authorized Distributor resmi Starlink di Indonesia. Layanan internet satelit dedicated enterprise dengan SLA 99.9% dan garansi resmi.">
    <meta property="og:url" content="https://datalakeindonesia.com/">
    <meta property="og:image" content="https://datalakeindonesia.com/og-default.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="PT Data Lake Indonesia | Authorized Distributor Starlink">
    <meta name="twitter:description" content="Authorized Distributor resmi Starlink di Indonesia. Layanan internet satelit dedicated enterprise dengan garansi resmi.">
    <meta name="twitter:image" content="https://datalakeindonesia.com/og-default.png">

    <!-- Schema.org JSON-LD Structured Data for Google Rich Snippets & Knowledge Graph -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Organization",
          "@id": "https://datalakeindonesia.com/#organization",
          "name": "PT Data Lake Indonesia",
          "alternateName": ["Data Lake", "Data Lake Indonesia", "PT DLI"],
          "url": "https://datalakeindonesia.com/",
          "logo": {
            "@type": "ImageObject",
            "url": "https://datalakeindonesia.com/logo/DLI-logo-navy.png",
            "caption": "PT Data Lake Indonesia"
          },
          "image": "https://datalakeindonesia.com/og-default.png",
          "description": "Authorized Distributor dan Reseller Resmi Layanan Satelit Starlink di Indonesia untuk Bisnis, Enterprise, Maritim, dan Reseller.",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Revenue Tower Lantai 16, District 8 SCBD, Jl. Jend. Sudirman Kav. 52-53, Senayan",
            "addressLocality": "Jakarta Selatan",
            "addressRegion": "DKI Jakarta",
            "postalCode": "11530",
            "addressCountry": "ID"
          },
          "contactPoint": [
            {
              "@type": "ContactPoint",
              "telephone": "+62-21-5082-0800",
              "contactType": "customer service",
              "areaServed": "ID",
              "availableLanguage": ["Indonesian", "English"]
            },
            {
              "@type": "ContactPoint",
              "telephone": "+62-817-0117-800",
              "contactType": "sales",
              "areaServed": "ID",
              "availableLanguage": ["Indonesian", "English"]
            }
          ],
          "founder": {
            "@type": "Person",
            "name": "Nike P. Kosasih",
            "jobTitle": "Direktur Utama"
          }
        },
        {
          "@type": "WebSite",
          "@id": "https://datalakeindonesia.com/#website",
          "url": "https://datalakeindonesia.com/",
          "name": "PT Data Lake Indonesia",
          "publisher": {
            "@id": "https://datalakeindonesia.com/#organization"
          },
          "inLanguage": "id-ID"
        }
      ]
    }
    </script>

    <!-- Favicon & Touch Icons -->
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <!-- Fonts Preload -->
    <link rel="preload" href="/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/pt-serif-bold-italic-latin.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Original Exact Datalke Stylesheets -->
    <link rel="stylesheet" href="/_astro/WhatsAppButton.Bx5n5ahj.css">
    <link rel="stylesheet" href="/_astro/HomeContent.Df-1erRN.css">

    <style>
      /* Integrated Auth Navigation & Consultation Section */
      .nav-auth-group {
        display: flex;
        align-items: center;
        gap: var(--space-2);
      }
      .nav-btn-login {
        font-family: var(--font-body);
        font-size: var(--text-body-sm);
        font-weight: 600;
        padding: 6px 14px;
        color: var(--color-primary);
        border: 1px solid rgba(26, 54, 93, 0.2);
        border-radius: var(--radius-full);
        transition: all var(--duration-fast);
      }
      .nav-btn-login:hover {
        background: rgba(26, 54, 93, 0.06);
        color: var(--color-primary);
      }
      .nav-btn-register {
        font-family: var(--font-body);
        font-size: var(--text-body-sm);
        font-weight: 600;
        padding: 6px 16px;
        background: var(--color-primary);
        color: #ffffff;
        border-radius: var(--radius-full);
        transition: all var(--duration-fast);
      }
      .nav-btn-register:hover {
        background: var(--color-secondary);
        color: #ffffff;
      }
      .nav-btn-dash {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: var(--font-body);
        font-size: var(--text-body-sm);
        font-weight: 700;
        padding: 6px 16px;
        background: linear-gradient(135deg, #00D2FF 0%, #0099cc 100%);
        color: #040b1a;
        border-radius: var(--radius-full);
        box-shadow: 0 2px 8px rgba(0, 210, 255, 0.35);
      }
      .consultation-section {
        background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        padding: var(--space-20) 0;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
      }
      .consultation-grid {
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        gap: var(--space-12);
        align-items: center;
      }
      .consultation-form-card {
        background: #ffffff;
        padding: var(--space-8);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-float);
        border: 1px solid rgba(0, 0, 0, 0.06);
      }
      @media(max-width: 991px) {
        .consultation-grid {
          grid-template-columns: 1fr;
          gap: var(--space-8);
        }
      }
    </style>
</head>
<body>
    <a href="#main-content" class="sr-only">Langsung ke konten utama</a>

    <!-- Main Navigation Header -->
    <header class="nav-wrapper" data-astro-cid-m6gy25n3>
        <nav class="nav container" data-astro-cid-m6gy25n3>
            <a href="/" class="nav-logo" data-astro-cid-m6gy25n3>
                <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="nav-logo-img" width="120" height="36" decoding="async" data-astro-cid-m6gy25n3>
            </a>

            <div class="nav-links" data-astro-cid-m6gy25n3>
                <a href="#produk" class="nav-link" data-astro-cid-m6gy25n3>Produk Kami</a>
                <a href="/store.php" class="nav-link" style="color: var(--color-tertiary); font-weight: 700;" data-astro-cid-m6gy25n3>Official Store</a>
                <a href="#mengapa-kami" class="nav-link" data-astro-cid-m6gy25n3>Why Starlink</a>
                <a href="#keunggulan" class="nav-link" data-astro-cid-m6gy25n3>Layanan</a>
                <a href="#mitra" class="nav-link" data-astro-cid-m6gy25n3>Mitra Resmi</a>
                <a href="#konsultasi" class="nav-link" data-astro-cid-m6gy25n3>Konsultasi</a>
            </div>

            <div class="nav-actions" data-astro-cid-m6gy25n3>
                <a href="?lang=en" class="nav-lang" aria-label="Ganti ke English" data-astro-cid-m6gy25n3>EN</a>

                <!-- User Authentication Buttons -->
                <div class="nav-auth-group">
                    <?php if (is_logged_in()): ?>
                        <a href="dashboard.php" class="nav-btn-dash">
                            <span class="material-symbols-outlined" style="font-size: 18px;">dashboard</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="logout.php" class="nav-lang" style="color: #BA1A1A; margin-left: 6px; font-weight: 600;" title="Keluar">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-btn-login">Masuk</a>
                        <a href="register.php" class="nav-btn-register">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mode Toggle: Personal vs Bisnis -->
            <nav class="mode-toggle nav-mode-toggle" aria-label="Site mode" data-astro-cid-q4lhxcan>
                <a href="/" class="toggle-item <?= (!isset($_GET['mode']) || $_GET['mode'] !== 'bisnis') ? 'active' : '' ?>" data-astro-cid-q4lhxcan>Personal</a>
                <a href="?mode=bisnis" class="toggle-item <?= (isset($_GET['mode']) && $_GET['mode'] === 'bisnis') ? 'active' : '' ?>" data-astro-cid-q4lhxcan>Bisnis</a>
            </nav>

            <!-- Mobile Hamburger Button -->
            <button class="nav-hamburger" aria-label="Buka menu navigasi" aria-expanded="false" data-astro-cid-m6gy25n3>
                <span class="material-symbols-outlined" style="font-size: 24px;" aria-hidden="true">menu</span>
            </button>
        </nav>
    </header>

    <!-- Mobile Drawer Navigation -->
    <div class="nav-drawer" aria-hidden="true" data-astro-cid-m6gy25n3>
        <div class="nav-drawer-backdrop" data-astro-cid-m6gy25n3></div>
        <div class="nav-drawer-panel" data-astro-cid-m6gy25n3>
            <div class="nav-drawer-header" data-astro-cid-m6gy25n3>
                <a href="/" class="nav-logo" data-astro-cid-m6gy25n3>
                    <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="nav-logo-img" width="120" height="36" decoding="async" data-astro-cid-m6gy25n3>
                </a>
                <button class="nav-drawer-close" aria-label="Tutup menu navigasi" data-astro-cid-m6gy25n3>
                    <span class="material-symbols-outlined" style="font-size: 24px;" aria-hidden="true">close</span>
                </button>
            </div>

            <nav class="mode-toggle nav-drawer-mode" aria-label="Site mode" data-astro-cid-q4lhxcan>
                <a href="/" class="toggle-item <?= (!isset($_GET['mode']) || $_GET['mode'] !== 'bisnis') ? 'active' : '' ?>" data-astro-cid-q4lhxcan>Personal</a>
                <a href="?mode=bisnis" class="toggle-item <?= (isset($_GET['mode']) && $_GET['mode'] === 'bisnis') ? 'active' : '' ?>" data-astro-cid-q4lhxcan>Bisnis</a>
            </nav>

            <div class="nav-drawer-links" data-astro-cid-m6gy25n3>
                <a href="#produk" class="nav-drawer-link" data-astro-cid-m6gy25n3>Produk Kami</a>
                <a href="/store.php" class="nav-drawer-link" style="color: var(--color-primary); font-weight: 700;" data-astro-cid-m6gy25n3>Official Store Online</a>
                <a href="#mengapa-kami" class="nav-drawer-link" data-astro-cid-m6gy25n3>Why Starlink</a>
                <a href="#keunggulan" class="nav-drawer-link" data-astro-cid-m6gy25n3>Layanan</a>
                <a href="#mitra" class="nav-drawer-link" data-astro-cid-m6gy25n3>Mitra Resmi</a>
                <a href="#konsultasi" class="nav-drawer-link" data-astro-cid-m6gy25n3>Konsultasi</a>
            </div>

            <div class="nav-drawer-actions" data-astro-cid-m6gy25n3>
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="btn btn-primary nav-drawer-btn">Buka Dashboard</a>
                    <a href="logout.php" class="nav-drawer-link" style="color: #BA1A1A;">Keluar (Logout)</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary nav-drawer-btn">Masuk</a>
                    <a href="register.php" class="btn btn-primary nav-drawer-btn">Daftar Akun Baru</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content">
        <!-- Hero Section -->
        <section class="hero-shell" data-astro-cid-nsbl7jv2>
            <div class="container" data-astro-cid-nsbl7jv2>
                <div class="hero" style="--hero-bg: url('/content/personal/refresh/home/indonesia-network-hero.png')" data-astro-cid-nsbl7jv2>
                    <div class="hero-col" data-reveal data-astro-cid-nsbl7jv2>
                        <p class="label-md hero-overline" data-astro-cid-nsbl7jv2>Starlink Authorized Distributor</p>
                        <p class="hero-accent" data-astro-cid-nsbl7jv2>Konektivitas Tanpa Batas</p>
                        <h1 class="hero-headline" data-astro-cid-nsbl7jv2>Memberdayakan Indonesia dengan Konektivitas Starlink</h1>
                        <p class="body-lg hero-sub" data-astro-cid-nsbl7jv2>
                            Data Lake merupakan Authorized Distributor Starlink di Indonesia. Beli Starlink resmi dengan garansi lokal, dukungan instalasi, dan pengiriman ke seluruh Indonesia.
                        </p>
                        <div class="hero-actions" data-astro-cid-nsbl7jv2>
                            <a href="#produk" data-astro-cid-bweis6se="true" class="btn btn-primary">Ketahui Lebih Lanjut</a>
                            <a href="/store.php" data-astro-cid-bweis6se="true" class="btn btn-inverted">Belanja Sekarang</a>
                            <?php if (!is_logged_in()): ?>
                                <a href="register.php" data-astro-cid-bweis6se="true" class="btn btn-outlined" style="border-color: rgba(255,255,255,0.4); color: #fff;">Daftar Akun Portal</a>
                            <?php else: ?>
                                <a href="dashboard.php" data-astro-cid-bweis6se="true" class="btn btn-outlined" style="border-color: var(--color-tertiary); color: var(--color-tertiary);">Masuk Dashboard</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Blocks: Starlink Kit & Accessories -->
        <section class="features" id="produk" data-astro-cid-fjaytwa7>
            <div class="container" data-astro-cid-fjaytwa7>
                <div class="section-head" data-reveal data-astro-cid-fjaytwa7>
                    <p class="section-eyebrow" data-astro-cid-fjaytwa7>PRODUK KAMI</p>
                    <h2 class="section-title" data-astro-cid-fjaytwa7>Starlink Kit &amp; Aksesoris</h2>
                    <p class="section-subtitle" data-astro-cid-fjaytwa7>Beli Starlink Gen 3 V4, Starlink Mini, dan aksesoris resmi untuk koneksi yang optimal.</p>
                </div>
                <div class="feature-grid" data-astro-cid-fjaytwa7>
                    <article class="feature" data-reveal data-astro-cid-fjaytwa7>
                        <div class="feature-media" style="--feature-bg: url('/content/personal/refresh/home/kit-card.jpg')" data-astro-cid-fjaytwa7>
                            <div class="feature-copy" data-astro-cid-fjaytwa7>
                                <h2 class="feature-title" data-astro-cid-fjaytwa7>Starlink Kit</h2>
                                <p class="feature-subtitle" data-astro-cid-fjaytwa7>Rasakan koneksi Starlink yang optimal</p>
                            </div>
                            <div class="feature-cta" data-astro-cid-fjaytwa7>
                                <a href="#konsultasi" data-astro-cid-bweis6se="true" class="btn btn-primary feature-btn">Ketahui Lebih Lanjut</a>
                                <a href="/store.php" data-astro-cid-bweis6se="true" class="btn btn-inverted feature-btn">Belanja Sekarang</a>
                            </div>
                        </div>
                    </article>
                    <article class="feature" data-reveal data-astro-cid-fjaytwa7>
                        <div class="feature-media" style="--feature-bg: url('/content/personal/refresh/our-product/accessories/router-mini.webp')" data-astro-cid-fjaytwa7>
                            <div class="feature-copy" data-astro-cid-fjaytwa7>
                                <h2 class="feature-title" data-astro-cid-fjaytwa7>Aksesoris</h2>
                                <p class="feature-subtitle" data-astro-cid-fjaytwa7>Optimalkan instalasi Starlink Anda</p>
                            </div>
                            <div class="feature-cta" data-astro-cid-fjaytwa7>
                                <a href="#konsultasi" data-astro-cid-bweis6se="true" class="btn btn-primary feature-btn">Ketahui Lebih Lanjut</a>
                                <a href="/store.php" data-astro-cid-bweis6se="true" class="btn btn-inverted feature-btn">Belanja Sekarang</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Why Band & Benefits -->
        <div class="why-benefits-surface" id="mengapa-kami" data-astro-cid-nsbl7jv2>
            <section class="why-band" data-astro-cid-nsbl7jv2>
                <div class="container" data-reveal data-astro-cid-nsbl7jv2>
                    <p class="why-eyebrow" data-astro-cid-nsbl7jv2>NILAI YANG KAMI TAWARKAN</p>
                    <h2 class="why-title" data-astro-cid-nsbl7jv2>Mengapa Data Lake Indonesia?</h2>
                    <p class="why-sub" data-astro-cid-nsbl7jv2>Data Lake adalah Distributor Resmi Starlink Indonesia, dengan jaringan cakupan terluas dan dukungan lokal terpercaya.</p>
                </div>
            </section>

            <section class="benefits-section" id="keunggulan" data-astro-cid-nsbl7jv2>
                <div class="container" data-astro-cid-nsbl7jv2>
                    <div class="featured-row" data-reveal data-astro-cid-nsbl7jv2>
                        <article class="featured-card" data-astro-cid-nsbl7jv2>
                            <div class="featured-text" data-astro-cid-nsbl7jv2>
                                <span class="featured-icon material-symbols-outlined" aria-hidden="true" data-astro-cid-nsbl7jv2>verified</span>
                                <div class="featured-body" data-astro-cid-nsbl7jv2>
                                    <h3 class="featured-title" data-astro-cid-nsbl7jv2>Layanan Terkelola</h3>
                                    <p class="body-sm featured-desc" data-astro-cid-nsbl7jv2>Tim ahli kami menangani layanan Starlink dari ujung ke ujung — penerapan, pemeliharaan, dan dukungan 24/7 — agar konektivitas Anda terus berjalan.</p>
                                </div>
                            </div>
                            <div class="featured-img" data-astro-cid-nsbl7jv2>
                                <img src="/content/personal/refresh/home/team-portrait.webp" alt="Layanan Terkelola" loading="lazy" decoding="async" width="640" height="420" data-astro-cid-nsbl7jv2>
                            </div>
                        </article>
                        <article class="featured-card" data-astro-cid-nsbl7jv2>
                            <div class="featured-text" data-astro-cid-nsbl7jv2>
                                <span class="featured-icon material-symbols-outlined" aria-hidden="true" data-astro-cid-nsbl7jv2>support_agent</span>
                                <div class="featured-body" data-astro-cid-nsbl7jv2>
                                    <h3 class="featured-title" data-astro-cid-nsbl7jv2>Dukungan Lokal Khusus</h3>
                                    <p class="body-sm featured-desc" data-astro-cid-nsbl7jv2>Instalasi oleh tenaga ahli lokal dengan dukungan on-site dan jarak jauh — bantuan lokal cepat saat Anda membutuhkannya, dalam Bahasa Indonesia dan Inggris.</p>
                                </div>
                            </div>
                            <div class="featured-img" data-astro-cid-nsbl7jv2>
                                <img src="/content/personal/refresh/use-case/installer-rooftop.jpg" alt="Dukungan Lokal Khusus" loading="lazy" decoding="async" width="640" height="420" data-astro-cid-nsbl7jv2>
                            </div>
                        </article>
                    </div>

                    <div class="supporting-row" data-reveal data-astro-cid-nsbl7jv2>
                        <article class="support-card" data-astro-cid-nsbl7jv2>
                            <span class="support-icon-wrap" aria-hidden="true" data-astro-cid-nsbl7jv2>
                                <span class="material-symbols-outlined" data-astro-cid-nsbl7jv2>dashboard</span>
                            </span>
                            <h3 class="support-title" data-astro-cid-nsbl7jv2>Dashboard Pelanggan</h3>
                            <p class="body-sm support-desc" data-astro-cid-nsbl7jv2>Kontrol penuh atas Starlink Anda — pantau penggunaan, kelola tagihan, dan akses dukungan dalam satu tempat.</p>
                        </article>
                        <article class="support-card" data-astro-cid-nsbl7jv2>
                            <span class="support-icon-wrap" aria-hidden="true" data-astro-cid-nsbl7jv2>
                                <span class="material-symbols-outlined" data-astro-cid-nsbl7jv2>verified_user</span>
                            </span>
                            <h3 class="support-title" data-astro-cid-nsbl7jv2>Mitra Garansi Starlink</h3>
                            <p class="body-sm support-desc" data-astro-cid-nsbl7jv2>Mitra garansi resmi. Kami menangani klaim garansi pelanggan dari awal hingga selesai — hot-swap tercakup.</p>
                        </article>
                        <article class="support-card" data-astro-cid-nsbl7jv2>
                            <span class="support-icon-wrap" aria-hidden="true" data-astro-cid-nsbl7jv2>
                                <span class="material-symbols-outlined" data-astro-cid-nsbl7jv2>receipt_long</span>
                            </span>
                            <h3 class="support-title" data-astro-cid-nsbl7jv2>Penagihan Lokal</h3>
                            <p class="body-sm support-desc" data-astro-cid-nsbl7jv2>Bayar dalam IDR, berbagai metode pembayaran lokal, dan selalu didukung kuitansi yang jelas untuk transparansi penuh.</p>
                        </article>
                        <article class="support-card" data-astro-cid-nsbl7jv2>
                            <span class="support-icon-wrap" aria-hidden="true" data-astro-cid-nsbl7jv2>
                                <span class="material-symbols-outlined" data-astro-cid-nsbl7jv2>distance</span>
                            </span>
                            <h3 class="support-title" data-astro-cid-nsbl7jv2>Cakupan Nasional yang Luas</h3>
                            <p class="body-sm support-desc" data-astro-cid-nsbl7jv2>Pemasangan Data Lake dan Starlink yang tersebar strategis di seluruh Indonesia — di mana pun Anda butuh terhubung.</p>
                        </article>
                    </div>
                </div>
            </section>
        </div>

        <!-- Consultation & Order Inquiry Section (Full PHP Database Connected) -->
        <section class="consultation-section" id="konsultasi">
            <div class="container">
                <div class="consultation-grid" data-reveal>
                    <div>
                        <p class="why-eyebrow">KONSULTASI GRATIS</p>
                        <h2 class="why-title" style="font-size: 2.25rem;">Siap Menikmati Kecepatan Starlink?</h2>
                        <p class="why-sub" style="margin-bottom: var(--space-6);">
                            Dapatkan rekomendasi paket perangkat dan instalasi Starlink yang paling tepat untuk rumah, villa, kapal, atau lokasi bisnis Anda di seluruh pelosok Indonesia.
                        </p>

                        <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-symbols-outlined">verified</span>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--color-primary);">Distributor Resmi Resmi di Indonesia</div>
                                    <small style="color: #64748b;">Perangkat asli 100% garansi resmi Starlink</small>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-symbols-outlined">bolt</span>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--color-primary);">Respon Cepat Tim Spesialis</div>
                                    <small style="color: #64748b;">Konsultasi langsung melalui WhatsApp & Portal Pelanggan</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="consultation-form-card">
                        <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; color: var(--color-primary); margin-bottom: 8px;">
                            Formulir Permintaan Konsultasi
                        </h3>
                        <p style="font-size: 13.5px; color: #64748b; margin-bottom: 20px;">
                            Data Anda akan langsung tersimpan di sistem kami dan ditindaklanjuti oleh tim konsultan.
                        </p>

                        <?php if ($inquiry_success): ?>
                            <div style="padding: 16px; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; margin-bottom: 20px; font-size: 14px;">
                                <strong>Terima kasih!</strong> Permintaan Anda telah kami terima. Tim kami akan segera menghubungi nomor WhatsApp Anda.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($inquiry_error)): ?>
                            <div style="padding: 16px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; margin-bottom: 20px; font-size: 14px;">
                                <?= e($inquiry_error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="#konsultasi" style="display: flex; flex-direction: column; gap: 14px;">
                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                            <input type="hidden" name="submit_inquiry" value="1">

                            <div>
                                <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Nama Lengkap *</label>
                                <input type="text" name="name" required placeholder="Contoh: Budi Santoso" 
                                       style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">No. WhatsApp / HP *</label>
                                    <input type="tel" name="phone" required placeholder="08123456789" 
                                           style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Email</label>
                                    <input type="email" name="email" placeholder="budi@domain.com" 
                                           style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Pilihan Paket</label>
                                    <select name="package" style="width: 100%; height: 44px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #fff;">
                                        <option value="Starlink Standard Gen 3">Starlink Standard Gen 3</option>
                                        <option value="Starlink Mini">Starlink Mini</option>
                                        <option value="Paket Residensial Lite">Paket Residensial Lite</option>
                                        <option value="Paket Bisnis Enterprise">Paket Bisnis Enterprise</option>
                                        <option value="Aksesoris & Bracket">Aksesoris & Bracket</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Kota / Lokasi Pemasangan</label>
                                    <input type="text" name="location" placeholder="Contoh: Raja Ampat, Papua" 
                                           style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Catatan Tambahan (Opsional)</label>
                                <textarea name="message" rows="2" placeholder="Sampaikan kebutuhan instalasi atau medan lokasi Anda..." 
                                          style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; height: 46px; border-radius: 8px; margin-top: 6px;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">send</span>
                                Kirim Permintaan Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Authorized Retail Partners -->
        <section class="partners-section" id="mitra" data-astro-cid-nsbl7jv2>
            <div class="container" data-reveal data-astro-cid-nsbl7jv2>
                <div class="partners-head" data-astro-cid-nsbl7jv2>
                    <p class="partners-eyebrow" data-astro-cid-nsbl7jv2>MITRA RESMI</p>
                    <h2 class="partners-title" data-astro-cid-nsbl7jv2>Mitra Kami</h2>
                    <p class="partners-sub" data-astro-cid-nsbl7jv2>Jaringan distributor retail Data Lake Indonesia untuk pembelian Starlink yang lebih mudah dan terpercaya.</p>
                </div>
                <div class="partners-grid" aria-label="Mitra Kami" data-astro-cid-nsbl7jv2>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/apollo.webp" alt="Apollo Gadget Store" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/bintang-mahameru.webp" alt="Bintang Mahameru Utama" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/color-transparent.webp" alt="Digi Global Store" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/electronic-city.webp" alt="Electronic City" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/erafone.webp" alt="Erafone" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/gadget-mart.webp" alt="Gadget Mart" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/hartono.webp" alt="Hartono" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/hnd.webp" alt="HND Computer" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/hypermart.webp" alt="Hypermart" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                    <div class="partner-cell" data-astro-cid-nsbl7jv2>
                        <img src="/content/personal/partners/ur.webp" alt="Urban Republic" loading="lazy" decoding="async" width="220" height="96" class="partner-logo" data-astro-cid-nsbl7jv2>
                    </div>
                </div>
            </div>
        </section>

        <!-- Floating WhatsApp FAB -->
        <a href="<?= htmlspecialchars(get_wa_url('Halo, saya ingin konsultasi dan info lebih lanjut mengenai layanan Starlink di Data Lake Indonesia.')) ?>" 
           class="wa-fab" target="_blank" rel="noopener noreferrer" aria-label="Chat dengan kami di WhatsApp" data-astro-cid-wqmjn7bl>
            <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false" data-astro-cid-wqmjn7bl>
                <path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.13h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.23 8.23 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.51.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29Z" data-astro-cid-wqmjn7bl></path>
            </svg>
        </a>
    </main>

    <!-- Footer -->
    <footer class="footer footer-personal" data-astro-cid-l3trhy4j>
        <div class="container" data-astro-cid-l3trhy4j>
            <div class="footer-brand" data-astro-cid-l3trhy4j>
                <img src="/_astro/DLI-logo-navy.BFfOijbL.png" alt="Data Lake Indonesia" class="footer-brand-logo" data-astro-cid-l3trhy4j>
                <p class="footer-brand-desc" data-astro-cid-l3trhy4j>Authorized Distributor Starlink di Indonesia. Kami menghadirkan konektivitas satelit yang andal untuk rumah dan bisnis di seluruh penjuru negeri.</p>
            </div>

            <div class="footer-grid" data-astro-cid-l3trhy4j>
                <div class="footer-col" data-astro-cid-l3trhy4j>
                    <h4 class="footer-heading" data-astro-cid-l3trhy4j>Data Lake ID</h4>
                    <ul class="footer-links" data-astro-cid-l3trhy4j>
                        <li data-astro-cid-l3trhy4j><a href="#mengapa-kami" data-astro-cid-l3trhy4j>Tentang Kami</a></li>
                        <li data-astro-cid-l3trhy4j><a href="#keunggulan" data-astro-cid-l3trhy4j>Keunggulan Layanan</a></li>
                        <li data-astro-cid-l3trhy4j><a href="#konsultasi" data-astro-cid-l3trhy4j>Hubungi Kami</a></li>
                    </ul>
                </div>

                <div class="footer-col" data-astro-cid-l3trhy4j>
                    <h4 class="footer-heading" data-astro-cid-l3trhy4j>Belanja</h4>
                    <ul class="footer-links" data-astro-cid-l3trhy4j>
                        <li data-astro-cid-l3trhy4j><a href="store.php?cat=kit" data-astro-cid-l3trhy4j>Starlink Kit</a></li>
                        <li data-astro-cid-l3trhy4j><a href="store.php?cat=accessories" data-astro-cid-l3trhy4j>Aksesori Starlink</a></li>
                        <li data-astro-cid-l3trhy4j><a href="store.php" data-astro-cid-l3trhy4j>Official Store Online</a></li>
                    </ul>
                </div>

                <div class="footer-col" data-astro-cid-l3trhy4j>
                    <h4 class="footer-heading" data-astro-cid-l3trhy4j>Portal Akun</h4>
                    <ul class="footer-links" data-astro-cid-l3trhy4j>
                        <?php if (is_logged_in()): ?>
                            <li data-astro-cid-l3trhy4j><a href="dashboard.php" style="font-weight: 700; color: var(--color-primary);" data-astro-cid-l3trhy4j>Buka Dashboard</a></li>
                            <li data-astro-cid-l3trhy4j><a href="dashboard.php?tab=profile" data-astro-cid-l3trhy4j>Pengaturan Profil</a></li>
                            <li data-astro-cid-l3trhy4j><a href="logout.php" style="color: #BA1A1A;" data-astro-cid-l3trhy4j>Keluar (Logout)</a></li>
                        <?php else: ?>
                            <li data-astro-cid-l3trhy4j><a href="login.php" style="font-weight: 700; color: var(--color-primary);" data-astro-cid-l3trhy4j>Masuk (Login)</a></li>
                            <li data-astro-cid-l3trhy4j><a href="register.php" data-astro-cid-l3trhy4j>Daftar Akun Baru</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom" data-astro-cid-l3trhy4j>
                <a href="/" class="footer-logo" data-astro-cid-l3trhy4j>
                    <img src="/_astro/DLI-logo-navy.BFfOijbL.png" alt="Data Lake Indonesia" class="footer-logo-img" data-astro-cid-l3trhy4j>
                </a>
                <p class="footer-copy" data-astro-cid-l3trhy4j>© 2026 PT Data Lake Indonesia. Hak cipta dilindungi</p>
                <div class="footer-social" data-astro-cid-l3trhy4j>
                    <a href="<?= htmlspecialchars(get_wa_url('Halo admin Data Lake, saya ingin info tentang promo terbaru Starlink')) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp Kami" data-astro-cid-l3trhy4j title="Hubungi CS WhatsApp">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.13h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.23 8.23 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Z"/></svg>
                    </a>
                    <a href="store.php" data-astro-cid-l3trhy4j title="Kunjungi Official Store">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </a>
                    <a href="#konsultasi" data-astro-cid-l3trhy4j title="Formulir Permohonan & Konsultasi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Navigation Scripts -->
    <script type="module">
        // Header scroll backdrop effect
        const header = document.querySelector(".nav-wrapper");
        let ticking = false;
        function updateScroll() {
            if (!ticking) {
                requestAnimationFrame(() => {
                    header.classList.toggle("scrolled", window.scrollY > 8);
                    ticking = false;
                });
                ticking = true;
            }
        }
        window.addEventListener("scroll", updateScroll, { passive: true });
        updateScroll();

        // Mobile drawer handlers
        const hamburger = document.querySelector(".nav-hamburger");
        const drawer = document.querySelector(".nav-drawer");
        const drawerClose = document.querySelector(".nav-drawer-close");
        const drawerBackdrop = document.querySelector(".nav-drawer-backdrop");

        function openDrawer() {
            drawer.classList.add("open");
            drawer.setAttribute("aria-hidden", "false");
            hamburger.setAttribute("aria-expanded", "true");
            document.body.classList.add("no-scroll");
            drawerClose.focus();
        }

        function closeDrawer() {
            drawer.classList.remove("open");
            drawer.setAttribute("aria-hidden", "true");
            hamburger.setAttribute("aria-expanded", "false");
            document.body.classList.remove("no-scroll");
            hamburger.focus();
        }

        hamburger?.addEventListener("click", openDrawer);
        drawerClose?.addEventListener("click", closeDrawer);
        drawerBackdrop?.addEventListener("click", closeDrawer);

        // Close drawer on clicking internal links
        document.querySelectorAll(".nav-drawer-link").forEach(link => {
            link.addEventListener("click", closeDrawer);
        });

        // Data-reveal Intersection Observer for scroll animations
        const revealElements = document.querySelectorAll("[data-reveal]");
        const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        if (prefersReduced) {
            revealElements.forEach(el => el.classList.add("revealed"));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("revealed");
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: "0px 0px -40px 0px" });

            revealElements.forEach(el => observer.observe(el));
        }
    </script>
</body>
</html>
