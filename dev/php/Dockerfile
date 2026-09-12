FROM php:8.5-fpm

# Install Linux packages needed by PHP/Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Laravel will be mounted here by Docker Compose
WORKDIR /var/www/html

CMD ["php-fpm"]