# 🚀 Panduan Deploy SI-MENTILIN ke CasaOS Nginx (PHP 8.5.12 CLI)

Panduan ini disusun khusus untuk deployment aplikasi **SI-MENTILIN** pada lingkungan **CasaOS** menggunakan container **`linuxserver/nginx`** dengan lingkungan **PHP 8.5.12 (CLI)**.

---

## 📌 Ringkasan Konfigurasi & Arsitektur

- **Host Source Path**: `/DATA/AppData/nginx/config`
- **Container Target Path**: `/config`
- **Direktori Aplikasi di Host**: `/DATA/AppData/nginx/config/www/simentilin`
- **Direktori Aplikasi di Container**: `/config/www/simentilin`
- **Web Root Nginx**: `/config/www/simentilin/public`
- **Port Akses Web**: `8082` (Container port 80 -> Host port 8082)
- **Versi PHP**: **PHP 8.5.12 (CLI)**
- **Database Default**: SQLite (terisolasi dan cepat) atau MariaDB CasaOS

> **Catatan Kompatibilitas PHP 8.5:**  
> SI-MENTILIN dibangun menggunakan Laravel 13 (`php: ^8.3`), yang secara resmi mendukung PHP 8.3 hingga PHP 8.5+. Untuk menghindari kendala *version locking* dari dependensi vendor lama saat menjalankan Composer di PHP 8.5, gunakan selalu flag `--ignore-platform-req=php+`.

---

## ⚡ Metode 1: Deploy Otomatis via Script (Direkomendasikan)

Script `deploy-casaos-nginx.sh` telah diperbarui dengan **deteksi otomatis versi PHP** (mendukung PHP 8.5, 8.4, dan 8.3).

### 1. Buka Terminal CasaOS
Akses server via SSH atau buka menu **Terminal** di dashboard web CasaOS:
```bash
ssh <username>@20.20.20.231
```

### 2. Jalankan Satu Baris Perintah Deployment
```bash
curl -fsSL https://raw.githubusercontent.com/hoirulr/simentilin/main/deploy-casaos-nginx.sh -o /tmp/deploy.sh && bash /tmp/deploy.sh
```

---

## 🛠️ Metode 2: Deploy Manual Langkah demi Langkah (PHP 8.5.12)

Gunakan metode ini jika Anda ingin mengontrol setiap langkah instalasi atau jika Anda menemui kendala pada script otomatis.

### Langkah 1: Clone Repository ke Folder Target Nginx
```bash
# Buat folder jika belum ada
mkdir -p /DATA/AppData/nginx/config/www/simentilin
cd /DATA/AppData/nginx/config/www/simentilin

# Clone project SI-MENTILIN
git clone https://github.com/hoirulr/simentilin.git .
```

---

### Langkah 2: Verifikasi & Siapkan Ekstensi PHP 8.5

Ada 2 kemungkinan letak PHP 8.5.12 Anda:

#### Opsi A: Jika PHP 8.5.12 berada di dalam Container Nginx (`nginx-server`)
Cek versi PHP di dalam container:
```bash
docker exec nginx-server php -v
```
Jika container menggunakan Alpine Linux, pasang paket ekstensi PHP 8.5 & Composer:
```bash
docker exec nginx-server apk update

# Pasang ekstensi PHP 8.5 (atau fallback ke paket umum php)
docker exec nginx-server apk add --no-cache \
    php85-pdo \
    php85-pdo_mysql \
    php85-pdo_sqlite \
    php85-sqlite3 \
    php85-fileinfo \
    php85-tokenizer \
    php85-xml \
    php85-dom \
    php85-curl \
    php85-intl \
    php85-session \
    php85-openssl \
    php85-mbstring \
    php85-ctype \
    php85-zip \
    composer 2>/dev/null || \
docker exec nginx-server apk add --no-cache \
    php-pdo \
    php-pdo_mysql \
    php-pdo_sqlite \
    php-sqlite3 \
    php-fileinfo \
    php-tokenizer \
    php-xml \
    php-dom \
    php-curl \
    php-intl \
    php-session \
    php-openssl \
    php-mbstring \
    php-ctype \
    php-zip \
    composer
```

#### Opsi B: Jika PHP 8.5.12 (CLI) terpasang di Host Server CasaOS (Debian/Ubuntu)
Jika perintah `php -v` langsung di terminal server Anda menampilkan `PHP 8.5.12 (cli)`:
```bash
# Pastikan ekstensi PHP 8.5 di Host OS lengkap
sudo apt update
sudo apt install -y \
    php8.5-cli \
    php8.5-common \
    php8.5-sqlite3 \
    php8.5-mysql \
    php8.5-curl \
    php8.5-mbstring \
    php8.5-xml \
    php8.5-zip \
    php8.5-intl \
    unzip git composer
```

---

### Langkah 3: Siapkan File Environment `.env`
Di folder `/DATA/AppData/nginx/config/www/simentilin`:
```bash
cp .env.example .env

# Siapkan file database SQLite
mkdir -p database
touch database/database.sqlite
```

Edit file `.env` (misal menggunakan `nano .env`):
```env
APP_NAME=SI-MENTILIN
APP_ENV=production
APP_DEBUG=false
APP_URL=http://20.20.20.231:8082

DB_CONNECTION=sqlite
# DB_DATABASE=/config/www/simentilin/database/database.sqlite
```

---

### Langkah 4: Install Vendor Dependensi via Composer (PHP 8.5 Safe)

> [!IMPORTANT]
> Tambahkan parameter `--ignore-platform-req=php+` agar Composer mengizinkan dependensi berjalan mulus di PHP 8.5.

**Jika mengeksekusi di dalam container Nginx:**
```bash
docker exec -w /config/www/simentilin nginx-server composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=php+
```

**Atau jika mengeksekusi langsung di Host CLI:**
```bash
cd /DATA/AppData/nginx/config/www/simentilin
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=php+
```

---

### Langkah 5: Generate Key, Migrasi & Seeding Database

Jalankan perintah Artisan (contoh melalui container):
```bash
# Generate APP_KEY
docker exec -w /config/www/simentilin nginx-server php artisan key:generate --force

# Migrasi tabel database
docker exec -w /config/www/simentilin nginx-server php artisan migrate --force

# Seeding data master wilayah, indikator skoring EWS, dan akun demo
docker exec -w /config/www/simentilin nginx-server php artisan db:seed --force

# Storage link untuk file upload aset
docker exec -w /config/www/simentilin nginx-server php artisan storage:link

# Cache konfigurasi untuk performa maksimal production
docker exec -w /config/www/simentilin nginx-server php artisan config:cache
docker exec -w /config/www/simentilin nginx-server php artisan route:cache
docker exec -w /config/www/simentilin nginx-server php artisan view:cache
```

*(Catatan: Jika menjalankan via Host CLI, Anda cukup ketik `php artisan migrate --force`, dst. langsung di direktori `/DATA/AppData/nginx/config/www/simentilin`)*

---

### Langkah 6: Konfigurasi Virtual Host Nginx

Salin file konfigurasi Nginx SI-MENTILIN ke direktori `site-confs`:
```bash
cp /DATA/AppData/nginx/config/www/simentilin/docker/nginx/nginx-simentilin-casaos.conf /DATA/AppData/nginx/config/nginx/site-confs/default.conf
```

Pastikan isi `/DATA/AppData/nginx/config/nginx/site-confs/default.conf` seperti berikut:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /config/www/simentilin/public;
    index index.php index.html index.htm;

    client_max_body_size 50M;

    # Livewire & Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM FastCGI Handler
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300;
    }

    # Keamanan: Tolak akses file sensitif
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Blokir akses langsung ke storage internal & database SQLite
    location ~ ^/(storage/logs|database) {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Cache aset statis Vite (Tailwind CSS, JS, Gambar)
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, no-transform";
    }
}
```

---

### Langkah 7: Pengaturan Hak Akses (Permissions)

Container `linuxserver/nginx` umumnya berjalan dengan user `PUID=1000` dan `PGID=1000`. Atur hak kepemilikan file agar Laravel dapat menulis log dan database SQLite:

```bash
chown -R 1000:1000 /DATA/AppData/nginx/config/www/simentilin/storage \
                   /DATA/AppData/nginx/config/www/simentilin/bootstrap/cache \
                   /DATA/AppData/nginx/config/www/simentilin/database

chmod -R 775 /DATA/AppData/nginx/config/www/simentilin/storage \
             /DATA/AppData/nginx/config/www/simentilin/bootstrap/cache

chmod 664 /DATA/AppData/nginx/config/www/simentilin/database/database.sqlite 2>/dev/null || true
```

---

### Langkah 8: Reload Nginx Server

Muat ulang konfigurasi Nginx di container:
```bash
docker exec nginx-server nginx -s reload
```

---

## 🌐 Uji Coba & Akses Sistem

Buka browser Anda dan akses alamat:
👉 **[http://20.20.20.231:8082](http://20.20.20.231:8082)**

### Kredensial Akun Pengujian:
| Peran (Role) | Email | Password | Cakupan Wilayah |
|---|---|---|---|
| **Admin Dinas (Super Admin)** | `admin@simentilin.bangka.go.id` | `password` | Seluruh Kabupaten Bangka |
| **Petugas Lapangan (PKB Sungailiat)** | `pkb.sungailiat@simentilin.bangka.go.id` | `password` | Kecamatan Sungailiat |

---

## 🔍 Troubleshooting Masalah Umum

### 1. Error `tempnam(): file created in the system's temporary directory`
**Penyebab:**
Folder `storage/framework/views` belum ada atau user PHP-FPM di container tidak memiliki izin tulis (*write permission*). PHP terpaksa membuat file sementara di `/tmp` sehingga Laravel memicu exception ini saat Blade me-render halaman.

**Solusi Cepat (Jalankan di Terminal Server):**
```bash
# Ganti <path_aplikasi> sesuai folder Anda (misal /DATA/AppData/nginx/config/www/simentilin atau ews-rsdb)
APP_PATH="/DATA/AppData/nginx/config/www/simentilin"

# 1. Pastikan seluruh subfolder storage & cache dibuat
mkdir -p $APP_PATH/storage/framework/{views,cache/data,sessions}
mkdir -p $APP_PATH/storage/logs
mkdir -p $APP_PATH/bootstrap/cache

# 2. Berikan hak akses penuh (PUID/PGID 1000 atau www-data)
chown -R 1000:1000 $APP_PATH/storage $APP_PATH/bootstrap/cache
chmod -R 775 $APP_PATH/storage $APP_PATH/bootstrap/cache

# 3. Bersihkan & re-cache view di dalam container
docker exec nginx-server php artisan view:clear
```

### 2. Error `SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: 127.0.0.1)`
**Penyebab:**
Di dalam container Docker, alamat `127.0.0.1` adalah *localhost container Nginx itu sendiri*, bukan host CasaOS atau container database. Akibatnya container tidak menemukan service MySQL di port 3306.

**Solusi Opsi A (Paling Mudah - Gunakan SQLite):**
Buka `.env` di folder project Anda, ubah:
```env
DB_CONNECTION=sqlite
# Komentari atau hapus baris DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```
Pastikan file database dibuat:
```bash
touch database/database.sqlite
chmod 664 database/database.sqlite
chown 1000:1000 database/database.sqlite
```
Lalu jalankan migrasi kembali:
```bash
docker exec -w /config/www/simentilin nginx-server php artisan migrate --force
```

**Solusi Opsi B (Tetap Menggunakan MySQL/MariaDB CasaOS):**
1. Ganti `DB_HOST` di `.env` dari `127.0.0.1` menjadi **IP Host CasaOS** atau IP Gateway Docker:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=20.20.20.231       # atau 172.17.0.1
   DB_PORT=3306
   DB_DATABASE=simentilin
   DB_USERNAME=nama_user_db
   DB_PASSWORD=password_db
   ```
2. Pastikan database `simentilin` sudah dibuat di MySQL/MariaDB server Anda:
   ```sql
   CREATE DATABASE IF NOT EXISTS simentilin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Pastikan user MySQL memiliki hak akses dari IP container (bukan hanya `localhost`).

### 3. Error `Composer: Your Composer dependencies require a PHP version "..."`
Tambahkan flag `--ignore-platform-req=php+` saat menjalankan `composer install`.

### 4. Error `could not find driver (SQL: select ...)`
Ekstensi SQLite belum aktif. Jalankan:
- Di container: `docker exec nginx-server apk add php85-sqlite3 php85-pdo_sqlite`
- Di host: `sudo apt install php8.5-sqlite3`
Lalu restart/reload container.

### 5. Livewire 4 / Blade Bermasalah saat Update
Bersihkan seluruh view & route cache:
```bash
docker exec -w /config/www/simentilin nginx-server php artisan optimize:clear
```

### 6. Akses via Cloudflare Tunnel: `Mixed content: ... update Blocked` saat Login
**Penyebab:**
Cloudflare Tunnel melayani pengunjung menggunakan **HTTPS**, namun meneruskan trafik ke container Nginx lokal melalui **HTTP biasa**. Karena Nginx menerima koneksi HTTP, Laravel & Livewire 4 mengira request tidak aman dan membuat URL endpoint `/livewire/update` menggunakan protokol `http://`. Browser memblokir request HTTP tersebut karena halaman induk dibuka melalui HTTPS (*Mixed Content violation*), sehingga tombol login atau submit tidak merespons.

**Solusi Cepat:**
1. **Pastikan `APP_URL` di `.env` menggunakan `https://`**:
   Buka file `.env` di server CasaOS Anda:
   ```env
   APP_URL=https://nama-domain-tunnel-anda.com
   ```
2. **Aktifkan Trust Proxies & Force HTTPS**:
   Pastikan kode aplikasi terbaru sudah di-pull (`git pull origin main`) yang sudah memuat:
   - `$middleware->trustProxies(at: '*');` pada `bootstrap/app.php`
   - `URL::forceScheme('https');` pada `app/Providers/AppServiceProvider.php`
3. **Bersihkan Cache Konfigurasi**:
   ```bash
   docker exec -w /config/www/simentilin nginx-server php artisan optimize:clear
   docker exec -w /config/www/simentilin nginx-server php artisan config:clear
   ```
4. **Perbarui file vhost Nginx dan Reload**:
   ```bash
   cp /DATA/AppData/nginx/config/www/simentilin/docker/nginx/nginx-simentilin-casaos.conf /DATA/AppData/nginx/config/nginx/site-confs/default.conf
   docker exec nginx-server nginx -s reload
   ```
