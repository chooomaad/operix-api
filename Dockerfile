# ── Operix HSSE API — image de production (Render / Docker) ──────────────────
# Laravel 13 / PHP 8.3 servi par Apache (mod_php), docroot = /public.
FROM php:8.3-apache

# Dépendances système + extensions PHP (PostgreSQL, GD pour les images, zip, bcmath)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pgsql zip bcmath gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Apache : servir le dossier /public de Laravel (docroot fixé au build)
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public/>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel
# NB : le port d'écoute ($PORT fourni par Render) est appliqué au démarrage par l'entrypoint.

WORKDIR /var/www/html

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Installer les dépendances d'abord (cache Docker)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Code applicatif
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrée : migrations + seed idempotent + caches, puis Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE ${PORT}
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
