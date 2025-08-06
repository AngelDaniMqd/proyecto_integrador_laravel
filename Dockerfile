# Multi-stage build para optimizar el tamaño final
FROM node:18-alpine AS node-builder

WORKDIR /app
COPY package*.json ./
RUN npm ci

# Copiar archivos para build
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
COPY . .

# Build de assets
ENV NODE_ENV=production
RUN npm run build

FROM php:8.2-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar Apache
RUN a2enmod rewrite headers

# Apache config mejorado
RUN echo '<VirtualHost *:80>' > /etc/apache2/sites-available/000-default.conf && \
    echo '    ServerName localhost' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    DocumentRoot /var/www/html/public' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html/public>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '</VirtualHost>' >> /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copiar composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Copiar aplicación
COPY . .

# Copiar assets compilados
COPY --from=node-builder /app/public/build ./public/build

# CREAR .env a partir de variables de entorno (CORREGIDO)
RUN echo 'APP_NAME="Sustainity PI"' > .env && \
    echo 'APP_ENV=${APP_ENV:-production}' >> .env && \
    echo 'APP_KEY=${APP_KEY}' >> .env && \
    echo 'APP_DEBUG=${APP_DEBUG:-false}' >> .env && \
    echo 'APP_URL=${APP_URL}' >> .env && \
    echo '' >> .env && \
    echo 'DB_CONNECTION=${DB_CONNECTION:-mysql}' >> .env && \
    echo 'DB_HOST=${DB_HOST}' >> .env && \
    echo 'DB_PORT=${DB_PORT:-3306}' >> .env && \
    echo 'DB_DATABASE=${DB_DATABASE}' >> .env && \
    echo 'DB_USERNAME=${DB_USERNAME}' >> .env && \
    echo 'DB_PASSWORD=${DB_PASSWORD}' >> .env && \
    echo '' >> .env && \
    echo 'SESSION_DRIVER=${SESSION_DRIVER:-file}' >> .env && \
    echo 'SESSION_LIFETIME=${SESSION_LIFETIME:-120}' >> .env && \
    echo '' >> .env && \
    echo 'LOG_CHANNEL=${LOG_CHANNEL:-single}' >> .env && \
    echo 'LOG_LEVEL=${LOG_LEVEL:-info}' >> .env && \
    echo '' >> .env && \
    echo 'CACHE_STORE=${CACHE_STORE:-file}' >> .env && \
    echo 'FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}' >> .env && \
    echo '' >> .env && \
    echo 'STRIPE_KEY=${STRIPE_KEY}' >> .env && \
    echo 'STRIPE_SECRET=${STRIPE_SECRET}' >> .env

RUN composer dump-autoload --optimize

# Crear directorios y permisos
RUN mkdir -p storage/{logs,framework/{cache,sessions,views},app/public} && \
    mkdir -p bootstrap/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 storage && \
    chmod -R 777 bootstrap/cache

# Script de inicio
RUN echo '#!/bin/bash' > /usr/local/bin/start.sh && \
    echo 'set -e' >> /usr/local/bin/start.sh && \
    echo 'echo "=== SUSTAINITY PI STARTING ==="' >> /usr/local/bin/start.sh && \
    echo 'echo "Environment: $APP_ENV"' >> /usr/local/bin/start.sh && \
    echo 'echo "Debug: $APP_DEBUG"' >> /usr/local/bin/start.sh && \
    echo 'echo "Checking .env file..."' >> /usr/local/bin/start.sh && \
    echo 'ls -la .env || echo ".env not found"' >> /usr/local/bin/start.sh && \
    echo 'php artisan storage:link --force 2>/dev/null || echo "Storage link OK"' >> /usr/local/bin/start.sh && \
    echo 'php artisan config:cache 2>/dev/null || echo "Config cache failed"' >> /usr/local/bin/start.sh && \
    echo 'php artisan route:cache 2>/dev/null || echo "Route cache failed"' >> /usr/local/bin/start.sh && \
    echo 'chown -R www-data:www-data storage bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'chmod -R 777 storage bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'echo "=== APACHE STARTING ==="' >> /usr/local/bin/start.sh && \
    echo 'apache2-foreground' >> /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]
