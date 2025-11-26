# Scripts d'Utilitaires

## Migrations de la Table Users

### Script: `migrate-users.sh`

Exécute la migration SQL initiale pour créer la table `users` depuis Docker Compose.

#### Utilisation:

```bash
# Méthode 1: Exécuter directement le script
./scripts/migrate-users.sh

# Méthode 2: Avec docker-compose (recommandé)
docker-compose up -d  # Démarrer les conteneurs si pas déjà démarrés
./scripts/migrate-users.sh
```

---

#### Configuration:

Le script utilise les variables d'environnement suivantes (avec valeurs par défaut) :
- `CONTAINER_NAME` : `parking_app_db` (nom du conteneur PostgreSQL)
- `POSTGRES_USER` : `postgres` (utilisateur PostgreSQL)
- `POSTGRES_DB` : `parking_db` (nom de la base de données)

Vous pouvez les définir dans un fichier `.env` ou directement dans la ligne de commande :

```bash
POSTGRES_USER=myuser POSTGRES_DB=mydb ./scripts/migrate-users.sh
```

---

## Méthodes Alternatives

### Méthode 1: Via docker exec (ligne de commande)

```bash
docker exec -i parking_app_db psql -U postgres -d parking_db < src/docker/postgres/migrations/001_create_users_table.sql
```

### Méthode 2: Via docker-compose exec

```bash
docker-compose exec -T db psql -U postgres -d parking_db < src/docker/postgres/migrations/001_create_users_table.sql
```

### Méthode 3: Se connecter au conteneur et exécuter

```bash
# Se connecter au conteneur
docker exec -it parking_app_db psql -U postgres -d parking_db

# Puis dans psql, exécuter:
\i /path/to/migration.sql
# ou copier-coller le contenu du fichier
```

---

## Vérification

Après l'exécution de la migration, vérifiez que la table a été créée :

```bash
docker exec parking_app_db psql -U postgres -d parking_db -c "\d users"
```

Pour voir toutes les tables :

```bash
docker exec parking_app_db psql -U postgres -d parking_db -c "\dt"
```

