# Abonnements — Résumé des modifications

Ce document décrit de manière claire et concise les changements apportés au domaine "abonnement" dans le dépôt, ce qui a été créé ou modifié, pourquoi et comment tester.

**Contexte**
- Objectif principal : supporter des abonnements qui peuvent être restreints à des plages horaires hebdomadaires (créneaux qui peuvent couvrir plusieurs jours et même « wrap » sur la semaine) ou des abonnements "total" (accès complet).
- Approche : créer/modifier des ValueObjects et l'entité `Abonnement` pour gérer correctement ces cas, ajouter des tests unitaires et corriger le code adjacent nécessaire.

**Fichiers créés / modifiés (principalement liés à l'abonnement)**
- `src/Domain/ValueObjects/TimeSlot.php`
  - MIS À JOUR : passage à des créneaux hebdomadaires multi-jours.
  - Nouvelle API : constructeur `__construct(int $startDay, int $startMinute, int $endDay, int $endMinute)`.
  - Méthodes utilitaires : `fromHm()`, `fromDayTime()`, `coversDateTime(DateTimeInterface $dt)`, `isActiveAt(int $day, int $minute)`, `toArray()`.
  - Support des créneaux qui traversent la semaine (ex. vendredi 18:00 -> lundi 10:00).

- `src/Domain/Entities/Abonnement.php`
  - MIS À JOUR : ajout de la méthode `coversDateTime(DateTimeInterface $dateTime)` qui utilise `TimeSlot::coversDateTime()` pour déterminer si un abonnement couvre un instant donné.
  - VALIDATION ajustée : `validateTimeSlots()` désormais accepte un tableau vide quand le type d'abonnement est `TYPE_TOTAL` (abonnement donnant accès permanent).
  - Raison : les tests et la logique métier exigent qu'un abonnement "total" puisse être créé sans créneaux.

- `src/Domain/Repositories/AbonnementRepositoryInterface.php`
  - CRÉÉ : interface Domain pour abstraire la persistance des abonnements.

- Tests unitaires ajoutés / modifiés
  - `tests/unit/Domain/TimeSlot/TimeSlotTest.php` — couvre cas same-day, nuit/passage au jour suivant, wrap sur la semaine.
  - `tests/unit/Domain/Abonnement/AbonnementTest.php` — vérifie comportements `TYPE_TOTAL` et `TYPE_SPECIFIQUE` (ou similaire), et `coversDateTime`.

- Adaptations auxiliaires effectuées pour compatibilité
  - `src/Domain/ValueObjects/Parking/OpeningHours.php` : correction pour construire l'indexation des `TimeSlot` par jour (un `TimeSlot` couvrant plusieurs jours est ajouté à chaque jour correspondant), et usage de `DateTimeInterface`.
  - `src/Domain/Entities/UserEntity.php` : ajustement des types `createdAt/updatedAt` pour accepter `string` ou `DateTimeInterface` et normaliser en `DateTimeImmutable` (nécessaire pour des fixtures/tests qui fournissaient des chaînes). Ce changement n'est pas spécifique à l'abonnement mais a été requis pour faire passer la suite de tests.

**Décisions techniques majeures**
- Représentation des créneaux : on a choisi une échelle en "minutes depuis le début de la semaine" pour comparer plus facilement les instants et gérer le wrap.
- `TYPE_TOTAL` : conçu comme abonnement « tout-temps » — il ne nécessite pas de `TimeSlot` et doit donc pouvoir être instancié avec un tableau vide.
- Tests : TDD / tests unitaires pour valider les cas limites (wrap, nuit, multi-jours).

**Comment tester localement**
- Exécuter uniquement les tests unitaires locaux (sans docker) :

```bash
./vendor/bin/phpunit --testsuite unit
```

- Exécuter l'ensemble des tests dans le conteneur Docker (requiert `docker compose up -d` en amont) :

```bash
docker compose up -d
docker-compose exec web vendor/bin/phpunit
```

(Remarque : les tests fonctionnels/intégration nécessitent des services (Postgres, Mongo) disponibles et configurés — voir `docker-compose.yml` et `config/*.php`.)

**Points d'attention / prochaines améliorations suggérées**
- Consolidation des namespaces : le projet contient à la fois `ObjectValues` et `ValueObjects`, ce qui a causé des duplications et des adaptateurs. Une refonte pour choisir une convention unique est recommandée.
- Cas limites : test des transitions lors de l'heure d'été/hiver si l'application vise des environnements avec DST.

## Diagramme métier — Abonnement (simple)

```
                          +----------------+
                          |  Abonnement    |
                          +----------------+
                          | id             |
                          | userId         |
                          | parkingId      |
                          | type           |--> (TOTAL | SPECIFIQUE | WEEKEND | SOIR)
                          | timeSlots[]    |    - if TOTAL: timeSlots may be empty
                          | startDate      |    - if SPECIFIQUE: at least one TimeSlot
                          | endDate        |
                          | monthlyPrice   |
                          +----------------+

Rules / invariants:
- TYPE_TOTAL: accès intégral, `coversDateTime()` retourne toujours `true` quand abonnement actif.
- TYPE_SPECIFIQUE / TYPE_WEEKEND / TYPE_SOIR: nécessitent au moins un `TimeSlot` (sauf cas TOTAL) ; `coversDateTime()` parcourt les `TimeSlot` et utilise `TimeSlot::coversDateTime()`.
- Durée minimale: 1 mois, maximale: 12 mois.

Exemples d'utilisation:
- Abonnement TOTAL: créé avec `type = 'total'` et `timeSlots = []` → accès permanent entre `startDate` et `endDate`.
- Abonnement SPECIFIQUE: `type = 'specifique'`, `timeSlots = [TimeSlot::fromDayTime(...), ...]` → la méthode `coversDateTime()` teste si l'instant donné est couvert par l'un des créneaux.
```

## Application layer — UseCases et DTOs

Nous avons ajouté une couche Application minimale pour exposer et orchestrer la gestion des abonnements :

- DTOs:
  - `CreateAbonnementDto` : `userId`, `parkingId`, `type`, `timeSlots[]` (internes `TimeSlot`), `startDate`, `endDate`, `monthlyPrice`.
  - `AbonnementResponseDto` : `id`, `userId`, `parkingId`, `type`, `startDate`, `endDate`, `monthlyPrice`.

- UseCases:
  - `CreateAbonnementUseCase` : crée un `Abonnement` à partir d'un `CreateAbonnementDto`, le persiste via `AbonnementRepositoryInterface` et retourne un `AbonnementResponseDto`.
  - `ListAbonnementsForParkingUseCase` : liste les abonnements actifs pour un parking.
  - `GetAbonnementUseCase` : récupère un abonnement par identifiant.

Remarque : ces UseCases sont conçus pour être testés indépendamment (tests unitaires utilisent un mock de `AbonnementRepositoryInterface`).

API HTTP / Controller : le controller doit convertir la requête JSON en `CreateAbonnementDto` (parser des créneaux horaires en `TimeSlot`), appeler `CreateAbonnementUseCase` puis renvoyer le `AbonnementResponseDto`.


---

## Explication synthétique des changements réalisés

- **But général :** ajouter la gestion d'abonnements temporels (créneaux hebdomadaires multi-jours) et d'abonnements "total" tout en respectant la séparation Clean Architecture.

- **Domain (ce qui a été modifié) :**
  - `Abonnement` : ajout de la méthode `coversDateTime(DateTimeInterface)` ; validation adaptée pour autoriser `timeSlots = []` quand `type === TYPE_TOTAL` ; règles métier (durée minimale/maximale, status, paiement).
  - `TimeSlot` (ValueObject) : support des créneaux multi-jours et du "wrap" semaine ; utilitaires `coversDateTime()` / `isActiveAt()` / `toArray()`.

- **Persistance (décision technique) :**
  - Implémentation hybride : la table Postgres `abonnements` conserve les métadonnées (dates, price, status, référence au document de créneaux) ; MongoDB stocke les documents `creneaux_abonnements` pour la représentation souple des `timeSlots`.
  - Amélioration : lors d'une mise à jour qui insère un nouveau document Mongo, le document Mongo ancien est supprimé si présent (cleanup pour éviter les orphelins).

- **Couche Application :**
  - DTOs : `CreateAbonnementDto`, `AbonnementResponseDto`.
  - UseCases : `CreateAbonnementUseCase`, `GetAbonnementUseCase` (lance `NotFoundException` si introuvable), `ListAbonnementsForParkingUseCase`, `SubscribeToAbonnementUseCase`, `ValidateAbonnementUseCase`.

- **Presenter / HTTP :**
  - `AbonnementController` exposé via `public/index.php` avec endpoints protégés par `AuthenticationMiddleware` :
    - `POST /api/abonnements` → création
    - `GET /api/abonnements/{id}` → récupération (404 si introuvable)
    - `GET /api/parkings/{parkingId}/abonnements` → liste abonnements actifs
    - `POST /api/abonnements/{id}/subscribe` → marquer comme payé/activer
    - `POST /api/abonnements/{id}/validate` → vérifier couverture d'une DateTime

- **Tests ajoutés :**
  - Unitaires domaine : `TimeSlot`, `Abonnement::coversDateTime`.
  - UseCases : tests unitaires pour `CreateAbonnementUseCase`, `GetAbonnementUseCase` (maintenant lève `NotFoundException`).
  - Presenter : tests unitaires pour `AbonnementController` (mocks des use cases).

- **Points importants à connaître :**
  - La persistance Postgres + Mongo n'est pas atomique ; le mécanisme de cleanup réduit le risque d'orphelins mais une stratégie plus robuste (replace, transactions compensatoires, ou test d'intégration de bout en bout) est recommandée.
  - Il est conseillé d'ajouter du logging (PSR‑3) pour tracer les erreurs Mongo et les opérations de nettoyage.
  - Consolidation des namespaces `ValueObjects` / `ObjectValues` serait utile pour éviter les adaptateurs et confusions.



