#!/bin/sh
set -e

# Buat direktori logs & run
mkdir -p /var/log/nginx /var/run/nginx /var/log/supervisor /run

# Pastikan direktori framework Laravel & permission
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Jika .env belum ada, copy dari .env.docker atau .env.example
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.docker ]; then
        cp /var/www/html/.env.docker /var/www/html/.env
    elif [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    fi
fi

# Generate APP_KEY jika belum terisi
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "Generating application encryption key..."
    php artisan key:generate --force
fi

# Tunggu database siap jika menggunakan MySQL/MariaDB
if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "mariadb" ]; then
    DB_HOST_TARGET="${DB_HOST:-db}"
    DB_PORT_TARGET="${DB_PORT:-3306}"
    echo "Menunggu database di ${DB_HOST_TARGET}:${DB_PORT_TARGET} siap..."
    MAX_TRIES=60
    COUNT=0
    while ! nc -z "$DB_HOST_TARGET" "$DB_PORT_TARGET"; do
        COUNT=$((COUNT+1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "Timeout menunggu koneksi database!"
            break
        fi
        sleep 1
    done
    echo "Koneksi database terhubung!"
fi

# Jalankan migrasi dan seeding jika diatur
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    echo "Menjalankan database migration..."
    php artisan migrate --force || true
    
    # Jalankan seeder jika pertama kali atau diminta
    if [ "${AUTO_SEED:-false}" = "true" ]; then
        echo "Menjalankan database seeder..."
        php artisan db:seed --force || true
    fi
fi

# Buat symbolic link storage jika belum ada
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --quiet || true
fi

# Optimasi konfigurasi untuk runtime
if [ "$APP_ENV" = "production" ]; then
    echo "Caching konfigurasi untuk mode production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "Memulai layanan web..."
exec "$@"
