FROM php:8.3-fpm

# Run PHP-FPM's www-data as the host user (uid/gid 1000 by default). The source
# tree is bind-mounted from the host, so the build-time chown below is hidden at
# runtime; matching uids lets Laravel write storage/ and bootstrap/cache, and
# files it creates stay owned by the host user.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data

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
