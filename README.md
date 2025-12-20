# 🚗 Clean Architecture Parking System

Système de gestion de parking construit selon les principes de **Clean Architecture** avec PHP 8.3, **PostgreSQL** (données relationnelles), **MongoDB/JSON** (données flexibles), et Docker.

## 📋 Table des matières

- [Architecture](#-architecture)
- [Entités du domaine](#-entités-du-domaine)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Lancer le projet](#-lancer-le-projet)
- [Base de données](#-base-de-données)
- [API Endpoints](#-api-endpoints)
- [Tests](#-tests)
- [Structure du projet](#-structure-du-projet)
- [Fonctionnalités](#-fonctionnalités)

---

## 🏗️ Architecture

Ce projet suit les principes de **Clean Architecture** avec une séparation claire des responsabilités :

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTER (Http)                          │
│  Controllers/Api    Controllers/Web    Frontend (React)      │
├─────────────────────────────────────────────────────────────┤
│                   APPLICATION (UseCases)                     │
│  CreateReservation  EnterParking  CalculateRevenue  etc.     │
├─────────────────────────────────────────────────────────────┤
│                      DOMAIN (Core)                           │
│  Entities  ValueObjects  Repositories(interfaces)  Services  │
├─────────────────────────────────────────────────────────────┤
│                  INFRASTRUCTURE                              │
│  Persistence/Sql  NoSql  External Services                   │
└─────────────────────────────────────────────────────────────┘
```

### Couches

| Couche | Description | Dépendances |
|--------|-------------|-------------|
| **Domain** | Entités métier, Value Objects, interfaces des repositories | Aucune |
| **Application** | Use Cases, DTOs, logique applicative | Domain uniquement |
| **Infrastructure** | Implémentations des repositories (SQL, NoSQL) | Domain |
| **Presenter** | Controllers HTTP (API/Web), Frontend React | Application |

### Architecture de base de données hybride

**PostgreSQL (Données relationnelles)** :
- Users, Parkings, Réservations
- Stationnements (sessions de parking)
- Abonnements

**MongoDB/JSON (Données flexibles)** :
- Grilles tarifaires personnalisées
- Créneaux horaires d'abonnement
- Factures
- Logs système

---

## 📦 Entités du domaine

| Entité | Description |
|--------|-------------|
| `User` | Utilisateurs (clients, admins) |
| `ParkingOwner` | Propriétaires de parkings |
| `Parking` | Places de parking avec horaires, tarifs, capacité |
| `Reservation` | Réservations avec statut (confirmed, cancelled, completed) |
| `Stationnement` | Sessions de parking effectives (entrée/sortie) |
| `Abonnement` | Abonnements avec créneaux horaires |
| `SubscriptionType` | Types d'abonnements disponibles |

---

## ✅ Prérequis

### Avec Docker :
- Docker Desktop (ou Docker Engine + Docker Compose)
- Git

### Sans Docker :
- PHP 8.2 ou supérieur
- PostgreSQL 14 ou supérieur
- MongoDB 7 ou supérieur (optionnel, fallback JSON disponible)
- Composer
- Node.js 18+ (pour le frontend React)

---

## 📥 Installation

### 1. Cloner le repository

```bash
git clone <repository-url>
cd clean_architecture
```

### 2. Configurer les variables d'environnement

```bash
cp .env.exemple .env
```

Éditer `.env` avec vos identifiants :

```env
# PostgreSQL
POSTGRES_USER=parking_user
POSTGRES_PASSWORD=your_secure_password
POSTGRES_DB=parking_db

DB_HOST=db                    # 'localhost' sans Docker
DB_NAME=parking_db
DB_USER=parking_user
DB_PASSWORD=your_secure_password
DB_PORT=5432

# MongoDB (optionnel)
MONGO_USER=parking_mongo
MONGO_PASSWORD=your_mongo_password
MONGO_DB=parking_db
```

---

## 🚀 Lancer le projet

### Avec Docker (Recommandé)

```bash
# Build et démarrer
docker compose up -d --build

# Voir les logs
docker compose logs -f
```

**URLs disponibles :**
- **API**: http://localhost
- **PostgreSQL**: localhost:5432
- **MongoDB**: localhost:27017

### Sans Docker

```bash
# Installer les dépendances PHP
composer install

# Initialiser la base de données
psql -U parking_user -d parking_db -f src/docker/postgres/init.sql

# Démarrer le serveur
php -S localhost:8000 -t public
```

### Frontend React

```bash
cd src/Presenter/Frontend
npm install
npm run dev
```

**URL Frontend**: http://localhost:3000

---

## 🗄️ Base de données

### Tables PostgreSQL

| Table | Description |
|-------|-------------|
| `users` | Comptes utilisateurs avec rôles |
| `parking_owners` | Propriétaires de parkings |
| `parkings` | Parkings avec GPS, horaires, tarifs |
| `reservations` | Réservations avec statut et paiement |
| `stationnements` | Sessions de stationnement |
| `abonnements` | Abonnements actifs |
| `subscription_types` | Types d'abonnements |

### Collections MongoDB/JSON

| Collection | Description |
|------------|-------------|
| `pricing_grids` | Tarifs personnalisés (15min, 30min, 1h, jour, nuit) |
| `subscription_time_slots` | Créneaux horaires d'abonnement |
| `invoices` | Factures détaillées |
| `parking_events` | Logs d'entrées/sorties |
| `system_logs` | Logs application |

### Comptes de test (mot de passe: `password123`)

| Email | Rôle | Description |
|-------|------|-------------|
| `lucas.martin@gmail.com` | User | Client standard |
| `sophie.dubois@gmail.com` | Owner | Propriétaire de parking |

---

## 🌐 API Endpoints

### Authentification (JWT)

```bash
# Inscription
POST /api/auth/register
{
  "first_name": "Lucas",
  "last_name": "Martin",
  "email": "lucas@example.com",
  "password": "password123"
}

# Connexion
POST /api/auth/login
{
  "email": "lucas@example.com",
  "password": "password123"
}
# Retourne: { "token": "JWT_TOKEN", "user": {...} }
```

### Utilisateurs

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/users` | Liste des utilisateurs |
| GET | `/api/users/{id}` | Détail utilisateur |
| PUT | `/api/users/{id}` | Modifier utilisateur |
| DELETE | `/api/users/{id}` | Supprimer utilisateur |

### Parkings

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/parkings/search` | Rechercher parkings (lat, lng, radius) |
| POST | `/api/owner/parkings` | Créer un parking (owner) |
| PUT | `/api/owner/parkings/{id}/hours` | Modifier horaires |
| PUT | `/api/owner/parkings/{id}/tarifs` | Modifier tarifs |
| GET | `/api/owner/parkings/{id}/revenue` | Chiffre d'affaires mensuel |

### Réservations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/reservations` | Créer réservation (min 15 min) |
| GET | `/api/reservations/{id}` | Détail réservation |
| GET | `/api/reservations?user_id={id}` | Mes réservations |
| DELETE | `/api/reservations/{id}` | Annuler réservation |

### Stationnements

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/stationnements/enter` | Entrer dans le parking |
| POST | `/api/stationnements/exit` | Sortir (calcul tarif) |
| GET | `/api/stationnements/{id}` | Détail session |
| GET | `/api/stationnements?user_id={id}` | Mes sessions |

### Abonnements

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/subscription-types` | Types disponibles |
| POST | `/api/subscriptions` | Souscrire |
| GET | `/api/subscriptions?user_id={id}` | Mes abonnements |
| DELETE | `/api/subscriptions/{id}` | Résilier |

---

## 🧪 Tests

### Exécuter tous les tests

```bash
# Avec Docker
docker exec -it parking_app_web ./vendor/bin/phpunit

# Sans Docker
./vendor/bin/phpunit
```

### Tests par catégorie

```bash
# Tests unitaires (Domain + Application)
./vendor/bin/phpunit tests/unit --testdox

# Tests fonctionnels (API)
./vendor/bin/phpunit tests/functional --testdox

# Tests d'intégration (avec DB)
./vendor/bin/phpunit tests/integration --testdox
```

### Couverture de tests

| Catégorie | Tests | Description |
|-----------|-------|-------------|
| **Unit** | 60 | Domain (Entities, ValueObjects), Application (UseCases) |
| **Functional** | 27 | API endpoints (User, Reservation, Stationnement) |
| **Integration** | 18 | Repositories avec PostgreSQL |

**Couverture Domain > 60%** ✅

---

## 📁 Structure du projet

```
clean_architecture/
├── config/                     # Configuration
│   ├── database.php           # PostgreSQL PDO
│   ├── mongodb.php            # MongoDB client
│   └── json_storage.php       # Fallback JSON
├── public/
│   └── index.php              # Point d'entrée API (routage)
├── src/
│   ├── Domain/                # COUCHE DOMAINE
│   │   ├── Entities/          # Parking, User, Reservation, etc.
│   │   ├── ValueObjects/      # Price, OpeningHours, TimeSlot
│   │   ├── Repositories/      # Interfaces (contrats)
│   │   ├── Services/          # Services métier (PricingService)
│   │   └── Exceptions/        # Exceptions métier
│   ├── Application/           # COUCHE APPLICATION
│   │   ├── UseCases/          # Cas d'utilisation
│   │   │   ├── Auth/          # LoginUserUseCase
│   │   │   ├── User/          # CRUD User
│   │   │   ├── Parking/       # SearchParkingsUseCase
│   │   │   ├── ParkingOwner/  # CreateParking, CalculateRevenue...
│   │   │   ├── Reservation/   # Create, Cancel, List...
│   │   │   ├── Stationnement/ # Enter, Exit parking
│   │   │   └── Abonnement/    # Gestion abonnements
│   │   └── dtos/              # Data Transfer Objects
│   ├── Infrastructure/        # COUCHE INFRASTRUCTURE
│   │   ├── Persistence/
│   │   │   └── Sql/           # Repositories PostgreSQL
│   │   ├── NoSql/             # Repositories MongoDB/JSON
│   │   └── Services/          # Services externes (JWT, Pricing)
│   ├── Presenter/             # COUCHE PRÉSENTATION
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       ├── Api/       # REST API (JSON)
│   │   │       └── Web/       # Pages HTML (vide - SPA React)
│   │   └── Frontend/          # Application React/TypeScript
│   └── docker/                # Configuration Docker
│       ├── php/
│       ├── postgres/
│       └── mongodb/
├── tests/
│   ├── unit/                  # Tests unitaires
│   │   ├── Domain/            # Entités, ValueObjects
│   │   ├── Application/       # UseCases
│   │   └── Presenter/         # Controllers
│   ├── functional/            # Tests fonctionnels API
│   │   ├── User/
│   │   ├── Reservation/
│   │   └── Stationnement/
│   └── integration/           # Tests avec base de données
├── data/json/                 # Stockage JSON (fallback MongoDB)
├── docs/                      # Documentation
├── scripts/                   # Scripts utilitaires
├── composer.json
├── phpunit.xml
└── docker-compose.yml
```

### Controllers/Api vs Controllers/Web

| Dossier | Utilisation | Contenu |
|---------|-------------|---------|
| `Controllers/Api/` | API REST JSON | Tous les controllers actuels |
| `Controllers/Web/` | Pages HTML rendues côté serveur | **Vide** - le projet utilise une SPA React |

> **Note** : Le dossier `Web/` est prévu pour des pages HTML traditionnelles (comme Blade/Twig), mais ce projet utilise un **frontend React séparé** qui consomme l'API REST. C'est pourquoi ce dossier est vide.

---

## ⚡ Fonctionnalités

### Côté Utilisateur
- Inscription / Connexion (JWT)
- Recherche de parkings par géolocalisation
- Réservation de place (minimum 15 minutes)
- Entrée/Sortie du parking
- Consultation de l'historique
- Gestion des abonnements

### Côté Propriétaire (Owner)
- Création de parking
- Gestion des horaires d'ouverture
- Configuration des tarifs
- Consultation des réservations
- Calcul du chiffre d'affaires mensuel
- Gestion des types d'abonnements

### Règles métier
- **Facturation par tranche de 15 minutes**
- **Mise à jour des places disponibles** à chaque réservation confirmée
- **Pénalité de 20€** + facturation du temps supplémentaire en cas de dépassement
- Validation des créneaux pour éviter les conflits

---

## 🔐 Authentification JWT

Le projet utilise `firebase/php-jwt` pour l'authentification :

```php
// Génération du token
$token = JWT::encode([
    'user_id' => $user->getId(),
    'email' => $user->getEmail(),
    'role' => $user->getRole(),
    'exp' => time() + 3600
], $secretKey, 'HS256');
```

Incluez le token dans les headers :
```bash
Authorization: Bearer <token>
```

---

## 🛠️ Développement

### Standards

- PHP 8.2+ avec types stricts
- PSR-4 Autoloading
- Clean Architecture
- SOLID principles
- Repository Pattern

### Namespaces PSR-4

```
App\Domain\        → src/Domain/
App\Application\   → src/Application/
App\Infrastructure\→ src/Infrastructure/
App\Presenter\     → src/Presenter/
```

---

## 📝 Licence

MIT

---

## 👥 Auteur

Projet étudiant HETIC - Clean Architecture PHP

---

## 🆘 Dépannage

### Erreur de connexion PostgreSQL

```bash
# Vérifier les logs
docker compose logs db

# Redémarrer
docker compose restart db
```

### MongoDB non disponible

Le projet utilise automatiquement un **fallback JSON** si MongoDB n'est pas installé. Les données sont stockées dans `data/json/`.

### Tests échouent

```bash
# Vérifier la connexion DB dans les tests
# Les tests d'intégration nécessitent PostgreSQL

# Exécuter uniquement les tests unitaires
./vendor/bin/phpunit tests/unit
```

---


