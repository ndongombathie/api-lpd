#!/bin/bash

echo "⏳ Waiting for MySQL..."

while ! nc -z db 3306; do
  sleep 2
done

echo "✅ MySQL is ready!"

php artisan config:clear
php artisan cache:clear

php artisan reverb:start --host=0.0.0.0 --port=8080
