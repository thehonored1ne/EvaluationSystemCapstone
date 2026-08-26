#!/bin/sh
set -e

# Default port to 10000 (Render default) or 80 if not defined
export PORT=${PORT:-10000}

# Configure Nginx with dynamic port
envsubst '${PORT}' < /var/www/html/docker/nginx.conf > /etc/nginx/conf.d/default.conf

# Ensure directory permissions
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run Laravel optimizations
php /var/www/html/artisan storage:link --force || true

if [ "$AUTO_MIGRATE" = "true" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force || true
fi

# Optimize configuration and caches
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "Starting all services via Supervisor (Nginx, PHP-FPM, Python AI, Queue Worker)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
