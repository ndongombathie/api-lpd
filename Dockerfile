# 🔹 Base PHP FPM
FROM php:8.3-fpm

# -----------------------------
# Installer dépendances système
# -----------------------------
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------
# Installer extensions PHP nécessaires
# -----------------------------
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip

# -----------------------------
# Installer Composer
# -----------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# -----------------------------
# Définir le répertoire de travail
# -----------------------------
WORKDIR /var/www

# -----------------------------
# Copier les fichiers du projet
# -----------------------------
COPY . .

# -----------------------------
# Assurer les permissions pour storage & cache
# -----------------------------
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# -----------------------------
# Copier script d'attente MySQL
# -----------------------------
COPY script.sh /usr/local/bin/script.sh
RUN chmod +x /usr/local/bin/script.sh

# -----------------------------
# Installer les dépendances PHP
# -----------------------------
RUN composer install --no-dev --optimize-autoloader

# -----------------------------
# Exposer le port PHP-FPM
# -----------------------------
EXPOSE 9000

# -----------------------------
# Entrypoint pour attendre MySQL puis démarrer Laravel
# -----------------------------
ENTRYPOINT ["/usr/local/bin/script.sh"]
