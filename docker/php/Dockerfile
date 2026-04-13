FROM php:8.4-fpm-alpine

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install F3 dependencies if composer.json exists
# This runs at build time; volumes override at runtime,
# so composer install should also run on first startup or via entrypoint
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

EXPOSE 9000

CMD ["php-fpm"]
