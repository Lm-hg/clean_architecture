# 📋 Explication Complète : Structure des Tests et Problèmes CI

## 📁 Structure des Tests

```
tests/
├── unit/                    # Tests unitaires (mocks, pas de DB)
│   ├── ExampleTest.php
│   └── User/                # Tests pour le module User
│       ├── CreateUserUseCaseTest.php
│       ├── DeleteUserUseCaseTest.php
│       ├── GetUserUseCaseTest.php
│       ├── ListUsersUseCaseTest.php
│       └── UpdateUserUseCaseTest.php
│
├── integration/             # Tests d'intégration (avec vraie DB)
│   └── User/
│       └── UserRepositoryIntegrationTest.php
│
├── functional/              # Tests fonctionnels E2E (simulation HTTP)
│   ├── SmokeTest.php
│   └── User/
│       ├── UserApiTest.php              # Teste l'API complète
│       └── UserApiFunctionalTestHelper.php  # Helper pour les tests
│
└── fixtures/                # Données de test
```

### Pourquoi un dossier `User/` dans chaque type ?

C'est une **organisation par module** :
- ✅ Facile de trouver tous les tests du module User
- ✅ Permet d'avoir des helpers spécifiques par module
- ✅ Structure claire et maintenable
- ✅ Prêt pour d'autres modules (Booking, Parking, etc.)

## ✅ Vérification PSR-4 et Namespaces

### Configuration actuelle : ✅ CORRECTE

```json
{
  "autoload": {
    "psr-4": {
      "App\\Domain\\": "src/Domain/",
      "App\\Application\\": "src/Application/",
      "App\\Infrastructure\\": "src/Infrastructure/",
      "App\\Presenter\\": "src/Presenter/"  // ✅ Majuscule P
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  }
}
```

### Mapping PSR-4 :

| Namespace | Répertoire | Status |
|-----------|-----------|--------|
| `App\Application\UseCases\` | `src/Application/UseCases/` | ✅ Correct |
| `App\Presenter\` | `src/Presenter/` | ✅ Correct (renommé) |
| `Tests\` | `tests/` | ✅ Correct |

## ❌ Problèmes CI Identifiés

### Problème 1 : Classes non trouvées dans le CI

**Erreur** : `Class "App\Presenter\Http\Controllers\Api\AuthController" not found`

**Cause** : 
- Répertoire était `src/presenter/` (minuscule) mais namespace `App\Presenter\` (majuscule)
- Sur macOS (insensible à la casse) ça marche
- Sur Linux CI (sensible à la casse) ça échoue ❌

**Solution appliquée** :
- ✅ Répertoire renommé : `src/presenter/` → `src/Presenter/`
- ✅ `composer.json` mis à jour : `"App\\Presenter\\": "src/Presenter/"`
- ✅ Git a enregistré le renommage

### Problème 2 : Service "db" non en cours d'exécution

**Erreur** : `service "db" is not running` dans le job `docker-build`

**Cause** :
- Le job `docker-build` essaie d'exécuter `docker compose exec -T db` avant que les conteneurs ne soient prêts
- Ou les conteneurs n'ont pas démarré correctement

**Solution appliquée** :
- ✅ Vérification que les conteneurs sont démarrés avant d'exécuter des commandes
- ✅ Meilleure gestion des erreurs avec logs détaillés

## 🔧 Services dans le CI

### Job `php-lint-and-test` :
- ✅ Utilise les **services GitHub Actions** (postgres, mongodb)
- ✅ Pas de Docker Compose nécessaire
- ✅ PostgreSQL accessible via `localhost:5432`

### Job `docker-build` :
- ✅ Utilise **Docker Compose** pour construire et tester les images
- ⚠️ Doit attendre que les conteneurs démarrent
- ⚠️ Doit vérifier que le service "db" est en cours d'exécution

## ✅ Tests Locaux vs CI

### Local (macOS) :
- ✅ Tous les tests passent (15/15)
- ✅ Système de fichiers insensible à la casse
- ✅ Classes correctement chargées

### CI (Linux) :
- ⚠️ Échoue à cause de la casse des répertoires
- ✅ Maintenant corrigé avec renommage et vérifications améliorées

