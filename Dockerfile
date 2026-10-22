# syntax=docker/dockerfile:1

# ---------------------------------------------------------------
# 1. Dépendances PHP (Composer)
#    Faker est dans « require » : DatabaseSeeder utilise des factories.
# ---------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---------------------------------------------------------------
# 2. Assets Vite (Tailwind + JS)
#    resources/js/app.js importe Livewire depuis vendor/ : on le copie depuis l'étape précédente.
# ---------------------------------------------------------------
FROM node:20 AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor/livewire ./vendor/livewire
RUN npm run build

# ---------------------------------------------------------------
# 3. Image finale : PHP 8.3 + Apache
# ---------------------------------------------------------------
FROM php:8.3-apache

# pdo_sqlite est compilé dans l'image officielle : on vérifie sa présence au lieu de le réinstaller.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev sqlite3 unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip intl bcmath \
    && php -m | grep -q pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache : mod_rewrite, DocumentRoot sur public/, écoute sur ${PORT} (fourni par Render, 8080 par défaut)
ENV PORT=8080 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# Valeurs par défaut de production (surchargées par Render ou docker-compose)
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=fr \
    APP_FALLBACK_LOCALE=en \
    APP_FAKER_LOCALE=fr_FR \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwX storage bootstrap/cache database

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
