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

# Build con variables correctas para Railway
ENV NODE_ENV=production
ENV VITE_APP_URL=placeholder
RUN npm run build && \
    echo "=== ASSETS BUILD COMPLETED ===" && \
    ls -la public/build/

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
COPY <<EOF /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/public
    
    <Directory /var/www/html>
        AllowOverride None
        Require all granted
    </Directory>
    
    <Directory /var/www/html/public>
        AllowOverride All
        Options Indexes FollowSymLinks
        Require all granted
        
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

WORKDIR /var/www/html

# Copiar composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Copiar aplicación
COPY . .

# Copiar assets Y crear enlaces
COPY --from=node-builder /app/public/build ./public/build

# AGREGAR: Verificar que los assets estén ahí
RUN echo "=== VERIFICANDO ASSETS ===" && \
    ls -la public/build/ && \
    ls -la public/build/assets/ || echo "No assets directory"

RUN composer dump-autoload --optimize

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/public \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/bootstrap/cache

# Script de inicio mejorado
COPY <<EOF /usr/local/bin/start.sh
#!/bin/bash
set -e

echo "=== INICIANDO SUSTAINITY PI ==="

# Crear directorios
mkdir -p /var/www/html/storage/{logs,framework/{cache,sessions,views},app/public}
mkdir -p /var/www/html/bootstrap/cache

# Permisos
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage
chmod -R 777 /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/public

# Storage link
php artisan storage:link --force 2>/dev/null || echo "Warning: storage link failed"

echo "=== DEBUG ASSETS ==="
echo "Build directory:"
ls -la public/build/ || echo "❌ No build directory"
echo "Assets:"
ls -la public/build/assets/ || echo "❌ No assets"
echo "Manifest:"
cat public/build/manifest.json || echo "❌ No manifest"

echo "=== INICIANDO APACHE ==="
apache2-foreground
EOF

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]