# 🐳 Panduan Deploy SI-MENTILIN ke Server CasaOS Docker (20.20.20.231)

Panduan praktis untuk menjalankan aplikasi **SI-MENTILIN** di server uji coba CasaOS menggunakan Docker & Docker Compose.

---

## 📌 Ringkasan Konfigurasi Server

- **IP Server**: `20.20.20.231`
- **Dashboard CasaOS**: [http://20.20.20.231:8000](http://20.20.20.231:8000)
- **Port Aplikasi SI-MENTILIN**: `8088` ([http://20.20.20.231:8088](http://20.20.20.231:8088))
- **SSH Port**: `22`
- **Database**: MariaDB 10.11 (dalam container terisolasi `simentilin-db`)

---

## 🚀 Metode 1: Deploy Cepat via Terminal SSH (Sangat Disarankan)

Metode ini paling stabil dan otomatis meng-compile aset Vite + Tailwind CSS serta migrasi & seeding database.

### 1. Masuk ke Server via SSH
Buka terminal (PowerShell atau Terminal CasaOS):
```bash
ssh <username>@20.20.20.231
```
*(Ganti `<username>` dengan username Linux server Anda, contoh: `casaos`, `root`, atau `ubuntu`)*

### 2. Clone Repository ke Direktori AppData CasaOS
```bash
# Masuk ke direktori AppData CasaOS
mkdir -p /DATA/AppData/simentilin
cd /DATA/AppData/simentilin

# Clone repository
git clone https://github.com/hoirulr/simentilin.git .

# Siapkan file environment Docker
cp .env.docker .env
```

### 3. Build & Jalankan Container
```bash
docker compose up -d --build
```

> **Proses yang berjalan otomatis saat start:**
> 1. Multi-stage build meng-compile aset Vite (CSS/JS).
> 2. Menyiapkan container PHP 8.3 FPM + Nginx + MariaDB.
> 3. Menunggu database aktif.
> 4. Menjalankan `php artisan migrate --force`.
> 5. Menjalankan `php artisan db:seed --force` (data wilayah & akun demo).
> 6. Membuat symbolic link `storage:link`.

### 4. Cek Log Container
Untuk memastikan semua layanan berjalan lancar:
```bash
docker compose logs -f app
```
*(Tekan `Ctrl+C` untuk keluar dari log)*

---

## 🖥️ Metode 2: Deploy via Web UI CasaOS (Custom Install)

Jika Anda ingin menginstall langsung melalui tampilan web browser CasaOS:

1. Buka browser dan akses **[http://20.20.20.231:8000](http://20.20.20.231:8000)**.
2. Di dashboard CasaOS, klik ikon **`+`** (di baris Apps) lalu pilih **`Install a customized app`**.
3. Di pojok kanan atas modal yang muncul, klik tombol **`Import`** (ikon file dengan panah).
4. Salin dan tempelkan seluruh isi file [docker-compose.yml](file:///d:/laragon/www/simentilin/docker-compose.yml).
5. Klik **`Submit`**. CasaOS akan otomatis membaca:
   - Nama aplikasi: **SI-MENTILIN**
   - Port web: **8088**
   - Database service: **MariaDB 10.11**
6. Klik tombol **`Install`** dan tunggu proses pembuatan container selesai.

---

## 🔑 Akun Login Pengujian

Setelah aplikasi aktif di **[http://20.20.20.231:8088](http://20.20.20.231:8088)**:

| Role | Email | Password |
|---|---|---|
| **Admin Dinas (DP2KBP3A)** | `admin@simentilin.bangka.go.id` | `password` |
| **Petugas Lapangan (Sungailiat)** | `pkb.sungailiat@simentilin.bangka.go.id` | `password` |

---

## 🔄 Pembaruan Aplikasi di Masa Depan (Update/Pull)

Jika ada commit/fitur baru yang dipush ke GitHub:
```bash
cd /DATA/AppData/simentilin
git pull origin main
docker compose up -d --build
```
