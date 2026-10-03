# PT Data Lake Indonesia (datalake.id Clone) — Web Portal & Admin Dashboard

Aplikasi web portal resmi clone **PT Data Lake Indonesia** (Authorized Distributor Starlink Indonesia - [datalake.id](https://datalake.id/)) berbasis **Full Native PHP** dengan dukungan **Dual Database (SQLite & MySQL)**, sistem autentikasi lengkap (Register, Login, Role-based Access Control), formulir konsultasi permohonan Starlink terintegrasi, dan **Dashboard Administrator Modern**.

Dilengkapi dengan wizard instalasi otomatis web (**`install.php`**) untuk kemudahan pemasangan ke server cPanel, VPS, XAMPP, maupun shared hosting dalam hitungan detik.

---

## 🌟 Fitur Utama

### 1. Desain & Elemen Identik 100% (datalake.id)
- **Aset & Identitas Visual Asli**: Menggunakan logo resmi Data Lake Indonesia, tipografi **Plus Jakarta Sans**, **Inter**, dan font aksen serif **PT Serif** (*"Konektivitas Tanpa Batas"*).
- **Palet Warna Presisi**: Deep Navy `#040b1a` & `#1A365D`, serta aksen Cyan `#00D2FF`.
- **Navigasi Lengkap**: Header sticky dengan efek glassmorphism saat scroll, switcher mode Personal/Bisnis, switcher bahasa, dan mobile drawer menu.
- **Hero & Feature Sections**: Banner peta jaringan Starlink Indonesia, kartu produk *Starlink Kit Gen 3 V4* dan *Starlink Mini / Aksesoris*.
- **Keunggulan & Split Cards**: 2 featured card (*Layanan Terkelola*, *Dukungan Lokal*) dan 4 supporting card (*Dashboard Pelanggan*, *Mitra Garansi*, *Penagihan IDR*, *Cakupan Nasional*).
- **10 Mitra Distributor Retail**: Grid logo resmi Apollo Gadget, Erafone, Electronic City, Hartono, Hypermart, Urban Republic, dll.
- **Official Web Store Mandiri (`store.php`)**: Toko online resmi Starlink bawaan mandiri dengan katalog produk lengkap (Starlink Standard Gen 3, Starlink Mini, Wi-Fi 6 Router, Ethernet Adapter, Pivot Mount, Pipe Adapter), formulir pemesanan instan, dan integrasi WhatsApp ke admin tanpa ketergantungan atau komunikasi ke website eksternal.
- **Pengaturan WhatsApp & Kontak Mandiri**: Seluruh tautan WhatsApp dan nomor telepon CS/Sales diatur dinamis via database dan dapat diubah kapan saja langsung melalui Dashboard Admin (Tab Profil) dalam 1 klik.
- **Floating WhatsApp FAB**: Tombol konsultasi WhatsApp dinamis langsung ke kontak admin yang ditentukan.
- **Formulir Konsultasi Interaktif**: Pengunjung dapat mengajukan permintaan paket dan tersimpan otomatis ke database.

### 2. Sistem Autentikasi & Keamanan (Full PHP)
- **Registrasi Akun (`register.php`)**: Validasi nama, username, email, nomor HP/WA, dan konfirmasi kata sandi.
- **Login Akun (`login.php`)**: Mendukung login dengan username maupun email, proteksi brute-force, dan 1-click autofill untuk akun demo.
- **Enkripsi Standar Industri**: Menggunakan algoritma `PASSWORD_BCRYPT`.
- **Proteksi CSRF**: Token keamanan unik untuk setiap request formulir.
- **Session & Audit Log**: Pencatatan aktivitas pengguna dan riwayat login ke dalam tabel `activity_logs`.

### 3. Dashboard Administrator (`dashboard.php`)
- **Ringkasan Eksekutif (Overview)**: Kartu metrik total user, status permohonan layanan, indikator koneksi database aktif, dan log sistem.
- **Manajemen Pengguna (User Management)**: Fitur CRUD lengkap (tambah pengguna baru, aktifkan/nonaktifkan akun, hapus pengguna, manajemen hak akses admin/user).
- **Manajemen Leads & Konsultasi**: Memantau seluruh permohonan paket Starlink yang masuk, filter status (*Baru / Dihubungi / Selesai*), dan tombol langsung untuk chat via WhatsApp ke calon pelanggan.
- **Pengaturan Profil & Keamanan**: Pembaruan data diri dan penggantian kata sandi dengan verifikasi password lama.

### 4. Web Installer Server (`install.php`)
- **Pemeriksaan Kompatibilitas**: Cek otomatis versi PHP, ekstensi PDO, driver DB, dan izin tulis folder.
- **Pilihan Mesin Database**:
  - **SQLite (1-Klik Siap Pakai)**: Langsung jalan tanpa perlu konfigurasi MySQL atau database hosting.
  - **MySQL / MariaDB**: Dilengkapi fitur **"Tes Koneksi Database"** dan otomatis `CREATE DATABASE IF NOT EXISTS`.
- **Pembuatan Akun Admin Kustom**: Menentukan username dan sandi administrator saat instalasi.
- **Keamanan Kunci (`installed.lock`)**: Mencegah penimpaan konfigurasi oleh pihak lain setelah website online.

---

## 🚀 Panduan Instalasi ke Server Hosting

### Opsi 1: Menggunakan Web Installer (`install.php`) — Sangat Mudah
1. Upload seluruh berkas proyek ini ke folder web root server Anda (misal `public_html/` di cPanel atau root direktori VPS).
2. Buka browser dan arahkan ke alamat URL installer:
   ```text
   https://domain-anda.com/install.php
   ```
3. Pilih tipe database yang diinginkan:
   - **SQLite**: Langsung klik pasang (tanpa setup database tambahan).
   - **MySQL**: Masukkan Host, Nama Database, User, dan Password, lalu klik *"Tes Koneksi"*.
4. Masukkan nama dan password Administrator yang Anda inginkan.
5. Klik **"Pasang & Jalankan Sekarang"**.
6. Selesai! Anda akan langsung diarahkan ke Dashboard Admin.

### Opsi 2: Menjalankan di Komputer Lokal (Development)
Pastikan PHP (minimal versi 7.4) sudah terpasang di komputer Anda:
```bash
# Masuk ke direktori proyek
cd datalake

# Jalankan PHP Built-in Server
php -S 127.0.0.1:8000
```
Buka browser ke [http://127.0.0.1:8000/](http://127.0.0.1:8000/).

---

## 🔑 Kredensial Default Administrator
Jika menggunakan database bawaan tanpa menjalankan installer ulang:
- **Username**: `admin`
- **Password**: `admin123`
- **Email**: `admin@datalake.id`

---

## 📁 Struktur Berkas Direktori

```text
├── index.php                 # Landing page utama Data Lake Indonesia
├── login.php                 # Halaman login portal
├── register.php              # Halaman registrasi akun baru
├── logout.php                # Script logout sesi aman
├── dashboard.php             # Dasbor Administrator & Manajemen Leads
├── install.php               # Wizard installer server interaktif
├── isntall.php               # Alias pengarah typo untuk install.php
├── config/
│   ├── db.php                # Handler koneksi database PDO (SQLite / MySQL)
│   └── config.php            # Berkas konfigurasi hasil generate installer
├── database/
│   ├── datalake.sqlite       # Berkas database SQLite
│   └── schema.sql            # Skema SQL untuk import phpMyAdmin / MySQL
├── includes/
│   └── auth.php              # Helper otentikasi, sesi, proteksi CSRF
├── assets/
│   ├── css/
│   │   ├── custom-auth.css   # Styling halaman login/register
│   │   └── dashboard.css     # Styling halaman admin dashboard
│   ├── logo/                 # Logo resmi Data Lake Indonesia
│   └── fonts/                # Berkas lokal WOFF2 fonts
├── _astro/                   # Berkas CSS & JS bundle asli
└── content/                  # Banner produk, foto teknisi, & logo mitra retail
```

---

## 📄 Lisensi
Hak cipta materi, logo, dan merek dagang Starlink merupakan milik pihak terkait. Proyek ini dikembangkan untuk kebutuhan integrasi portal web Data Lake Indonesia.
