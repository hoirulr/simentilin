# ===================================================
# SI-MENTILIN Production Application Image
# PHP 8.3 FPM + Nginx + Supervisor (Multi-Arch: AMD64 & ARM64)
# ===================================================
FROM php:8.3-fpm-alpine

# Install system dependencies, Nginx, Supervisor, dan netcat
RUN apk add --no-cache \
    nginx \
    supervisor \
    netcat-openbsd \
    curl \
    git \
    unzip

# Install ekstensi PHP menggunakan official extension installer (cepat, menggunakan pre-built binary Alpine)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    gd \
    intl \
    zip \
    opcache \
    exif \
    pcntl

# Install Composer resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cache composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --optimize-autoloader

# Salin seluruh kode aplikasi (termasuk public/build yang telah di-compile)
COPY . .

# Copy konfigurasi Docker
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Fix line endings dan set permissions
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate autoload teroptimasi
RUN composer dump-autoload --optimize

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
