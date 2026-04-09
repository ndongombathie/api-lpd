#!/bin/bash

echo "🚀 Déploiement API..."

# Pull du code
git pull

# Installer dépendances
#composer install --no-dev --optimize-autoloader

# Laravel cache
#php artisan config:clear
#php artisan cache:clear
#php artisan config:cache
#php artisan route:cache

# Migrations
#php artisan migrate --force

# Redémarrer uniquement les services API
#cd /home/lpdmanager/lpdManagerProject
#docker compose build app reverb queue
#docker compose up -d app reverb queue

echo "✅ API mise à jour"
