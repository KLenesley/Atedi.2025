#!/bin/sh
set -e

echo "Attente MariaDB..."
timeout 30s sh -c 'until nc -z db 3306 2>/dev/null; do echo "."; sleep 1; done' || (echo "DB KO" && exit 1)
echo "DB OK !"

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Exécute les fixtures UNIQUEMENT en environnement de développement
if [ "$APP_ENV" = "dev" ]; then
    echo "Chargement des fixtures de développement..."
    php bin/console doctrine:fixtures:load --no-interaction
fi

php bin/console cache:warmup --no-debug

echo "Démarrage PHP-FPM en foreground..."
exec php-fpm -F
