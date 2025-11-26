#!/bin/bash

# Script pour exécuter les tests dans Docker
# Usage: ./scripts/run-tests.sh [options] [fichier de test]

set -e

CONTAINER_NAME="${CONTAINER_NAME:-parking_app_web}"

echo "🧪 Exécution des tests dans Docker..."
echo "🐳 Conteneur: $CONTAINER_NAME"
echo ""

# Vérifier que le conteneur est démarré
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "❌ Le conteneur '$CONTAINER_NAME' n'est pas démarré."
    echo "💡 Démarrez-le avec: docker-compose up -d"
    exit 1
fi

# Si aucun argument, exécuter tous les tests
if [ $# -eq 0 ]; then
    echo "📋 Exécution de tous les tests..."
    docker-compose exec -T "$CONTAINER_NAME" vendor/bin/phpunit "$@"
else
    echo "📋 Exécution: vendor/bin/phpunit $*"
    docker-compose exec -T "$CONTAINER_NAME" vendor/bin/phpunit "$@"
fi

