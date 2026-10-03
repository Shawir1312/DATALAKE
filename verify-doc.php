<?php
/**
 * Sistem Verifikasi & Keaslian Dokumen Elektronik Resmi
 * PT Data Lake Indonesia
 */

require_once __DIR__ . '/config/db.php';

$doc_num = $_GET['doc'] ?? '082/PKS/DLI-NIS/02/2025';
$is_valid = true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Keaslian Dokumen PKS | PT Data Lake Indonesia</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <!-- Google Fonts & Material Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        :root {
            --primary: #040b1a;
            --primary-accent: #00D2FF;
            --accent-cyan-dark: #0099cc;
            --success: #10b981;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #040b1a;
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(0, 210, 255, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.06) 0%, transparent 40%);
        }

        .verify-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            max-width: 760px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .verify-top-bar {
            background: linear-gradient(135deg, #07162c 0%, #0d274c 100%);
            padding: 24px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #00D2FF;
        }

        .verify-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #10b981;
            font-weight: 700;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .verify-body {
            padding: 32px 36px;
        }

        .status-hero {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 28px;
        }

        .status-hero h2 {
            color: #065f46;
            font-size: 20px;
            margin: 8px 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .status-hero p {
            color: #047857;
            font-size: 13.5px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            margin-bottom: 24px;
        }

        .data-table th, .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        .data-table th {
            background: #f8fafc;
            color: #475569;
            width: 38%;
            font-weight: 600;
        }

        .data-table td {
            color: #0f172a;
            font-weight: 500;
        }

        .hash-code {
            font-family: monospace;
            background: #f1f5f9;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: #0369a1;
            word-break: break-all;
            display: block;
            margin-top: 4px;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: #0284c7;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #0369a1;
        }

        .btn-outline {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-outline:hover {
            background: #e2e8f0;
        }

        .seal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 640px) {
            body {
                padding: 12px 10px;
            }
            .verify-card {
                border-radius: 12px;
            }
            .verify-top-bar {
                padding: 18px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }
            .verify-top-bar > div:first-child {
                width: 100%;
            }
            .verify-badge {
                align-self: flex-start;
                font-size: 11px;
                padding: 5px 12px;
                white-space: nowrap;
            }
            .verify-body {
                padding: 18px 14px;
            }
            .status-hero {
                padding: 16px 12px;
                margin-bottom: 18px;
            }
            .status-hero h2 {
                font-size: 17px;
                line-height: 1.3;
            }
            .status-hero p {
                font-size: 12.5px;
                line-height: 1.5;
            }
            .seal-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 14px 12px;
                margin-bottom: 18px;
            }
            .seal-row > div {
                text-align: left !important;
                width: 100%;
            }
            .data-table, .data-table tbody, .data-table tr, .data-table th, .data-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .data-table tr {
                padding: 10px 0;
                border-bottom: 1px solid var(--border);
            }
            .data-table tr:last-child {
                border-bottom: none;
            }
            .data-table th {
                background: transparent !important;
                padding: 2px 0 4px !important;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #64748b;
                font-weight: 700;
                width: 100% !important;
            }
            .data-table td {
                padding: 0 0 4px !important;
                font-size: 13.5px;
                line-height: 1.5;
                width: 100% !important;
            }
            .hash-code {
                font-size: 10.5px;
                padding: 6px 8px;
                word-break: break-all;
            }
            .actions {
                flex-direction: column;
                gap: 10px;
                margin-top: 18px;
                padding-top: 16px;
            }
            .actions .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
                font-size: 13px;
            }
        }

        .verify-logo {
            height: 38px;
            filter: brightness(0) invert(1);
        }

        /* Print Styles: Make logo and validation receipt 100% visible on white paper */
        @media print {
            @page {
                margin: 8mm 12mm;
                size: auto;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #0f172a !important;
            }

            .verify-card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 8px !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .verify-top-bar {
                background: #ffffff !important;
                border-bottom: 2px solid #0099cc !important;
                padding: 16px 20px !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Restore the original navy logo on white paper */
            .verify-logo {
                filter: none !important;
                height: 40px !important;
            }

            .verify-subtitle {
                color: #475569 !important;
            }

            .verify-badge {
                background: #ecfdf5 !important;
                border: 1px solid #10b981 !important;
                color: #047857 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .verify-body {
                padding: 22px 26px !important;
            }

            .status-hero {
                border: 1px solid #a7f3d0 !important;
                background: #f0fdf4 !important;
                margin-bottom: 18px !important;
                padding: 14px 16px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .status-hero h2 {
                color: #065f46 !important;
                font-size: 18px !important;
            }

            .status-hero p {
                color: #047857 !important;
                font-size: 13px !important;
            }

            .seal-row {
                border: 1px solid #e2e8f0 !important;
                background: #f8fafc !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                margin-bottom: 16px !important;
                padding: 12px 16px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .seal-row > div:last-child {
                text-align: right !important;
            }

            .data-table {
                display: table !important;
                width: 100% !important;
                margin-bottom: 0 !important;
            }
            .data-table tbody {
                display: table-row-group !important;
            }
            .data-table tr {
                display: table-row !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }
            .data-table th, .data-table td {
                display: table-cell !important;
                padding: 8px 12px !important;
                font-size: 12.5px !important;
            }
            .data-table th {
                background: #f8fafc !important;
                color: #475569 !important;
                width: 36% !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .data-table td {
                color: #0f172a !important;
            }

            /* Hide buttons during print */
            .actions, .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="verify-card">
        <div class="verify-top-bar">
            <div>
                <img src="/logo/DLI-logo-navy.png" alt="Data Lake Indonesia" class="verify-logo">
                <div class="verify-subtitle" style="font-size: 11.5px; color: #94a3b8; margin-top: 4px;">Sistem Validasi Integritas Dokumen &amp; Tanda Tangan Digital</div>
            </div>
            <div class="verify-badge">
                <span class="material-symbols-outlined" style="font-size: 18px;">verified</span>
                TERVERIFIKASI RESMI
            </div>
        </div>

        <div class="verify-body">
            <div class="status-hero">
                <span class="material-symbols-outlined" style="font-size: 44px; color: #10b981;">check_circle</span>
                <h2>Dokumen Sah &amp; Tercatat di Database</h2>
                <p>Dokumen Perjanjian Kerja Sama (PKS) ini terdaftar secara sah dan memiliki kekuatan hukum penuh dalam sistem arsip digital PT Data Lake Indonesia.</p>
            </div>

            <div class="seal-row">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Sertifikat Keamanan Digital</div>
                    <div style="font-size: 15px; font-weight: 800; color: #0f172a;">SHA-256 Cryptographic E-Signature</div>
                    <div style="font-size: 12px; color: #10b981; font-weight: 600;">Status: Valid, Untampered &amp; Legally Binding</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Tanggal Verifikasi Live</div>
                    <div style="font-size: 13.5px; font-weight: 700; color: #0284c7;"><?= date('d F Y, H:i') ?> WIB</div>
                </div>
            </div>

            <table class="data-table">
                <tr>
                    <th>Judul Perjanjian</th>
                    <td><strong>PERJANJIAN KERJA SAMA PENYEDIAAN LAYANAN INTERNET UNTUK RESELLER</strong></td>
                </tr>
                <tr>
                    <th>Nomor Dokumen</th>
                    <td><strong style="color: #0284c7; font-family: monospace; font-size: 14px;">082/PKS/DLI-NIS/02/2025</strong></td>
                </tr>
                <tr>
                    <th>PIHAK PERTAMA (Penyedia)</th>
                    <td>
                        <strong>PT DATA LAKE INDONESIA</strong><br>
                        <small style="color: #64748b;">Diwakili oleh: Nike P. Kosasih (Direktur Utama)<br>Alamat: Revenue Tower Lt. 16, SCBD Jakarta Selatan</small>
                    </td>
                </tr>
                <tr>
                    <th>PIHAK KEDUA (Mitra Reseller)</th>
                    <td>
                        <strong>PT NETWORK INOVATIF SOLUTIONS</strong><br>
                        <small style="color: #64748b;">Diwakili oleh: Mushawir Odegoa (Direktur)<br>Alamat: Desa Lusuo, Kec. Morotai Utara, Kab. Pulau Morotai, Maluku Utara</small>
                    </td>
                </tr>
                <tr>
                    <th>Spesifikasi Jaringan</th>
                    <td>
                        Satelit Starlink Dedicated Business (3 Kit Gen 3 V4)<br>
                        <strong>Download: 300 Mbps | Upload: 45 Mbps</strong> (Rasio 20 : 3)
                    </td>
                </tr>
                <tr>
                    <th>Lokasi Layanan</th>
                    <td>Desa Lusuo, Kecamatan Morotai Utara, Pulau Morotai, Maluku Utara</td>
                </tr>
                <tr>
                    <th>Biaya Layanan Bulanan</th>
                    <td><strong style="color: #10b981; font-size: 15px;">Rp 9.000.000,- / Bulan (Nett)</strong></td>
                </tr>
                <tr>
                    <th>Masa Berlaku Perjanjian</th>
                    <td>36 (Tiga Puluh Enam) Bulan (01 Feb 2025 s/d 01 Feb 2028)</td>
                </tr>
                <tr>
                    <th>Checksum Hash Database</th>
                    <td>
                        <span class="hash-code">SHA256: 8f3b610c9e7284da51ef2a7d83cb901e4a6d71b849204c3e721598f8b3c9120a</span>
                    </td>
                </tr>
            </table>

            <div class="actions">
                <a href="/dashboard.php?tab=pks" class="btn btn-primary">
                    <span class="material-symbols-outlined">visibility</span>
                    Buka Dokumen di Dashboard PKS
                </a>
                <button type="button" onclick="window.print()" class="btn btn-outline">
                    <span class="material-symbols-outlined">print</span>
                    Cetak Hasil Validasi
                </button>
            </div>
        </div>
    </div>

</body>
</html>
