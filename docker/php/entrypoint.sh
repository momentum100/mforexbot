#!/bin/sh
set -e

if [ -f /var/www/html/composer.json ] && [ ! -f /var/www/html/vendor/autoload.php ]; then
    cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction
fi

exec "$@"
