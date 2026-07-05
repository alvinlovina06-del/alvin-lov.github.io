<<<<<<< HEAD
# UASKTE App — Secure User Management PWA

Project Akhir: Website berbasis PWA untuk pengelolaan user dengan multi-layer security.

## 🔐 Fitur Keamanan
- **Google SSO** — Login menggunakan akun Google (OAuth 2.0)
- **SHA512 Email Hashing** — Email di-enkripsi dengan SHA512 di database
- **OTP WhatsApp** — Verifikasi 2 langkah via WhatsApp (Fonnte API)
- **Biometric Security** — Verifikasi sidik jari/wajah untuk akses admin (WebAuthn)
- **Audit Logging** — Setiap perubahan di tabel user tercatat waktunya

## 📋 Prasyarat
- XAMPP (PHP 8.0+, MySQL/MariaDB, Apache)
- Composer ([download](https://getcomposer.org/download/))
- Akun Google Cloud Console (untuk OAuth)
- Akun Fonnte.com (untuk WhatsApp API)

## 🚀 Cara Install

### 1. Clone / Extract ke XAMPP
```
Letakkan project di: C:\xampp\htdocs\uaskte\
```

### 2. Install Dependencies
```bash
cd C:\xampp\htdocs\uaskte
composer install
```

### 3. Setup Database
- Buka phpMyAdmin (`http://localhost/phpmyadmin`)
- Import file `database/schema.sql`
- Atau jalankan via command line:
```bash
mysql -u root < database/schema.sql
```

### 4. Konfigurasi Environment
- Copy `.env.example` ke `.env`
- Isi semua variabel:

```env
# Google OAuth (dari Google Cloud Console)
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret

# Fonnte WhatsApp API
FONNTE_TOKEN=your-fonnte-token

# Database (sesuaikan jika perlu)
DB_HOST=localhost
DB_NAME=uaskte_db
DB_USER=root
DB_PASS=
```

### 5. Setup Google OAuth
1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat Project baru
3. Enable **Google+ API** atau **People API**
4. Buat **OAuth 2.0 Client ID** (Web Application)
5. Tambah Authorized Redirect URI: `http://localhost/uaskte/public/callback.php`
6. Copy Client ID dan Secret ke `.env`

### 6. Setup Fonnte (WhatsApp OTP)
1. Daftar di [fonnte.com](https://fonnte.com)
2. Tambah device (nomor WhatsApp Anda)
3. Copy API Token ke `.env`

### 7. Akses Website
```
http://localhost/uaskte/public/
```

## 📁 Struktur Project
```
uaskte/
├── config/          # Konfigurasi database & app
├── database/        # Schema SQL
├── public/          # Web-accessible files
│   ├── admin/       # Halaman admin
│   ├── api/         # API endpoints
│   ├── assets/      # CSS, JS, gambar
│   └── *.php        # Halaman utama
├── src/             # Backend services (PHP)
├── templates/       # Shared HTML templates
├── .env             # Environment variables
└── composer.json    # PHP dependencies
```

## 👤 Default Admin
- Email: `admin@example.com` (ganti di database/schema.sql sebelum import)
- Phone: `628123456789` (ganti dengan nomor WhatsApp Anda)

## 📱 PWA
Website ini mendukung PWA (Progressive Web App):
- Bisa di-install di HP/Desktop
- Offline support
- Push notifications (coming soon)

## ⚙️ Teknologi
- PHP 8.0+
- MySQL / MariaDB
- Google OAuth 2.0 (league/oauth2-google)
- Fonnte WhatsApp API
- WebAuthn (Biometric)
- PWA (Service Worker + Manifest)
- Vanilla CSS + JavaScript
=======
# alvin-lov.github.io
>>>>>>> 0faa9b92844b361c15470192b1d5437e2add1951
