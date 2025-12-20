# ParkingShare - Frontend

Interface utilisateur React pour la plateforme de gestion de parkings ParkingShare.

## 🚀 Fonctionnalités

- **Authentification JWT** avec gestion de session
- **Interface propriétaire** : gestion de parkings, monitoring, abonnements
- **Interface utilisateur** : recherche, réservation, stationnement
- **Gestion temps réel** des places disponibles
- **Facturation automatique** avec génération de PDF
- **Design responsive** avec Tailwind CSS

## 🛠️ Technologies

- **React 18** avec TypeScript
- **Vite** pour le build et le développement
- **Tailwind CSS** + **Radix UI** pour l'interface
- **Clean Architecture** pour l'organisation du code
- **JWT Authentication** avec gestion des tokens

## 📁 Structure du projet

```
src/Presenter/Frontend/
├── components/           # Composants React réutilisables
│   ├── ui/              # Composants UI de base (boutons, inputs, etc.)
│   ├── user/            # Composants spécifiques aux utilisateurs
│   └── ...
├── services/            # Services API pour communication backend
├── hooks/               # Hooks React personnalisés
├── context/             # Contextes React (auth, etc.)
├── types/               # Types TypeScript
├── utils/               # Fonctions utilitaires
├── config/              # Configuration de l'application
└── styles/              # Fichiers CSS/Tailwind
```

## 🔧 Installation et configuration

### 1. Installation des dépendances

```bash
cd src/Presenter/Frontend
npm install
```

### 2. Configuration de l'environnement

```bash
# Copier le fichier d'exemple
cp .env.example .env

# Éditer les variables d'environnement
# REACT_APP_API_BASE_URL=http://localhost:8000/api
```

### 3. Lancement du serveur de développement

```bash
npm run dev
```

L'application sera accessible sur `http://localhost:3000`

## 🏗️ Build de production

```bash
# Build optimisé
npm run build

# Prévisualisation du build
npm run preview
```

## 🔐 Architecture de sécurité

### Authentification JWT
- Tokens stockés dans localStorage avec auto-refresh
- Intercepteurs automatiques pour les requêtes API
- Redirection automatique en cas d'expiration

### Gestion des erreurs
- Centralisation via ApiClient avec types d'erreurs
- Messages d'erreur localisés en français
- Retry automatique pour les erreurs réseau

## 📋 Services API

### AuthService
- `login()` - Connexion utilisateur
- `register()` - Inscription
- `logout()` - Déconnexion
- `getCurrentUser()` - Profil utilisateur actuel

### ParkingService
- `getMyParkings()` - Liste des parkings (propriétaire)
- `searchParkings()` - Recherche de parkings
- `createParking()` - Création d'un parking
- `getParkingAvailability()` - Vérification disponibilité

### ReservationService
- `getUserReservations()` - Réservations utilisateur
- `createReservation()` - Nouvelle réservation
- `cancelReservation()` - Annulation
- `generateInvoice()` - Génération facture

### StationnementService
- `startStationnement()` - Début de stationnement
- `endStationnement()` - Fin de stationnement
- `getViolations()` - Liste des violations

### SubscriptionService
- `createSubscriptionType()` - Création type abonnement
- `getUserSubscriptions()` - Abonnements utilisateur
- `createSubscription()` - Souscription abonnement

## 🎨 Composants UI

### Composants de base (ui/)
- Button, Input, Label - Éléments de formulaire
- Card, Dialog, Tabs - Conteneurs et navigation
- Select, Checkbox - Sélecteurs

### Composants métier
- **LoginPage** - Authentification avec onglets
- **Dashboard** - Interface propriétaire
- **UserDashboard** - Interface utilisateur
- **ParkingsPage** - Gestion des parkings
- **AddParkingDialog** - Création de parking

## 🔍 Hooks personnalisés

### useApi
```typescript
const { data, loading, error, execute } = useApi(() => 
  parkingService.getMyParkings()
);
```

### useAsyncOperation
```typescript
const { loading, error, execute } = useAsyncOperation();

const handleSubmit = async () => {
  const result = await execute(() => 
    parkingService.createParking(data)
  );
};
```

## 🌐 Intégration Backend

### Configuration API
- Base URL configurable via variables d'environnement
- Timeout de 30 secondes pour toutes les requêtes
- Headers automatiques avec token JWT

### Mapping des DTOs
- Types TypeScript alignés sur les DTOs PHP
- Sérialisation automatique JSON
- Validation côté client des données

## 🚦 États de l'application

### Authentification
- `loading` - Vérification token au démarrage
- `authenticated` - Utilisateur connecté
- `user` - Données utilisateur (nom, email, rôle)

### API Calls
- `loading` - Requête en cours
- `error` - Message d'erreur localisé
- `data` - Données reçues de l'API

## 📱 Interface responsive

- **Mobile-first** design avec Tailwind
- **Breakpoints** : sm (640px), md (768px), lg (1024px)
- **Touch-friendly** sur tablettes et mobiles
- **Grid layouts** adaptatifs pour les listes

## 🔧 Scripts disponibles

```bash
npm run dev        # Serveur de développement
npm run build      # Build de production
npm run preview    # Prévisualisation du build
npm run lint       # Linting ESLint
npm run type-check # Vérification TypeScript
```

## 🐛 Debugging

### Variables de débogage
```bash
REACT_APP_DEBUG=true    # Logs détaillés
REACT_APP_API_BASE_URL  # URL de l'API backend
```

### Logs développement
- Erreurs API détaillées dans la console
- États des hooks visibles via React DevTools
- Network tab pour debugging des requêtes

---

## 🤝 Contribution

1. Respecter l'architecture Clean avec séparation des responsabilités
2. Utiliser TypeScript strict pour tous les nouveaux composants
3. Ajouter des tests unitaires pour les services critiques
4. Suivre les conventions de nommage du projet