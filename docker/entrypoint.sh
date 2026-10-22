#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY n'est pas défini : générez-en une avec « php artisan key:generate --show »." >&2
    exit 1
fi

DB_FILE="${DB_DATABASE:-database/database.sqlite}"

# Base SQLite : créée si absente, migrée à chaque démarrage, remplie seulement si elle est vide
if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
fi

if [ ! -s "$DB_FILE" ]; then
    php artisan migrate --force --seed
else
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

chown -R www-data:www-data storage bootstrap/cache database

exec "$@"
