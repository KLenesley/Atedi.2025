FROM php:8.3-fpm-bookworm

# Installation des dépendances système + ICU correctement
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    netcat-traditional \
    libicu-dev \
    pkg-config \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl gd \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie du code
WORKDIR /var/www
COPY . .

# Install des dépendances PHP (prod only)
RUN composer install --optimize-autoloader --no-interaction

# Entrypoint
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions
RUN chown -R www-data:www-data /var/www/var

ENTRYPOINT ["entrypoint.sh"]
CMD []
