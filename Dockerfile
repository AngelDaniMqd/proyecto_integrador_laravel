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
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html/public>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteEngine On' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteCond %{REQUEST_FILENAME} !-f' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteCond %{REQUEST_FILENAME} !-d' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteRule ^(.*)$ index.php [QSA,L]' >> /etc/apache2/sites-available/000-default.conf && \
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

# Crear directorios y permisos ANTES del script
RUN mkdir -p /var/www/html/storage/{logs,framework/{cache,sessions,views},app/public} && \
    mkdir -p /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/storage && \
    chmod -R 777 /var/www/html/bootstrap/cache

# Script de inicio con debug mejorado
RUN echo '#!/bin/bash' > /usr/local/bin/start.sh && \
    echo 'set -e' >> /usr/local/bin/start.sh && \
    echo 'echo "=== INICIANDO SUSTAINITY PI ==="' >> /usr/local/bin/start.sh && \
    echo 'echo "PHP Version: $(php --version | head -1)"' >> /usr/local/bin/start.sh && \
    echo 'echo "Laravel Version: $(php artisan --version 2>/dev/null || echo \"Laravel check failed\")"' >> /usr/local/bin/start.sh && \
    echo 'echo "Checking key..."' >> /usr/local/bin/start.sh && \
    echo 'php artisan key:generate --force 2>/dev/null || echo "Key generation failed"' >> /usr/local/bin/start.sh && \
    echo 'echo "Checking database connection..."' >> /usr/local/bin/start.sh && \
    echo 'php artisan migrate:status 2>/dev/null || echo "DB connection failed"' >> /usr/local/bin/start.sh && \
    echo 'echo "Creating storage link..."' >> /usr/local/bin/start.sh && \
    echo 'php artisan storage:link --force 2>/dev/null || echo "Storage link failed"' >> /usr/local/bin/start.sh && \
    echo 'echo "Final permission check..."' >> /usr/local/bin/start.sh && \
    echo 'chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache' >> /usr/local/bin/start.sh && \
    echo 'echo "=== STARTING APACHE ==="' >> /usr/local/bin/start.sh && \
    echo 'apache2-foreground' >> /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]