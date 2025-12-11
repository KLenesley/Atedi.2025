FROM php:8.4-fpm-trixie

# Installation des dépendances système, on ajoute nginx et supervisor
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
    nginx \
    supervisor \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl gd \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie du code de l'application
WORKDIR /var/www
COPY . .

# Install des dépendances PHP (prod only)
RUN composer install --optimize-autoloader --no-interaction --no-scripts --dev

# --- Configuration de l'environnement ---

# 1. On s'assure que PHP-FPM écoute bien sur localhost, car Nginx est dans le même conteneur.
RUN sed -i "s/listen = 9000/listen = 127.0.0.1:9000/" /usr/local/etc/php-fpm.d/www.conf

# 2. On copie nos fichiers de configuration personnalisés
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 3. On copie et on rend exécutable le script d'entrée
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh && sed -i 's/\r$//' /usr/local/bin/entrypoint.sh

# Permissions pour les dossiers de Symfony
RUN chown -R www-data:www-data /var/www

# On expose le port 80, celui de Nginx
EXPOSE 80

# On lance notre script d'entrée qui va gérer le démarrage
ENTRYPOINT ["entrypoint.sh"]
