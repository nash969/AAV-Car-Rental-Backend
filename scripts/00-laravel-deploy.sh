#!/usr/bin/env bash

echo "Running Laravel deployment tasks..."

cd /var/www/html

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan storage:link || true

echo "Laravel deployment tasks completed."