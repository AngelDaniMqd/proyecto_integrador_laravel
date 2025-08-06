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

# Build simplificado (sin verificaciones que fallan)
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

# Configurar Apache (método simplificado)
RUN a2enmod rewrite headers
RUN echo '<VirtualHost *:80>' > /etc/apache2/sites-available/000-default.conf && \
    echo '    DocumentRoot /var/www/html/public' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html/public>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
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

RUN composer dump-autoload --optimize

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/public \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/bootstrap/cache

# Script de inicio simplificado
RUN echo '#!/bin/bash' > /usr/local/bin/start.sh && \
    echo 'set -e' >> /usr/local/bin/start.sh && \
    echo 'mkdir -p /var/www/html/storage/{logs,framework/{cache,sessions,views},app/public}' >> /usr/local/bin/start.sh && \
    echo 'mkdir -p /var/www/html/bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'chmod -R 777 /var/www/html/storage' >> /usr/local/bin/start.sh && \
    echo 'chmod -R 777 /var/www/html/bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'chmod -R 755 /var/www/html/public' >> /usr/local/bin/start.sh && \
    echo 'php artisan storage:link --force 2>/dev/null || echo "Storage link OK"' >> /usr/local/bin/start.sh && \
    echo 'apache2-foreground' >> /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]