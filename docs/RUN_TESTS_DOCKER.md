# Exécuter les Tests dans Docker

## 🐳 Exécution des Tests Fonctionnels

Puisque votre projet s'exécute dans Docker, vous devez exécuter les tests **dans le conteneur web**.

### Méthode 1 : Via docker-compose exec (Recommandé)

```bash
# Exécuter tous les tests fonctionnels User
docker-compose exec web vendor/bin/phpunit tests/functional/User/UserApiTest.php

# Avec affichage détaillé
docker-compose exec web vendor/bin/phpunit tests/functional/User/UserApiTest.php --testdox

# Un test spécifique
docker-compose exec web vendor/bin/phpunit tests/functional/User/UserApiTest.php --filter test_update_user_with_authentication
```

### Méthode 2 : Se connecter au conteneur

```bash
# Se connecter au conteneur
docker-compose exec web bash

# Puis dans le conteneur
cd /var/www/html
vendor/bin/phpunit tests/functional/User/UserApiTest.php
```

### Méthode 3 : Script shell (à créer)

Créez un script `scripts/run-tests.sh` :

```bash
#!/bin/bash
docker-compose exec web vendor/bin/phpunit "$@"
```

Puis utilisez :
```bash
chmod +x scripts/run-tests.sh
./scripts/run-tests.sh tests/functional/User/UserApiTest.php
```

## ⚙️ Configuration Requise

### Variables d'environnement dans Docker

Assurez-vous que les variables suivantes sont définies dans `docker-compose.yml` :

```yaml
web:
  environment:
    DB_HOST: db
    DB_NAME: ${POSTGRES_DB}
    DB_USER: ${POSTGRES_USER}
    DB_PASSWORD: ${POSTGRES_PASSWORD}
    JWT_SECRET_KEY: ${JWT_SECRET_KEY:-your-secret-key-change-in-production}
```

### Vérifier la connexion à la base

```bash
docker-compose exec web php -r "require 'config/database.php'; echo 'DB connected';"
```

## 🔧 Dépannage

### Erreur : "Database config not found"

**Cause** : Le chemin vers `config/database.php` est incorrect dans les tests.

**Solution** : Les tests utilisent `__DIR__ . '/../../../config/database.php'` qui devrait fonctionner depuis `tests/functional/User/`.

### Erreur : "Connection refused"

**Cause** : La base de données n'est pas accessible depuis le conteneur web.

**Solution** : Vérifiez que :
1. Le conteneur `db` est démarré : `docker-compose ps`
2. Les variables d'environnement sont correctes
3. Le réseau Docker permet la communication entre `web` et `db`

### Erreur : "JWT_SECRET_KEY not set"

**Cause** : La variable d'environnement JWT n'est pas définie.

**Solution** : Ajoutez `JWT_SECRET_KEY` dans `docker-compose.yml` ou créez un fichier `.env`.

## 📋 Commandes Utiles

### Voir les logs du conteneur web

```bash
docker-compose logs web
```

### Redémarrer les conteneurs

```bash
docker-compose restart web
```

### Vérifier les variables d'environnement

```bash
docker-compose exec web env | grep -E "DB_|JWT_"
```

### Exécuter un test spécifique avec verbose

```bash
docker-compose exec web vendor/bin/phpunit tests/functional/User/UserApiTest.php --filter test_update_user --verbose
```

## 🎯 Workflow Recommandé

1. **Démarrer les conteneurs**
   ```bash
   docker-compose up -d
   ```

2. **Vérifier que tout fonctionne**
   ```bash
   curl http://localhost/health
   ```

3. **Exécuter les tests**
   ```bash
   docker-compose exec web vendor/bin/phpunit tests/functional/User/UserApiTest.php
   ```

4. **Voir les résultats**
   - Les tests affichent les résultats directement
   - Vérifiez le code de retour : `echo $?` (0 = succès)

