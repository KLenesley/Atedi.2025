#!/bin/sh
set -e

echo "Attente MySQL..."
timeout 30s sh -c 'until nc -z db 3306 2>/dev/null; do echo "."; sleep 1; done' || (echo "DB KO" && exit 1)
echo "DB OK !"

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# On charge les fixtures si on est en environnement de développement
if [ "$APP_ENV" = "dev" ]; then
    echo "Chargement des fixtures de développement..."
    php bin/console doctrine:fixtures:load --no-interaction
fi

# On charge les fixtures si la variable d'environnement est à "true"
if [ "$LOAD_FIXTURES" = "true" ]; then
    echo "Chargement des fixtures (demandé par LOAD_FIXTURES=true)..."
    # On exécute la commande dans un environnement temporairement 'dev'
    # pour s'assurer que toutes les dépendances et services de dev sont bien chargés.
    APP_ENV=dev php bin/console doctrine:fixtures:load --no-interaction
fi

php bin/console cache:warmup --no-debug

echo "Démarrage de Nginx et PHP-FPM avec Supervisor..."
# On ne lance pas php-fpm directement, mais supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
