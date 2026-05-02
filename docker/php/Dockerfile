FROM php:8.4-fpm

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        zlib1g-dev \
        libpq-dev \
        curl \
        ca-certificates \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath intl sockets xml

# Install redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install composer inline (avoid image dependency)
RUN curl -s https://getcomposer.org/installer 2>/dev/null | php -- --install-dir=/usr/local/bin --filename=composer || echo "Composer install skipped (offline mode)"

WORKDIR /var/www/html

# Copy composer files first for caching
COPY central-app/composer.json central-app/composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts

# Copy the rest of the application
COPY central-app/. .

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 9000

CMD ["php-fpm"]
