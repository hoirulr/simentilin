# 🚀 Panduan Deploy SI-MENTILIN ke CasaOS LinuxServer Nginx

Panduan ini digunakan jika Anda sudah memiliki container **`linuxserver/nginx`** yang berjalan di CasaOS dan ingin menempatkan aplikasi **SI-MENTILIN** di direktori:
`/DATA/AppData/nginx/config/www/simentilin`

---

## 📌 Ringkasan Mapping Direktori & Port

Berdasarkan konfigurasi Docker Compose CasaOS Anda:
- **Host Source**: `/DATA/AppData/nginx/config`  
- **Container Target**: `/config`
- **Port Nginx**: `8082` (Container port 80 -> Host port 8082)
- **Letak Folder Aplikasi di Host**: `/DATA/AppData/nginx/config/www/simentilin`
- **Letak Folder Aplikasi di Container**: `/config/www/simentilin`
- **Web Root Nginx**: `/config/www/simentilin/public`

---

## ⚡ Cara 1: Deploy Otomatis Menggunakan Script (Paling Mudah)

Kami telah menyiapkan script otomatis `deploy-casaos-nginx.sh`.

### 1. Masuk ke Terminal Server CasaOS
Buka **Terminal CasaOS** dari Web UI (atau via SSH):
```bash
ssh <user>@20.20.20.231
```

### 2. Download dan Jalankan Script Deployment
Jalankan perintah satu baris berikut di terminal CasaOS:

```bash
curl -fsSL https://raw.githubusercontent.com/hoirulr/simentilin/main/deploy-casaos-nginx.sh -o /tmp/deploy.sh && bash /tmp/deploy.sh
```

> **Apa yang dilakukan oleh script ini secara otomatis?**
> 1. Memeriksa container `nginx-server`.
> 2. Memasang ekstensi PHP yang dibutuhkan Laravel (`pdo`, `pdo_sqlite`, `pdo_mysql`, `mbstring`, `fileinfo`, `composer`, dll) di dalam container.
> 3. Meng-clone atau mem-pull source code SI-MENTILIN ke `/DATA/AppData/nginx/config/www/simentilin`.
> 4. Menginstall dependensi `vendor` via `composer install --no-dev`.
> 5. Menyiapkan `.env` dan `APP_KEY`.
> 6. Menjalankan migrasi database & seeding awal.
> 7. Mengonfigurasi Nginx server block (`/DATA/AppData/nginx/config/nginx/site-confs/default.conf`) agar mengarah ke `public/` Laravel.
> 8. Mengatur permission `1000:1000` pada folder `storage` & `database`.
> 9. Me-reload Nginx.

---

## 🛠️ Cara 2: Deploy Manual Langkah demi Langkah

Jika Anda lebih memilih mengeksekusi perintah secara manual:

### Langkah 1: Clone Repository ke Folder Target
```bash
# Masuk ke direktori www nginx
mkdir -p /DATA/AppData/nginx/config/www/simentilin
cd /DATA/AppData/nginx/config/www/simentilin

# Clone project
git clone https://github.com/hoirulr/simentilin.git .
```

### Langkah 2: Install Ekstensi PHP & Composer di Container Nginx
Container `linuxserver/nginx` berbasis Alpine Linux. Jalankan perintah ini untuk menginstal paket PHP yang dibutuhkan:

```bash
docker exec nginx-server apk update
docker exec nginx-server apk add --no-cache \
    php83-pdo \
    php83-pdo_mysql \
    php83-pdo_sqlite \
    php83-sqlite3 \
    php83-fileinfo \
    php83-tokenizer \
    php83-xml \
    php83-dom \
    php83-curl \
    php83-intl \
    php83-session \
    composer
```

### Langkah 3: Siapkan File Konfigurasi `.env`
```bash
cd /DATA/AppData/nginx/config/www/simentilin
cp .env.example .env

# Buat file database SQLite (jika menggunakan SQLite bawaan)
touch database/database.sqlite
```

Sesuaikan baris berikut di `.env`:
```env
APP_NAME=SI-MENTILIN
APP_ENV=production
APP_DEBUG=false
APP_URL=http://20.20.20.231:8082

DB_CONNECTION=sqlite
# Atau jika Anda menggunakan MariaDB di CasaOS:
# DB_CONNECTION=mysql
# DB_HOST=ip_mariadb
# DB_PORT=3306
# DB_DATABASE=simentilin
# DB_USERNAME=root
# DB_PASSWORD=password_db
```

### Langkah 4: Install Vendor & Jalankan Artisan di Dalam Container
```bash
# Install paket PHP
docker exec -w /config/www/simentilin nginx-server composer install --no-dev --optimize-autoloader

# Generate APP_KEY
docker exec -w /config/www/simentilin nginx-server php artisan key:generate --force

# Migrasi & Seeding
docker exec -w /config/www/simentilin nginx-server php artisan migrate --force
docker exec -w /config/www/simentilin nginx-server php artisan db:seed --force

# Symbolic link storage
docker exec -w /config/www/simentilin nginx-server php artisan storage:link

# Cache konfigurasi
docker exec -w /config/www/simentilin nginx-server php artisan config:cache
docker exec -w /config/www/simentilin nginx-server php artisan route:cache
docker exec -w /config/www/simentilin nginx-server php artisan view:cache
```

### Langkah 5: Konfigurasi Virtual Host Nginx
Salin konfigurasi Nginx SI-MENTILIN ke direktori `site-confs`:

```bash
cp /DATA/AppData/nginx/config/www/simentilin/docker/nginx/nginx-simentilin-casaos.conf /DATA/AppData/nginx/config/nginx/site-confs/default.conf
```

Isi konfigurasi `/DATA/AppData/nginx/config/nginx/site-confs/default.conf` tersebut adalah:
```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /config/www/simentilin/public;
    index index.php index.html index.htm;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, no-transform";
    }
}
```

### Langkah 6: Atur Izin Akses (Permissions)
```bash
chown -R 1000:1000 /DATA/AppData/nginx/config/www/simentilin/storage \
                   /DATA/AppData/nginx/config/www/simentilin/bootstrap/cache \
                   /DATA/AppData/nginx/config/www/simentilin/database

chmod -R 775 /DATA/AppData/nginx/config/www/simentilin/storage \
             /DATA/AppData/nginx/config/www/simentilin/bootstrap/cache

chmod 664 /DATA/AppData/nginx/config/www/simentilin/database/database.sqlite 2>/dev/null || true
```

### Langkah 7: Reload Nginx
```bash
docker exec nginx-server nginx -s reload
```

---

## 🌐 Akses Aplikasi

Buka browser dan kunjungi:
**`http://20.20.20.231:8082`**

### Kredensial Pengujian:
- **Admin DP2KBP3A (Super Admin)**:
  - Email: `admin@simentilin.bangka.go.id`
  - Password: `password`
- **Petugas Lapangan (Sungailiat)**:
  - Email: `pkb.sungailiat@simentilin.bangka.go.id`
  - Password: `password`
