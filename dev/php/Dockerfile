FROM php:8.5.13-fpm

ARG UID=1000
ARG GID=1000

# Install Linux packages needed by PHP/Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Create a user matching the host user's UID/GID
RUN groupadd --gid ${GID} app \
    && useradd \
        --uid ${UID} \
        --gid ${GID} \
        --create-home \
        --shell /bin/bash \
        app

RUN if [ -f /usr/local/etc/php/php.ini-production ]; then \
        cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini; \
    fi \
    && printf '%s\n' \
        'session.upload_progress.enabled=0' \
        'zend.exception_ignore_args=1' \
        'fatal_error_backtraces=0' \
        'expose_php=Off' \
        > /usr/local/etc/php/conf.d/security.ini \
    && sed -i 's/^;*catch_workers_output = .*/catch_workers_output = no/' /usr/local/etc/php-fpm.d/www.conf

# Copy Composer from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]