# Système de Gestion des Avantages d'Abonnements

## Vue d'ensemble

Ce système permet aux propriétaires de parkings de créer des types d'abonnements avec des avantages personnalisés (benefits) que les utilisateurs peuvent consulter avant de souscrire.

## Architecture

### Base de données

Nouvelle table `subscription_types` :
- **id** : UUID unique
- **parking_id** : Référence au parking
- **name** : Nom de l'abonnement (ex: "Abonnement Mensuel Premium")
- **description** : Description optionnelle
- **benefits** : Tableau JSON d'avantages stocké comme TEXT
- **price** : Prix de l'abonnement
- **duration_days** : Durée en jours
- **time_slots_id** : Référence MongoDB pour les créneaux horaires (optionnel)
- **is_active** : Actif ou non

Modification de la table `abonnements` :
- **subscription_type_id** : Référence vers subscription_types (optionnel)

### Domain Layer

#### Entité `SubscriptionType`
```php
new SubscriptionType(
    parkingId: string,
    name: string,
    price: Price,
    durationDays: int,
    createdAt: DateTime,
    updatedAt: DateTime,
    description?: string,
    benefits: array = [],  
    timeSlots: array = [],
    isActive: bool = true,
    id?: string
)
```

#### Repository `SubscriptionTypeRepository`
- `save(SubscriptionType): ?SubscriptionType` - Créer ou mettre à jour
- `findById(string): ?SubscriptionType` - Trouver par ID
- `findByParkingId(string): array` - Tous les types d'un parking
- `findActiveByParkingId(string): array` - Types actifs uniquement
- `delete(string): bool` - Supprimer un type

### API Endpoints

#### GET `/api/owner/parkings/{parkingId}/subscription-types`
Récupère tous les types d'abonnements actifs d'un parking.

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "uuid",
      "parkingId": "uuid",
      "name": "Abonnement Mensuel Premium",
      "description": "Abonnement premium avec tous les avantages",
      "benefits": [
        "Accès illimité 24/7",
        "Place garantie",
        "Aucune réservation nécessaire"
      ],
      "price": 99.99,
      "duration": 30,
      "timeSlots": [],
      "isActive": true,
      "createdAt": "2025-01-16T10:00:00+00:00",
      "updatedAt": "2025-01-16T10:00:00+00:00"
    }
  ]
}
```

#### POST `/api/owner/parkings/{parkingId}/subscription-types`
Crée un nouveau type d'abonnement.

**Request Body:**
```json
{
  "name": "Abonnement Mensuel Premium",
  "description": "Abonnement premium",
  "benefits": [
    "Accès illimité 24/7",
    "Place garantie"
  ],
  "price": 99.99,
  "duration": 30
}
```

**Response:** Même format que GET

### Frontend

#### Types TypeScript

```typescript
interface SubscriptionType {
  id: string;
  parkingId: string;
  name: string;
  description?: string;
  benefits?: string[];  
  price: number;
  duration: number;
  timeSlots?: Array<{...}>;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}
```

#### Composants

**AddSubscriptionDialog.tsx** :
- Permet de créer un type d'abonnement
- Champs dynamiques pour ajouter/supprimer des avantages
- Envoie un tableau `benefits` au backend

**ParkingDetailsDialog.tsx** :
- Affiche les types d'abonnements disponibles
- Liste tous les avantages avec des checkmarks verts

**ParkingDetailsPage.tsx** :
- Affiche les détails des abonnements
- Liste les avantages de chaque type

## Migration

Pour mettre à jour une base existante, exécutez :

```bash
psql -U parking_user -d parking_db -f scripts/migrations/add_subscription_types_table.sql
```

Ou avec Docker :

```bash
docker exec -i parking_postgres psql -U parking_user -d parking_db < scripts/migrations/add_subscription_types_table.sql
```

## Tests

Exécuter les tests de création :

```bash
php scripts/test_subscription_types.php
```

Ce script :
1. Se connecte à PostgreSQL
2. Récupère un parking existant
3. Crée deux types d'abonnements avec des avantages
4. Vérifie la récupération des types

## Principes Clean Architecture Respectés

✅ **Séparation des responsabilités** :
- Domain : Entité SubscriptionType avec validation métier
- Infrastructure : Repository PostgreSQL + MongoDB optionnel
- Presentation : API REST et composants React

✅ **Injection de dépendances** :
- Repository accepte PDO et MongoDB optionnel
- Pas de couplage fort avec MongoDB

✅ **Pas de SQL direct** :
- Utilisation exclusive du repository
- Respect du pattern Repository

✅ **Validation métier** :
- Validation dans le constructeur de l'entité
- Benefits doit être un tableau de strings
- Prix et durée doivent être positifs

## Exemples d'utilisation

### Côté Owner (création)

```typescript
// Dans AddSubscriptionDialog.tsx
const benefits = [
  'Accès 24/7',
  'Place garantie',
  'Sans réservation'
];

await subscriptionService.createSubscriptionType(parkingId, {
  name: 'Abonnement Premium',
  price: 99.99,
  duration: 30,
  benefits: benefits
});
```

### Côté User (consultation)

```typescript
// Dans ParkingDetailsDialog.tsx
const types = await subscriptionService.getAvailableSubscriptionTypes(parkingId);

types.forEach(type => {
  console.log(type.name);
  type.benefits?.forEach(benefit => {
    console.log(`  ✓ ${benefit}`);
  });
});
```

## Prochaines étapes

- [ ] Ajouter endpoint PUT pour modifier un type existant
- [ ] Ajouter endpoint DELETE pour supprimer un type
- [ ] Ajouter validation côté frontend (minimum 1 avantage recommandé)
- [ ] Ajouter traductions i18n pour les avantages
- [ ] Implémenter l'historique des modifications
