## 📁 Structure des dossiers

```
clean_architecture/
│
├── src/
│   ├── Domain/                           # 🔵 COUCHE DOMAINE (Cœur métier)
│   │   ├── Entities/
│   │   │   ├── User.php                 # ✅ Existe
│   │   │   ├── ParkingOwner.php         # 🆕 À créer
│   │   │   ├── Parking.php              # 🆕 À créer
│   │   │   ├── Reservation.php          # ⚠️ Renommer Booking → Reservation ✅ 
│   │   │   ├── Stationnement.php        # 🆕 À créer
│   │   │   └── Abonnement.php           # ✅ Implémenté
│   │   │   ├── ParkingOwner.php         # ✅ Existe
│   │   │   ├── Parking.php              # ✅ Existe
│   │   │   ├── Reservation.php          # ✅ Existe
│   │   │   ├── Stationnement.php        # ✅ Existe
│   │   │   └── Abonnement.php           # ✅ Existe
│   │   │
│   │   ├── ValueObjects/ ⚠️ Renommer ObjectValues en ValuesObjects
│   │   │   ├── User/
│   │   │   │   ├── Email.php            # ✅ Existe
│   │   │   │   ├── IdUser.php           # ✅ Existe
│   │   │   │   ├── Password.php         # ✅ Existe
│   │   │   │   └── Role.php             # ✅ Existe
│   │   │   ├── Parking/
│   │   │   │   ├── GPSCoordinates.php   # 🆕 À créer
│   │   │   │   ├── Address.php          # 🆕 À créer
│   │   │   │   ├── OpeningHours.php     # 🆕 À créer (plages horaires)
│   │   │   │   └── ParkingId.php        # 🆕 À créer
│   │   │   ├── Pricing/
│   │   │   │   ├── Price.php            # ✅ Existe
│   │   │   │   ├── Tarif.php            # 🆕 À créer (tarif par tranche 15min)
│   │   │   │   └── TarifCollection.php  # 🆕 À créer (grille tarifaire)
│   │   │   └── TimeSlot.php             # 🆕 À créer (créneau horaire)
│   │   │
│   │   ├── Repositories/                # Interfaces uniquement
│   │   │   ├── UserRepositoryInterface.php          # ✅ Existe
│   │   │   ├── ParkingOwnerRepositoryInterface.php # 🆕 À créer
│   │   │   ├── ParkingRepositoryInterface.php      # 🆕 À créer
│   │   │   ├── ReservationRepositoryInterface.php  # ⚠️ Renommer Booking en Reser
│   │   │   ├── StationnementRepositoryInterface.php # 🆕 À créer
│   │   │   └── AbonnementRepositoryInterface.php   # ✅ Implémenté
│   │   │
│   │   ├── Services/                    # Services du domaine (logique métier complexe)
│   │   │   ├── JwtServiceInterface.php  # ✅ Existe
│   │   │   ├── ParkingAvailabilityService.php      # 🆕 Vérifier places disponibles
│   │   │   ├── ReservationPriceCalculator.php      # 🆕 Calculer prix réservation
│   │   │   ├── PenaltyCalculator.php               # 🆕 Calculer pénalités
│   │   │   ├── OverlapDetector.php                 # 🆕 Détecter chevauchements
│   │   │   └── InvoiceGenerator.php                # 🆕 Générer factures
│   │   │
│   │   └── Exceptions/
│   │       ├── ParkingFullException.php            # 🆕 À créer
│   │       ├── InvalidReservationException.php     # 🆕 À créer
│   │       ├── UnauthorizedAccessException.php     # 🆕 À créer
│   │       ├── PenaltyException.php                # 🆕 À créer
│   │       └── OverlappingReservationException.php # 🆕 À créer
│   │
│   ├── Application/                     # 🟢 COUCHE APPLICATION (Use Cases)
│   │   ├── DTOs/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginDto.php         # ✅ Existe
│   │   │   │   └── LoginResponseDto.php # ✅ Existe
│   │   │   ├── User/
│   │   │   │   ├── CreateUserDto.php    # ✅ Existe
│   │   │   │   ├── UpdateUserDto.php    # ✅ Existe
│   │   │   │   └── UserResponseDto.php  # ✅ Existe
│   │   │   ├── Parking/                 # 🆕 À créer
│   │   │   │   ├── CreateParkingDto.php
│   │   │   │   ├── UpdateParkingDto.php
│   │   │   │   └── ParkingResponseDto.php
│   │   │   ├── Reservation/             # 🆕 À créer
│   │   │   │   ├── CreateReservationDto.php
│   │   │   │   └── ReservationResponseDto.php
│   │   │   ├── Stationnement/           # 🆕 À créer
│   │   │   │   └── StationnementResponseDto.php
│   │   │   └── Abonnement/              # 🆕 À créer
│   │   │       ├── CreateAbonnementDto.php # ✅ Implémenté
│   │   │       └── AbonnementResponseDto.php # ✅ Implémenté
│   │   │
│   │   └── UseCases/
│   │       ├── Auth/
│   │       │   └── LoginUserUseCase.php # ✅ Existe
│   │       │
│   │       ├── User/                    # ✅ Existe (à compléter)
│   │       │   ├── CreateUserUseCase.php
│   │       │   ├── GetUserUseCase.php
│   │       │   ├── ListUsersUseCase.php
│   │       │   ├── UpdateUserUseCase.php
│   │       │   ├── DeleteUserUseCase.php
│   │       │   ├── ListUserReservationsUseCase.php      # 🆕 À créer
│   │       │   ├── ListUserStatonnementsUseCase.php     # 🆕 À créer
│   │       │   └── SearchParkingsUseCase.php            # 🆕 À créer
│   │       │
│   │       ├── ParkingOwner/            # 🆕 À créer entièrement
│   │       │   ├── RegisterParkingOwnerUseCase.php
│   │       │   ├── CreateParkingUseCase.php
│   │       │   ├── UpdateParkingTarifsUseCase.php
│   │       │   ├── UpdateParkingHoursUseCase.php
│   │       │   ├── ListParkingReservationsUseCase.php
│   │       │   ├── ListParkingStatonnementsUseCase.php
│   │       │   ├── GetAvailablePlacesUseCase.php
│   │       │   ├── CalculateMonthlyRevenueUseCase.php
│   │       │   ├── AddAbonnementTypeUseCase.php
│   │       │   └── ListViolationsUseCase.php
│   │       │
│   │       ├── Reservation/             # 
│   │       │   ├── CreateReservationUseCase.php
│   │       │   ├── GetReservationUseCase.php
│   │       │   ├── CancelReservationUseCase.php
│   │       │   ├── ListReservationsForUserUseCase.php
│   │       │   └── GenerateInvoiceUseCase.php       # 🆕 À créer
│   │       │
│   │       ├── Stationnement/           # 🆕 À créer 
│   │       │   ├── EnterParkingUseCase.php
│   │       │   ├── ExitParkingUseCase.php
│   │       │   └── GetStationnementUseCase.php
│   │       │
│   │       └── Abonnement/              # ✅ Partiellement implémenté
│   │           ├── CreateAbonnementUseCase.php       # ✅ Implémenté
│   │           ├── SubscribeToAbonnementUseCase.php   # ✅ Implémenté
│   │           ├── ValidateAbonnementUseCase.php      # ✅ Implémenté
│   │           └── ListAbonnementsForParkingUseCase.php # ✅ Implémenté
│   │
│   ├── Infrastructure/                  # 🟡 COUCHE INFRASTRUCTURE
│   │   ├── Persistence/
│   │   │   ├── Sql/                     # PostgreSQL
│   │   │   │   ├── UserRepository.php   # ✅ Existe
│   │   │   │   ├── ParkingOwnerRepository.php       # 🆕 À créer
│   │   │   │   ├── ParkingRepository.php            # 🆕 À créer
│   │   │   │   ├── ReservationRepository.php        # ✅ Existe
│   │   │   │   ├── StationnementRepository.php      # 🆕 À créer
│   │   │   │   └── AbonnementRepository.php         # ✅ Implémenté (SQL + Mongo time_slots)
│   │   │   │
│   │   │   └── NoSql/                   # MongoDB (ou JSON files)
│   │   │       ├── MongoDBConnection.php
│   │   │       ├── PricingGridRepository.php        # Grilles tarifaires flexibles
│   │   │       ├── TimeSlotRepository.php           # Créneaux horaires abonnements
│   │   │       └── InvoiceRepository.php            # Factures détaillées
│   │   │
│   │   └── Services/
│   │       ├── JwtService.php           # ✅ Existe
│   │       ├── PasswordHasher.php       # 🆕 À créer (hash PHP vanilla)
│   │       └── PDFGenerator.php         # 🆕 À créer (factures PDF)
│   │
│   └── Presenter/                       # 🔴 COUCHE PRÉSENTATION
│       ├── Http/                        # Interface Web HTML
│       │   ├── Controllers/
│       │   │   ├── Api/
│       │   │   │   ├── AuthController.php # ✅ Existe
│       │   │   │   ├── UserController.php
│       │   │   │   ├── ParkingController.php         # 🆕 À créer
│       │   │   │   ├── ReservationController.php     # 🆕 À créer
│       │   │   │   ├── StationnementController.php   # 🆕 À créer
│       │   │   │   └── AbonnementController.php      # ✅ Implémenté
│       │   │   └── Web/                # 🆕 À séparer des contrôleurs API
│       │   │       ├── HomeController.php
│       │   │       ├── AuthWebController.php
│       │   │       ├── DashboardController.php
│       │   │       └── ParkingWebController.php
│       │   │
│       │   ├── Middleware/
│       │   │   ├── AuthenticationMiddleware.php # ✅ Existe
│       │   │   ├── CSRFMiddleware.php           # 🆕 À créer
│       │   │   └── CORSMiddleware.php           # 🆕 À créer
│       │   │
│       │   └── Views/                   # 🆕 À créer (vues HTML)
│       │       ├── layouts/
│       │       │   ├── header.php
│       │       │   └── footer.php
│       │       ├── auth/
│       │       │   ├── login.php
│       │       │   └── register.php
│       │       └── dashboard/
│       │           ├── user.php
│       │           └── owner.php
│       │
│       └── router.php                   # 🆕 Router unique
│
├── tests/                               # ✅ Structure existante OK
│   ├── unit/
│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   ├── ValueObjects/
│   │   │   └── Services/
│   │   └── User/                        # ✅ Tests User déjà présents
│   │
│   ├── integration/
│   │   └── User/                        # ✅ Tests User déjà présents
│   │
│   └── functional/
│       ├── SmokeTest.php                # ✅ Existe
│       └── User/                        # ✅ Tests User déjà présents
│
├── config/
│   ├── database.php                     # ✅ Existe
│   ├── mongodb.php                      
│   ├── container.php                    # 🆕 À créer (DI)
│   └── routes.php                       # 🆕 À créer
│
├── docker/                              
│   ├── php/
│   ├── postgres/
│   │   ├── init.sql                     
│   │   └── migrations/
│   └── mongodb/
│       └── init-mongo.js
│
└── public/
    ├── index.php                        # Point d'entrée unique
    ├── css/
    └── js/
```

---

