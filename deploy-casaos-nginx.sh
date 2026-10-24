#!/usr/bin/env bash
# ==============================================================================
# Script Otomasi Deploy SI-MENTILIN ke LinuxServer Nginx di CasaOS
# Target Folder: /DATA/AppData/nginx/config/www/simentilin
# ==============================================================================
set -e

CONTAINER_NAME="nginx-server"
APP_DIR="/DATA/AppData/nginx/config/www/simentilin"
NGINX_CONF_DIR="/DATA/AppData/nginx/config/nginx/site-confs"
REPO_URL="https://github.com/hoirulr/simentilin.git"

echo "=========================================================="
echo "🚀 Memulai Deployment SI-MENTILIN ke CasaOS Nginx"
echo "=========================================================="

# 1. Cek apakah container nginx-server berjalan
if ! docker ps --format '{{.Names}}' | grep -w "^${CONTAINER_NAME}$" > /dev/null; then
    echo "❌ Error: Container '${CONTAINER_NAME}' tidak ditemukan atau sedang mati!"
    echo "   Pastikan aplikasi linuxserver-nginx aktif di CasaOS."
    exit 1
fi

echo "✅ Container '${CONTAINER_NAME}' aktif."

# 2. Deteksi versi PHP & install ekstensi yang dibutuhkan Laravel
echo "📦 Memeriksa versi PHP & memasang ekstensi di dalam container..."
docker exec "${CONTAINER_NAME}" apk update > /dev/null 2>&1 || true

# Deteksi versi PHP utama di container (contoh: 8.5 -> php85, 8.3 -> php83)
PHP_DETECTED_VER=$(docker exec "${CONTAINER_NAME}" php -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;' 2>/dev/null || echo "85")
echo "ℹ️ Terdeteksi versi PHP di container: PHP ${PHP_DETECTED_VER:0:1}.${PHP_DETECTED_VER:1:1}"

# Coba pasang paket spesifik versi, atau fallback ke unversioned php paket
docker exec "${CONTAINER_NAME}" apk add --no-cache \
    php${PHP_DETECTED_VER}-pdo \
    php${PHP_DETECTED_VER}-pdo_mysql \
    php${PHP_DETECTED_VER}-pdo_sqlite \
    php${PHP_DETECTED_VER}-sqlite3 \
    php${PHP_DETECTED_VER}-fileinfo \
    php${PHP_DETECTED_VER}-tokenizer \
    php${PHP_DETECTED_VER}-xml \
    php${PHP_DETECTED_VER}-dom \
    php${PHP_DETECTED_VER}-curl \
    php${PHP_DETECTED_VER}-intl \
    php${PHP_DETECTED_VER}-session \
    php${PHP_DETECTED_VER}-openssl \
    php${PHP_DETECTED_VER}-mbstring \
    php${PHP_DETECTED_VER}-ctype \
    php${PHP_DETECTED_VER}-zip \
    composer > /dev/null 2>&1 || \
docker exec "${CONTAINER_NAME}" apk add --no-cache \
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
    composer > /dev/null 2>&1 || true

# 3. Clone atau Pull source code ke folder target
if [ ! -d "${APP_DIR}/.git" ]; then
    echo "📥 Cloning repository ke ${APP_DIR}..."
    mkdir -p "${APP_DIR}"
    git clone "${REPO_URL}" "${APP_DIR}"
else
    echo "🔄 Mengambil pembaruan terbaru dari GitHub..."
    cd "${APP_DIR}"
    git pull origin main
fi

cd "${APP_DIR}"

# 4. Konfigurasi file .env
if [ ! -f "${APP_DIR}/.env" ]; then
    echo "⚙️ Menyiapkan file .env..."
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    
    # Deteksi IP server otomatis
    HOST_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
    if [ -z "$HOST_IP" ]; then
        HOST_IP="localhost"
    fi
    
    sed -i "s|APP_URL=.*|APP_URL=http://${HOST_IP}:8082|g" "${APP_DIR}/.env"
    sed -i "s|APP_ENV=.*|APP_ENV=production|g" "${APP_DIR}/.env"
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g" "${APP_DIR}/.env"
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|g" "${APP_DIR}/.env"
fi

# Pastikan struktur folder storage & cache Laravel lengkap
mkdir -p "${APP_DIR}/storage/framework/views" \
         "${APP_DIR}/storage/framework/cache/data" \
         "${APP_DIR}/storage/framework/sessions" \
         "${APP_DIR}/storage/logs" \
         "${APP_DIR}/bootstrap/cache"

# Pastikan file database sqlite ada jika menggunakan sqlite
if grep -q "DB_CONNECTION=sqlite" "${APP_DIR}/.env"; then
    mkdir -p "${APP_DIR}/database"
    if [ ! -f "${APP_DIR}/database/database.sqlite" ]; then
        touch "${APP_DIR}/database/database.sqlite"
    fi
fi

# Atur kepemilikan awal agar proses di container dapat menulis
chown -R 1000:1000 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/database" 2>/dev/null || true
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true

# 5. Jalankan Composer Install di dalam container
echo "📦 Menginstall dependensi Composer (vendor)..."
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=php+

# 6. Generate APP_KEY jika belum ada
if ! grep -q "^APP_KEY=base64:" "${APP_DIR}/.env"; then
    echo "🔑 Menghasilkan APP_KEY Laravel..."
    docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan key:generate --force
fi

# 7. Migrasi & Seeding Database
echo "🗄️ Menjalankan migrasi database..."
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan migrate --force

echo "🌱 Menjalankan seeder awal (data wilayah, indikator, akun demo)..."
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan db:seed --force || true

# 8. Buat symbolic link storage
echo "🔗 Menghubungkan storage Laravel..."
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan storage:link || true

# 9. Optimasi cache Laravel untuk production
echo "⚡ Mengoptimalkan cache konfigurasi dan rute..."
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan config:cache
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan route:cache
docker exec -w /config/www/simentilin "${CONTAINER_NAME}" php artisan view:cache

# 10. Konfigurasi Nginx Server Block
echo "🌐 Menyiapkan virtual host Nginx..."
mkdir -p "${NGINX_CONF_DIR}"

# Backup konfigurasi default lama jika ada
if [ -f "${NGINX_CONF_DIR}/default.conf" ] && [ ! -f "${NGINX_CONF_DIR}/default.conf.bak" ]; then
    cp "${NGINX_CONF_DIR}/default.conf" "${NGINX_CONF_DIR}/default.conf.bak"
fi

# Salin konfigurasi vhost SI-MENTILIN ke default.conf
cp "${APP_DIR}/docker/nginx/nginx-simentilin-casaos.conf" "${NGINX_CONF_DIR}/default.conf"

# 11. Perbaiki permission folder (PUID=1000 PGID=1000)
echo "🔒 Mengatur hak akses file & direktori..."
chown -R 1000:1000 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/database"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
if [ -f "${APP_DIR}/database/database.sqlite" ]; then
    chmod 664 "${APP_DIR}/database/database.sqlite"
fi

# 12. Reload Nginx
echo "🔄 Mereload Nginx..."
docker exec "${CONTAINER_NAME}" nginx -s reload

echo ""
echo "=========================================================="
echo "🎉 DEPLOYMENT SELESAI DENGAN SUKSES!"
echo "=========================================================="
HOST_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
echo "Aplikasi dapat diakses melalui browser di:"
echo "👉 http://${HOST_IP:-20.20.20.231}:8082"
echo ""
echo "Akun Pengujian:"
echo "1. Admin DP2KBP3A: admin@simentilin.bangka.go.id / password"
echo "2. Petugas Lapangan: pkb.sungailiat@simentilin.bangka.go.id / password"
echo "=========================================================="
