# Schéma de données – Projet Parking

Ce document décrit l’architecture des données du projet en distinguant :

- une **base relationnelle** (MySQL / PostgreSQL / SQLite) pour les données structurées et relationnelles ;
- une partie **NoSQL / fichiers** (JSON, logs, PDFs/HTML) pour les données flexibles, évolutives ou volumineuses.

L’objectif est de tirer parti des forces des deux mondes : **SQL** pour la cohérence et les jointures, **NoSQL** pour la souplesse des formats.

---

## 1. Base relationnelle (SQL)

La base relationnelle est utilisée pour toutes les données **structurées**, avec schéma fixe et relations fortes (clé étrangère, intégrité référentielle, etc.).

### 1.1 Table `utilisateurs`

Contient les utilisateurs “conducteurs” de l’application (ceux qui réservent et utilisent les parkings).

| Colonne       | Type (générique) | Description                            |
|--------------|------------------|----------------------------------------|
| id           | INT (PK)         | Identifiant unique                     |
| email        | VARCHAR          | Adresse e-mail (unique)                |
| mot_de_passe | VARCHAR          | Mot de passe hashé                     |
| nom          | VARCHAR          | Nom de famille                         |
| prenom       | VARCHAR          | Prénom                                 |
| cree_le      | DATETIME         | Date de création du compte             |
| maj_le       | DATETIME         | Dernière mise à jour                   |

**Remarques :**
- `email` doit idéalement être indexé et unique.
- `mot_de_passe` stocke uniquement un hash (jamais le mot de passe en clair).

---

### 1.2 Table `proprietaires`

Contient les propriétaires de parkings. Ils disposent de comptes distincts des “utilisateurs” classiques.

| Colonne       | Type (générique) | Description                            |
|--------------|------------------|----------------------------------------|
| id           | INT (PK)         | Identifiant unique                     |
| email        | VARCHAR          | Adresse e-mail (unique)                |
| mot_de_passe | VARCHAR          | Mot de passe hashé                     |
| nom          | VARCHAR          | Nom                                    |
| prenom       | VARCHAR          | Prénom                                 |
| cree_le      | DATETIME         | Date de création du compte             |
| maj_le       | DATETIME         | Dernière mise à jour                   |

**Remarques :**
- Comme pour `utilisateurs`, on applique des contraintes d’unicité sur `email`.
- Ces comptes servent à gérer les parkings, les grilles tarifaires, les abonnements, etc.

---

### 1.3 Table `parkings`

Contient les informations principales sur les parkings gérés par les propriétaires.

| Colonne         | Type (générique) | Description                                              |
|-----------------|------------------|----------------------------------------------------------|
| id              | INT (PK)         | Identifiant unique                                       |
| proprietaire_id | INT (FK)         | Référence vers `proprietaires.id`                       |
| nom             | VARCHAR          | Nom du parking                                           |
| latitude        | DECIMAL          | Coordonnée GPS (latitude)                               |
| longitude       | DECIMAL          | Coordonnée GPS (longitude)                              |
| nombre_places   | INT              | Nombre total de places                                  |
| horaires        | TEXT / VARCHAR   | Horaires d’ouverture (résumé lisible)                   |
| cree_le         | DATETIME         | Date de création                                         |
| maj_le          | DATETIME         | Dernière mise à jour                                     |

**Remarques :**
- La **grille tarifaire détaillée** n’est pas stockée ici mais en NoSQL (voir section 2.2).
- `horaires` peut contenir un texte simple (ex : “Lun–Ven 8h–20h, Sam 9h–18h”) ; les cas complexes seront gérés côté NoSQL/logique métier.

---

### 1.4 Table `reservations`

Représente les réservations effectuées par les utilisateurs pour un créneau horaire donné dans un parking.

| Colonne        | Type (générique)       | Description                                          |
|----------------|------------------------|------------------------------------------------------|
| id             | INT (PK)               | Identifiant unique                                   |
| utilisateur_id | INT (FK)               | Référence vers `utilisateurs.id`                     |
| parking_id     | INT (FK)               | Référence vers `parkings.id`                         |
| debut          | DATETIME               | Date/heure de début de la réservation                |
| fin            | DATETIME               | Date/heure de fin de la réservation                  |
| prix           | DECIMAL                | Prix calculé pour cette réservation                  |
| etat_paiement  | ENUM / BOOL / INT      | État de paiement : `paye` / `non_paye` (ou 0/1)      |
| cree_le        | DATETIME               | Date de création de la réservation                   |
| maj_le         | DATETIME               | Dernière mise à jour                                 |

**Remarques :**
- Le `prix` est calculé à partir de la **grille tarifaire NoSQL** au moment de la réservation (snapshotté ici).
- `etat_paiement` peut être un booléen, un enum ou un petit entier selon le SGBD choisi.

---

### 1.5 Table `stationnements`

Représente les stationnements réels : entrée et sortie du véhicule dans un parking.  
Peut être lié (ou non) à une réservation.

| Colonne          | Type (générique) | Description                                                  |
|------------------|------------------|--------------------------------------------------------------|
| id               | INT (PK)         | Identifiant unique                                           |
| utilisateur_id   | INT (FK)         | Référence vers `utilisateurs.id`                             |
| parking_id       | INT (FK)         | Référence vers `parkings.id`                                 |
| entree           | DATETIME         | Date/heure d’entrée dans le parking                          |
| sortie           | DATETIME NULL    | Date/heure de sortie (NULL si en cours)                      |
| prix             | DECIMAL          | Prix du stationnement (hors pénalités)                       |
| penalites        | DECIMAL          | Montant des pénalités éventuelles (0 si aucune)              |
| cree_le          | DATETIME         | Date de création de l’enregistrement                         |
| maj_le           | DATETIME         | Dernière mise à jour                                         |

**Remarques :**
- Le `prix` est calculé sur la base de la grille tarifaire NoSQL (durée réelle, tranches, etc.).
- `penalites` sert à appliquer les règles de dépassement de durée, stationnement hors créneau, etc.

---

### 1.6 Table `abonnements`

Représente les abonnements souscrits par les utilisateurs pour un parking (abonnement week-end, soir, complet, etc.).

| Colonne        | Type (générique) | Description                                             |
|----------------|------------------|---------------------------------------------------------|
| id             | INT (PK)         | Identifiant unique                                      |
| utilisateur_id | INT (FK)         | Référence vers `utilisateurs.id`                        |
| parking_id     | INT (FK)         | Référence vers `parkings.id`                            |
| type           | VARCHAR / ENUM   | Type d’abonnement (`total`, `weekend`, `soir`, …)       |
| date_debut     | DATE             | Date de début de validité                               |
| date_fin       | DATE             | Date de fin de validité                                 |
| cree_le        | DATETIME         | Date de création                                        |
| maj_le         | DATETIME         | Dernière mise à jour                                    |

**Remarques :**
- Les **créneaux horaires associés à l’abonnement** (jours, heures, périodes) ne sont pas en colonnes ici : ils sont gérés en NoSQL (voir 2.1).
- `type` permet de distinguer rapidement les grandes catégories (ex. pour filtrer en back-office).

---

### 1.7 Table `factures`

Table SQL qui stocke les informations principales (métadonnées) des factures, tandis que le détail complet (lignes, TVA, etc.) peut être déporté en JSON ou en fichier.

| Colonne          | Type            | Description                                                  |
|------------------|-----------------|--------------------------------------------------------------|
| id               | INT (PK)        | Identifiant unique de la facture                             |
| utilisateur_id   | INT (FK)        | Référence vers `utilisateurs.id`                             |
| parking_id       | INT (FK)        | Référence vers `parkings.id`                                 |
| reservation_id   | INT (FK) NULL   | Référence vers `reservations.id` (NULL si non applicable)    |
| stationnement_id | INT (FK) NULL   | Référence vers `stationnements.id` (NULL si non applicable)  |
| abonnement_id    | INT (FK) NULL   | Référence vers `abonnements.id` (NULL si non applicable)     |
| montant_total    | DECIMAL         | Montant total TTC                                            |
| devise           | VARCHAR(3)      | Devise (ex. `EUR`)                                           |
| statut           | VARCHAR / ENUM  | ex. `brouillon`, `en_attente`, `payee`, `annulee`            |
| chemin_document  | VARCHAR NULL    | Chemin vers le fichier PDF/HTML généré (si utilisé)          |
| cree_le          | DATETIME        | Date d’émission / création                                   |
| maj_le           | DATETIME        | Dernière mise à jour                                         |

**Remarques :**
- On peut lier la facture à une réservation, un stationnement ou un abonnement selon le contexte.
- Le contenu détaillé de la facture (lignes de facturation, TVA, remises, etc.) peut être sérialisé dans un document JSON ou directement dans un PDF.

---

## 2. Données NoSQL / JSON / Fichiers

Certaines données ne se prêtent pas bien à un schéma SQL strict parce qu’elles sont :

- très variables selon les cas,
- susceptibles d’évoluer souvent (ajout de nouvelles propriétés),
- volumineuses (logs, historique technique),
- ou peu critiques pour l’intégrité relationnelle.

Pour ces cas, on utilise une base **NoSQL** (type documents) ou de simples **fichiers JSON / logs / PDFs**.

---

### 2.1 Créneaux horaires des abonnements (NoSQL)

Chaque abonnement peut avoir des créneaux horaires différents (ex. week-end, soirées, nuits, combinaisons de jours/heures).  
Plutôt que de figer un schéma SQL complexe, on stocke ces informations dans des documents JSON.

**Collection / Document :** `creneaux_abonnements`

Exemple de document (abonnement week-end) :

```json
{
  "abonnement_id": 42,
  "type": "weekend",
  "creneaux": [
    { "jour": "Samedi", "debut": "08:00", "fin": "22:00" },
    { "jour": "Dimanche", "debut": "08:00", "fin": "20:00" }
  ]
}
```

Exemple de document (abonnement de soirée) :

```json
{
  "abonnement_id": 43,
  "type": "soir",
  "creneaux": [
    { "jour": "Lundi", "debut": "18:00", "fin": "08:00" },
    { "jour": "Mardi", "debut": "18:00", "fin": "08:00" }
  ]
}
```

**Avantages :**
- chaque abonnement peut avoir sa propre configuration de créneaux sans migration SQL ;
- la structure du JSON peut être enrichie (ajout d’exceptions, jours fériés, etc.).

---

### 2.2 Grilles tarifaires des parkings (NoSQL)

Les grilles tarifaires peuvent être :

- différentes pour chaque parking,
- dégressives, progressives, avec des forfaits de nuit, des tarifs spéciaux week-end, etc.

Au lieu d’un modèle SQL figé, on stocke ces grilles sous forme de documents JSON.

**Collection / Document :** `grilles_tarifaires_parking`

Exemple simple :

```json
{
  "parking_id": 10,
  "grille": {
    "30min": 0.60,
    "1h": 1.20,
    "2h": 3.20,
    "nuit": 2.00
  }
}
```

Exemple plus riche :

```json
{
  "parking_id": 11,
  "grille": {
    "jour": {
      "0_15": 0.40,
      "16_30": 0.40,
      "31_60": 0.80
    },
    "nuit": {
      "forfait": 2.00,
      "debut": "20:00",
      "fin": "08:00"
    },
    "weekend": {
      "forfait_jour": 3.50
    }
  }
}
```

**Avantages :**
- grande flexibilité pour changer les règles ;
- pas besoin de modifier la structure SQL au moindre ajout de tranche ou de période.

---

### 2.3 Factures détaillées (JSON + fichiers PDF/HTML)

Les factures peuvent être très riches et variables :

- plusieurs lignes (réservation, stationnement, pénalités, réduction) ;
- plusieurs taux de TVA ;
- informations légales diverses.

On peut donc :

1. **Générer un fichier PDF ou HTML** (pour l’envoi au client) et en stocker le chemin dans `factures.chemin_document`.
2. Stocker aussi une version **JSON détaillée** dans une collection NoSQL.

**Collection / Document :** `factures_detaillees`

Exemple :

```json
{
  "facture_id": 1234,
  "utilisateur_id": 5,
  "parking_id": 10,
  "lignes": [
    { "type": "reservation", "description": "Réservation 2h", "montant": 3.20 },
    { "type": "penalite", "description": "Dépassement 30 min", "montant": 1.00 }
  ],
  "tva": {
    "taux": 20,
    "montant": 0.84
  },
  "total_ttc": 5.04,
  "devise": "EUR"
}
```

**Avantages :**
- facile à rejouer pour recalculer ou analyser ;
- permet de stocker plus d’infos que dans des colonnes SQL classiques.

---

### 2.4 Logs / Historique technique (NoSQL / fichiers)

Les logs techniques peuvent inclure :

- entrées/sorties des parkings (événements bas niveau) ;
- logs applicatifs (erreurs, warnings, infos) ;
- historique des tentatives de connexion, appels d’API, etc.

Ces données sont souvent :

- volumineuses,
- peu utilisées dans les règles métier,
- principalement exploitées pour du debug ou de la supervision.

Elles sont donc bien adaptées à du stockage :

- en **NoSQL** (collection `logs_parking` par exemple) ;
- ou en **fichiers** (`logs/2025-05-01.log`, etc.).

Exemple de log JSON :

```json
{
  "timestamp": "2025-05-01T10:15:23Z",
  "type": "ENTREE_PARKING",
  "parking_id": 10,
  "utilisateur_id": 5,
  "stationnement_id": 99,
  "details": "Badge RFID validé"
}
```

---

## 3. Résumé de l’architecture globale

### 3.1 Données stockées en SQL (relationnel)

- `utilisateurs` : comptes des conducteurs.
- `proprietaires` : comptes des propriétaires de parkings.
- `parkings` : parkings physiques (coordonnées GPS, capacité, horaires).
- `reservations` : réservations de créneaux par les utilisateurs.
- `stationnements` : stationnements réels (entrées/sorties, pénalités).
- `abonnements` : souscriptions à des formules d’abonnement.
- `factures` : métadonnées des factures (liens avec réservation/stationnement/abonnement, montant total, statut).

### 3.2 Données stockées en NoSQL / JSON / fichiers

- **Créneaux horaires des abonnements**  
  → Collection JSON `creneaux_abonnements` (par `abonnement_id`).
- **Grilles tarifaires de chaque parking**  
  → Collection JSON `grilles_tarifaires_parking` (par `parking_id`).
- **Factures détaillées**  
  → Collection JSON `factures_detaillees` + fichiers PDF/HTML.
- **Logs / historique technique**  
  → Collection JSON (ex. `logs_parking`) ou fichiers de logs.

---

## 4. Avantages de cette architecture hybride

- Le **SQL** gère :
  - l’intégrité des données essentielles (utilisateurs, parkings, réservations…),
  - les jointures, les filtrages et les agrégations classiques,
  - les contraintes (clés étrangères, uniques, etc.).

- Le **NoSQL / JSON / fichiers** gère :
  - les structures de données susceptibles de changer souvent,
  - les cas spécifiques par parking ou par abonnement,
  - les données volumineuses ou techniques (logs, historiques détaillés),
  - les formats prêts à l’emploi pour le front ou l’export (PDF, HTML, JSON).

Cette séparation permet de garder une base relationnelle propre et stable, tout en restant très flexible sur les règles métiers (tarifs, créneaux, détails de facturation, logs techniques).
