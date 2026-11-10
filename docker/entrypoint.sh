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

# Ensure directory permissions.
# NOTE: /var/www/html/database may contain read-only bind mounts (local
# docker compose mounts migrations/ and seeders/ that way), so never recurse
# into it blindly - chmod would fail on the read-only filesystem and `set -e`
# would abort the container start.
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

chmod 775 /var/www/html/database 2>/dev/null || true
chown www-data:www-data /var/www/html/database 2>/dev/null || true
for sqlite_file in /var/www/html/database/*.sqlite /var/www/html/database/*.sqlite-*; do
    [ -e "$sqlite_file" ] || continue
    chmod 664 "$sqlite_file" 2>/dev/null || true
    chown www-data:www-data "$sqlite_file" 2>/dev/null || true
done

# Run Laravel optimizations
php /var/www/html/artisan storage:link --force || true

if [ "$AUTO_SEED" = "fresh" ]; then
    echo "Fresh migrating and seeding database..."
    php /var/www/html/artisan migrate:fresh --seed --force || true
elif [ "$AUTO_MIGRATE" = "true" ] || [ "$AUTO_SEED" = "initial" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force || true
fi

if [ "$AUTO_SEED" = "true" ]; then
    echo "Running database seeders..."
    php /var/www/html/artisan db:seed --force || true
fi

# Seed only once: the marker file lives inside the database volume, so
# restarts and rebuilds never double-seed. Unlike AUTO_SEED=fresh, this never
# wipes existing data.
if [ "$AUTO_SEED" = "initial" ] && [ ! -f /var/www/html/database/.seeded ]; then
    echo "First run: seeding database (this takes a few minutes)..."
    if php /var/www/html/artisan db:seed --force; then
        touch /var/www/html/database/.seeded
        echo "Seeding finished."
    else
        echo "Seeding failed; it will be retried on the next start." >&2
    fi
fi

# Optimize configuration and caches
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

# Ensure supervisor log directories exist
mkdir -p /var/log/supervisor /var/run

echo "Starting all services via Supervisor (Nginx, PHP-FPM, Python AI, Queue Worker)..."
exec /usr/bin/supervisord -c /var/www/html/docker/supervisord.conf
