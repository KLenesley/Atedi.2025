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
    netcat \
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Installe les dépendances (sans les dev pour la prod)
RUN composer install --optimize-autoloader --no-dev

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/var

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
