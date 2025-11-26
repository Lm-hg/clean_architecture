# Tests Fonctionnels - Module User

## 📋 Vue d'ensemble

Les tests fonctionnels du module User couvrent toutes les fonctionnalités CRUD ainsi que l'authentification JWT.

## 🧪 Structure des Tests

### Fichier: `tests/functional/User/UserApiTest.php`

## ✅ Tests d'Authentification (Routes Publiques)

### 1. `test_register_user_successfully()`
- **Route**: `POST /api/auth/register`
- **Test**: Création d'un compte utilisateur
- **Vérifie**:
  - Code HTTP 200
  - Structure de la réponse
  - Données en base de données
  - Hash du mot de passe

### 2. `test_login_successfully()`
- **Route**: `POST /api/auth/login`
- **Test**: Authentification avec email/password
- **Vérifie**:
  - Code HTTP 200
  - Présence du token JWT
  - Données utilisateur retournées

### 3. `test_login_with_wrong_password()`
- **Route**: `POST /api/auth/login`
- **Test**: Tentative de connexion avec mauvais mot de passe
- **Vérifie**: Code HTTP 401

### 4. `test_login_with_nonexistent_email()`
- **Route**: `POST /api/auth/login`
- **Test**: Tentative de connexion avec email inexistant
- **Vérifie**: Code HTTP 401

## 🔐 Tests CRUD avec Authentification

### 5. `test_create_user_with_authentication()`
- **Route**: `POST /api/users`
- **Test**: Création d'utilisateur avec token JWT valide
- **Vérifie**: Code HTTP 200

### 6. `test_create_user_without_authentication()`
- **Route**: `POST /api/users`
- **Test**: Tentative de création sans token
- **Vérifie**: Code HTTP 401 (Unauthorized)

### 7. `test_list_all_users_with_authentication()`
- **Route**: `GET /api/users`
- **Test**: Liste tous les utilisateurs avec authentification
- **Vérifie**:
  - Code HTTP 200
  - Nombre d'utilisateurs
  - Structure de la réponse

### 8. `test_get_user_by_id_with_authentication()`
- **Route**: `GET /api/users/{id}`
- **Test**: Récupération d'un utilisateur par ID
- **Vérifie**: Code HTTP 200 et données correctes

### 9. `test_get_user_not_found_with_authentication()`
- **Route**: `GET /api/users/{id}`
- **Test**: Récupération d'un utilisateur inexistant
- **Vérifie**: Code HTTP 404

### 10. `test_update_user_with_authentication()`
- **Route**: `PUT /api/users/{id}`
- **Test**: Mise à jour d'un utilisateur
- **Vérifie**:
  - Code HTTP 200
  - Données mises à jour en base
  - Hash du nouveau mot de passe

### 11. `test_update_user_with_invalid_data()`
- **Route**: `PUT /api/users/{id}`
- **Test**: Tentative de mise à jour avec données invalides
- **Vérifie**: Code HTTP 400

### 12. `test_delete_user_with_authentication()`
- **Route**: `DELETE /api/users/{id}`
- **Test**: Suppression d'un utilisateur
- **Vérifie**:
  - Code HTTP 200
  - Utilisateur supprimé de la base

### 13. `test_delete_nonexistent_user()`
- **Route**: `DELETE /api/users/{id}`
- **Test**: Suppression d'un utilisateur inexistant
- **Vérifie**: Code HTTP 404 ou 500

### 14. `test_list_users_without_authentication()`
- **Route**: `GET /api/users`
- **Test**: Tentative d'accès sans authentification
- **Vérifie**: Code HTTP 401

### 15. `test_request_with_invalid_token()`
- **Route**: `GET /api/users`
- **Test**: Requête avec token invalide
- **Vérifie**: Code HTTP 401

## 🚀 Exécution des Tests

### Exécuter tous les tests fonctionnels User

```bash
vendor/bin/phpunit tests/functional/User/UserApiTest.php
```

### Exécuter un test spécifique

```bash
vendor/bin/phpunit tests/functional/User/UserApiTest.php --filter test_login_successfully
```

### Exécuter avec affichage détaillé

```bash
vendor/bin/phpunit tests/functional/User/UserApiTest.php --testdox
```

### Exécuter tous les tests fonctionnels

```bash
vendor/bin/phpunit tests/functional/
```

## 📊 Couverture des Tests

### Routes Testées

✅ `POST /api/auth/register` - Inscription  
✅ `POST /api/auth/login` - Connexion  
✅ `POST /api/users` - Création (avec auth)  
✅ `GET /api/users` - Liste (avec auth)  
✅ `GET /api/users/{id}` - Détails (avec auth)  
✅ `PUT /api/users/{id}` - Mise à jour (avec auth)  
✅ `DELETE /api/users/{id}` - Suppression (avec auth)  

### Cas d'Erreur Testés

✅ Authentification manquante  
✅ Token invalide  
✅ Utilisateur inexistant  
✅ Données invalides  
✅ Validation des champs  

## 🔧 Configuration des Tests

### Prérequis

1. Base de données PostgreSQL accessible
2. Table `users` créée via migration
3. Variables d'environnement configurées (JWT_SECRET_KEY)

### Setup automatique

Les tests nettoient automatiquement la table `users` avant et après chaque test pour garantir l'isolation.

## 📝 Helpers Disponibles

### `authenticate()`
Authentifie un utilisateur de test et stocke le token dans `$this->authToken`.

### `makeRequest(method, path, data, headers)`
Simule une requête HTTP avec support des headers (notamment Authorization).

### `createUserInDb(email, password, ...)`
Crée un utilisateur directement en base de données.

### `getUserFromDb(id)`
Récupère un utilisateur depuis la base de données.

## ⚠️ Notes Importantes

1. **Isolation**: Chaque test est isolé (nettoyage de la DB)
2. **Authentification**: Les routes protégées nécessitent un token valide
3. **Mocking**: Le helper `getRequestBody()` dans `index.php` permet de simuler les requêtes JSON
4. **Headers**: Le header `Authorization: Bearer <token>` est automatiquement géré

## 🎯 Prochaines Étapes

Pour finaliser le module User, vous pouvez ajouter :

- [ ] Tests de performance (charge)
- [ ] Tests d'intégration avec d'autres modules
- [ ] Tests de sécurité (injection SQL, XSS)
- [ ] Tests de validation des rôles (admin vs user)
- [ ] Tests de pagination (si implémentée)

