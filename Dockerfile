# Multi-stage build para optimizar el tamaño final
# 1) Stage de Node: compila Vite
FROM node:18-alpine AS node-builder
WORKDIR /app

# Copia package.json, lock y config de vite
COPY package*.json vite.config.js ./

# Instala dependencias y compila
RUN npm ci
COPY resources/ resources/
RUN npm run build

# 2) Stage PHP+Apache
FROM php:8.2-apache

# Instala extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev zlib1g-dev libpng-dev libonig-dev zip unzip git curl \
  && docker-php-ext-install pdo_mysql mbstring bcmath zip exif pcntl gd \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

# Configura Apache para apuntar al public de Laravel
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options Indexes FollowSymLinks\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' \
 > /etc/apache2/sites-available/000-default.conf

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia todo el código, incluido artisan y package.json
COPY . .

# Instala dependencias PHP (artisan ya existe)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copia los assets compilados desde node-builder
COPY --from=node-builder /app/public/build public/build

# Ajusta permisos
RUN chown -R www-data:www-data /var/www/html \
  && find storage bootstrap/cache -type d -exec chmod 775 {} \; \
  && find storage bootstrap/cache -type f -exec chmod 664 {} \;

# Expóne el puerto HTTP
EXPOSE 80

# Migra, cachea y arranca Apache
CMD ["bash","-lc","php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground"]
