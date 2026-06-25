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

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy config for Xdebug coverage if needed:
# Alternatively, you can just add to php.ini or write your own xdebug.ini
RUN echo "zend_extension=xdebug.so\n\
xdebug.mode=coverage\n\
xdebug.start_with_request=yes\n\
" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Copy composer files, then install
#COPY composer.json composer.lock ./
#RUN curl -sS https://getcomposer.org/installer | php && \
#    php composer.phar install --no-dev --optimize-autoloader
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . .

# Set appropriate permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
