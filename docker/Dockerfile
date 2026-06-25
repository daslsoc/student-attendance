FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install needed extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql zip

# Install Xdebug (coverage driver used by `make coverage`).
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Xdebug runs in coverage mode only. Keep start_with_request off so normal
# requests / test runs aren't slowed down; the Makefile flips XDEBUG_MODE per
# command (off for tests, coverage for the coverage target).
RUN echo "xdebug.mode=off\n\
xdebug.start_with_request=no\n\
" > /usr/local/etc/php/conf.d/zz-xdebug.ini

# Custom FPM pool config (process management + log routing).
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-pool.conf

# Composer (matches the container's PHP 8.2 runtime).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . .

# Set appropriate permissions for the web user on writable paths.
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
