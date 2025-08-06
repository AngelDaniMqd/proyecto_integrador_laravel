# Multi-stage build para optimizar el tamaño final
# 1) Stage Node: compila Vite
FROM node:18-alpine AS node-builder
WORKDIR /app

# Copia configs y dependencias
COPY package*.json vite.config.js ./
RUN npm ci

# Copia código fuente Vite y compila
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# 2) Stage PHP+Apache final
FROM php:8.2-apache

# Instala extensiones y herramientas necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev zlib1g-dev libpng-dev libonig-dev zip unzip git curl \
  && docker-php-ext-install pdo_mysql mbstring bcmath zip exif pcntl gd \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

# Configura Apache para Laravel
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia toda la aplicación
COPY . .

# Instala dependencias PHP (ahora artisan existe)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copia los assets compilados
COPY --from=node-builder /app/public/build public/build

# Ajusta permisos
RUN chown -R www-data:www-data /var/www/html \
 && find storage bootstrap/cache -type d -exec chmod 775 {} \; \
 && find storage bootstrap/cache -type f -exec chmod 664 {} \;

EXPOSE 80

# Migra, cachea y arranca Apache
CMD ["bash","-lc","php artisan migrate --force \
  && php artisan config:cache \
  && php artisan route:cache \
  && php artisan view:cache \
  && apache2-foreground"]
