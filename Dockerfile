# 🔹 Base PHP FPM
FROM php:8.3-fpm

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

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1


WORKDIR /var/www


COPY . .


RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache


COPY script.sh /usr/local/bin/script.sh
RUN chmod +x /usr/local/bin/script.sh


RUN composer install --no-dev --optimize-autoloader


EXPOSE 9000


ENTRYPOINT ["/usr/local/bin/script.sh"]

