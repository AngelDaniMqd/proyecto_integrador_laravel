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

FROM php:8.0-apache

# 1) Instalar dependencias sistema, PHP y Node.js (para Vite)
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev zlib1g-dev libpng-dev libonig-dev \
    # Node.js para Vite
    nodejs npm \
  && docker-php-ext-install pdo_mysql mbstring bcmath zip exif pcntl gd \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

# 2) Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 3) Instalar dependencias PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 4) Copiar el resto de la aplicación y compilar assets
COPY . .
RUN npm ci \
  && npm run build

# 5) Ajustar permisos
RUN chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R 775 storage bootstrap/cache

# 6) Exponer el puerto HTTP
EXPOSE 80

# 7) Migraciones, caches y arranque de Apache
CMD ["bash","-lc","php artisan migrate --force \
  && php artisan config:cache \
  && php artisan route:cache \
  && php artisan view:cache \
  && apache2-foreground"]
