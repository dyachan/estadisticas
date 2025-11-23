# ---- Base PHP Image ----
FROM php:8.2-fpm

# ---- System Dependencies ----
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

# ---- Install Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- App Code ----
WORKDIR /var/www/html
COPY . .

# ---- Install PHP Dependencies ----
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# ---- Install Node & Build Assets ----
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install \
    && npm run build

# ---- Laravel Permissions ----
RUN chown -R www-data:www-data storage bootstrap/cache

# ---- Expose Port ----
EXPOSE 8000

# ---- Start Command ----
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000