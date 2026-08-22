# ---- Stage 1: Build frontend assets with Node ----
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/css resources/css
COPY resources/js resources/js
RUN npm run build


# ---- Stage 2: Production PHP + Apache ----
FROM php:8.2-apache

# System dependencies for PHP extensions + Composer + general utility
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgmp-dev \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libxpm-dev \
        libxml2-dev \
        libonig-dev \
        libexif-dev \
        unzip \
        curl \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql gd gmp bcmath zip intl mbstring exif opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: enable rewrite, disable built-in /icons/ alias, set DocumentRoot to Laravel's public/
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && (a2disconf apache2-doc 2>/dev/null || true) \
    && rm -f /etc/apache2/conf-enabled/apache2-doc.conf

# Apache vhost: point DocumentRoot at Laravel public/
RUN sed -i 's|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/public|' /etc/apache2/sites-enabled/000-default.conf \
    && sed -i 's|<Directory /var/www/html/>|<Directory /var/www/html/public/>|' /etc/apache2/sites-enabled/000-default.conf

# Copy application source
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Install PHP dependencies (no dev, optimized autoloader)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy built frontend assets from Stage 1
COPY --from=build /app/public/build public/build

# Storage + cache permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Set production environment
ENV APP_ENV=production
ENV APP_DEBUG=false

# Render provides $PORT — default 8080
ENV PORT=8080

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE ${PORT}

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
