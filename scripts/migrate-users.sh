#!/bin/bash

# Script pour exécuter la migration de la table users depuis Docker
# Usage: ./scripts/migrate-users.sh

set -e

# Configuration
CONTAINER_NAME="${CONTAINER_NAME:-parking_app_db}"
DB_USER="${POSTGRES_USER:-parking_user}"
DB_NAME="${POSTGRES_DB:-parking_db}"
MIGRATION_FILE="src/docker/postgres/migrations/001_create_users_table.sql"

echo "🚀 Exécution de la migration pour la table users..."
echo "📁 Fichier: $MIGRATION_FILE"
echo "🐳 Conteneur: $CONTAINER_NAME"
echo ""

# Vérifier que le conteneur est démarré
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "❌ Le conteneur '$CONTAINER_NAME' n'est pas démarré."
    echo "💡 Démarrez-le avec: docker-compose up -d"
    exit 1
fi

# Vérifier que le fichier de migration existe
if [ ! -f "$MIGRATION_FILE" ]; then
    echo "❌ Le fichier de migration n'existe pas: $MIGRATION_FILE"
    exit 1
fi

# Exécuter la migration
echo "📝 Exécution de la migration..."
docker exec -i "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME" < "$MIGRATION_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Migration exécutée avec succès !"
    echo ""
    echo "🔍 Vérification de la table users:"
    docker exec "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME" -c "\d users"
else
    echo ""
    echo "❌ Erreur lors de l'exécution de la migration."
    exit 1
fi

