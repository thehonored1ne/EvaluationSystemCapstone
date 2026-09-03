FROM php:8.3-fpm-bookworm

# Prevent interactive prompts during installation
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies, Nginx, Supervisor, Node.js 20, Python 3, and build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    ca-certificates \
    gettext-base \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    python3 \
    python3-pip \
    python3-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure & install required PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        mbstring \
        zip \
        gd \
        opcache \
        xml

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# 1. Copy dependency manifests first for optimal Docker layer caching
COPY composer.json composer.lock package.json package-lock.json ./
COPY python/requirements.txt ./python/

# Install Python requirements and download VADER lexicon
RUN pip3 install --no-cache-dir --break-system-packages -r python/requirements.txt gunicorn \
    && python3 -c "import nltk; nltk.download('vader_lexicon')"

# Install PHP dependencies without scripts (scripts need app source files)
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

# Install frontend dependencies
RUN npm ci

# 2. Copy application files
COPY . .

# Complete Composer autoloader generation & optimization with full codebase
RUN composer dump-autoload --optimize --no-dev

# Build Vite assets and prune node_modules to reduce final container size
RUN npm run build && rm -rf node_modules

# Remove default Nginx site config
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

# Setup permissions for Laravel storage and cache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/docker/entrypoint.sh

# Expose Render standard port
EXPOSE 10000 80

# Run entrypoint script
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
