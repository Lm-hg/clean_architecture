# Schéma de la base de données – Projet Parking

## 1. Table `utilisateurs`

Contient les conducteurs qui réservent et utilisent les parkings.

- `id` (INT, PK, auto-incrément) : identifiant unique de l'utilisateur
- `email` (VARCHAR, unique) : e-mail de connexion
- `mot_de_passe` (VARCHAR) : mot de passe hashé
- `prenom` (VARCHAR) : prénom de l'utilisateur
- `nom` (VARCHAR) : nom de l'utilisateur
- `cree_le` (DATETIME) : date de création du compte
- `mis_a_jour_le` (DATETIME) : dernière mise à jour

---

## 2. Table `proprietaires`

Contient les propriétaires de parkings.

- `id` (INT, PK, auto-incrément) : identifiant unique du propriétaire
- `email` (VARCHAR, unique) : e-mail de connexion
- `mot_de_passe` (VARCHAR) : mot de passe hashé
- `raison_sociale` (VARCHAR, nullable) : nom de l'entreprise, si applicable
- `prenom` (VARCHAR) : prénom du propriétaire
- `nom` (VARCHAR) : nom du propriétaire
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 3. Table `parkings`

Contient les parkings gérés par les propriétaires.

- `id` (INT, PK, auto-incrément)
- `proprietaire_id` (INT, FK → proprietaires.id)
- `nom` (VARCHAR)
- `description` (TEXT, optionnel)
- `adresse` (VARCHAR, optionnel)
- `latitude` (DECIMAL)
- `longitude` (DECIMAL)
- `nombre_places` (INT) : nombre total de places
- `ouvert_24_24` (TINYINT(1)) : 1 si ouvert 24/24, sinon 0
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 4. Table `periodes_ouverture_parking`

Décrit les horaires d'ouverture hebdomadaires de chaque parking.

- `id` (INT, PK, auto-incrément)
- `parking_id` (INT, FK → parkings.id)
- `jour_semaine` (TINYINT) : 0 à 6 (ex. 0 = lundi)
- `heure_debut` (TIME)
- `heure_fin` (TIME)

---

## 5. Table `tarification_parking`

Règles de tarification par tranches de temps au sein d’un parking.

- `id` (INT, PK, auto-incrément)
- `parking_id` (INT, FK → parkings.id)
- `minute_debut` (INT) : minute de début de la tranche (ex. 0, 60, 180…)
- `minute_fin` (INT) : minute de fin de la tranche
- `prix_par_15min` (DECIMAL(10,2)) : prix appliqué pour chaque bloc de 15 minutes

---

## 6. Table `reservations`

Réservations effectuées par les utilisateurs sur un créneau donné.

- `id` (INT, PK, auto-incrément)
- `utilisateur_id` (INT, FK → utilisateurs.id)
- `parking_id` (INT, FK → parkings.id)
- `debut` (DATETIME) : début de la réservation
- `fin` (DATETIME) : fin de la réservation
- `statut` (ENUM) : `en_attente`, `confirme`, `annule`, `termine`, `expire`, `non_honore`
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 7. Table `sejours_parking`

Représente la présence réelle du véhicule dans le parking (entrée/sortie).

- `id` (INT, PK, auto-incrément)
- `utilisateur_id` (INT, FK → utilisateurs.id)
- `parking_id` (INT, FK → parkings.id)
- `reservation_id` (INT, FK → reservations.id, nullable)
- `entree` (DATETIME) : date/heure d'entrée
- `sortie` (DATETIME, nullable) : date/heure de sortie
- `statut` (ENUM) : `en_cours`, `termine`, `penalise`
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 8. Table `formules_abonnement`

Les différentes formules proposées par un parking (total, week-end, soir, etc.).

- `id` (INT, PK, auto-incrément)
- `parking_id` (INT, FK → parkings.id)
- `nom` (VARCHAR)
- `description` (TEXT)
- `duree_min_mois` (INT) : durée minimale en mois
- `duree_max_mois` (INT) : durée maximale en mois
- `prix_mensuel` (DECIMAL(10,2))
- `actif` (TINYINT(1)) : 1 si la formule est active
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 9. Table `creneaux_formule_abonnement`

Décrit les créneaux horaires couverts par une formule (week-end, soirée, etc.).

- `id` (INT, PK, auto-incrément)
- `formule_abonnement_id` (INT, FK → formules_abonnement.id)
- `jour_semaine_debut` (TINYINT) : jour de début (0–6)
- `heure_debut` (TIME) : heure de début
- `jour_semaine_fin` (TINYINT) : jour de fin (0–6)
- `heure_fin` (TIME) : heure de fin

---

## 10. Table `abonnements`

Abonnements souscrits par les utilisateurs auprès d’un parking.

- `id` (INT, PK, auto-incrément)
- `utilisateur_id` (INT, FK → utilisateurs.id)
- `parking_id` (INT, FK → parkings.id)
- `formule_abonnement_id` (INT, FK → formules_abonnement.id)
- `date_debut` (DATE)
- `date_fin` (DATE)
- `statut` (ENUM) : `actif`, `resilie`, `expire`
- `prix_mensuel` (DECIMAL(10,2)) : prix appliqué au moment de la souscription
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 11. Table `factures`

Factures liées aux réservations et abonnements, incluant les pénalités éventuelles.

- `id` (INT, PK, auto-incrément)
- `utilisateur_id` (INT, FK → utilisateurs.id)
- `parking_id` (INT, FK → parkings.id)
- `reservation_id` (INT, FK → reservations.id, nullable)
- `abonnement_id` (INT, FK → abonnements.id, nullable)
- `date_emission` (DATETIME)
- `montant_total` (DECIMAL(10,2))
- `montant_penalite` (DECIMAL(10,2)) : 0 si aucune pénalité
- `devise` (VARCHAR(3)) : ex. "EUR"
- `statut` (ENUM) : `brouillon`, `en_attente`, `payee`, `annulee`
- `details` (TEXT ou JSON) : détail de calcul si besoin
- `cree_le` (DATETIME)
- `mis_a_jour_le` (DATETIME)

---

## 12. Table `lignes_facture` (optionnelle mais recommandée)

Détail des lignes d’une facture (stationnement, pénalité, abonnement, etc.).

- `id` (INT, PK, auto-incrément)
- `facture_id` (INT, FK → factures.id)
- `description` (VARCHAR)
- `quantite` (INT)
- `prix_unitaire` (DECIMAL(10,2))
- `total_ligne` (DECIMAL(10,2))
