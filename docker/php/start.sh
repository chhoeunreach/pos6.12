#!/bin/sh
set -e

php artisan config:cache
php-fpm -D
nginx -g 'daemon off;'
