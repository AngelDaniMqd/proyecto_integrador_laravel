# Multi-stage build para optimizar el tamaño final
# 1) Build de assets con Node
FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm ci
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# 2) Imagen final con PHP 8.2 + Apache
FROM php:8.2-apache

# Instala extensiones PHP y Node.js
RUN apt-get update && apt-get install -y \
    libzip-dev zlib1g-dev libpng-dev libonig-dev zip unzip git curl \
    nodejs npm \
  && docker-php-ext-install pdo_mysql mbstring bcmath zip exif pcntl gd \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

# Configurar Apache para Laravel
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options Indexes FollowSymLinks\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar código
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Instalar dependencias JS y compilar assets
RUN npm ci && npm run build

# Verificar que manifest.json existe
RUN ls -la public/build/ || echo "No build directory found"

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html \
  && chmod -R 755 /var/www/html \
  && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["bash", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground"]
