#!/bin/sh
set -e

# Default port to 10000 (Render default) or 80 if not defined
export PORT=${PORT:-10000}

# Configure Nginx with dynamic port
envsubst '${PORT}' < /var/www/html/docker/nginx.conf > /etc/nginx/conf.d/default.conf

# Ensure SQLite database file exists if DB_CONNECTION is sqlite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f /var/www/html/database/database.sqlite ]; then
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
fi

# Ensure directory permissions
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run Laravel optimizations
php /var/www/html/artisan storage:link --force || true

if [ "$AUTO_SEED" = "fresh" ]; then
    echo "Fresh migrating and seeding database..."
    php /var/www/html/artisan migrate:fresh --seed --force || true
elif [ "$AUTO_MIGRATE" = "true" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force || true
fi

if [ "$AUTO_SEED" = "true" ]; then
    echo "Running database seeders..."
    php /var/www/html/artisan db:seed --force || true
fi

# Optimize configuration and caches
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

# Ensure supervisor log directories exist
mkdir -p /var/log/supervisor /var/run

echo "Starting all services via Supervisor (Nginx, PHP-FPM, Python AI, Queue Worker)..."
exec /usr/bin/supervisord -c /var/www/html/docker/supervisord.conf
