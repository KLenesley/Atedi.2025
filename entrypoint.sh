#!/bin/sh

set -e

# Attend que la base de données soit prête
until nc -z -v -w30 db 3306
do
  echo "En attente de la base de données..."
  sleep 1
done

# Exécute les migrations
echo "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Charge les fixtures
echo "Chargement des fixtures..."
php bin/console doctrine:fixtures:load --no-interaction

# Lance les tests unitaires
echo "Exécution des tests unitaires..."
php bin/phpunit

exec "$@"
