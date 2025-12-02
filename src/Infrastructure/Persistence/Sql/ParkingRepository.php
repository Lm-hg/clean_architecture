<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\Parking;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;
use App\Domain\ValueObjects\Pricing\Tarif;

class ParkingRepository implements ParkingRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Parking
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM parkings 
            WHERE id = :id AND deleted_at IS NULL
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByOwnerId(string $ownerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM parkings 
            WHERE owner_id = :owner_id AND deleted_at IS NULL
            ORDER BY created_at DESC
        ');
        $stmt->execute(['owner_id' => $ownerId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM parkings 
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findAvailableParkings(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM parkings 
            WHERE is_available = true 
            AND available_spots > 0
            AND deleted_at IS NULL
            ORDER BY created_at DESC
        ');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByLocation(float $lat, float $lng, float $radius): array
    {
        // Utilisation de la formule Haversine pour calculer la distance en km
        // Radius est en kilomètres
        // Utilisation d'une sous-requête pour pouvoir filtrer par distance
        $sql = '
            SELECT * FROM (
                SELECT *, 
                (6371 * acos(
                    cos(radians(:lat)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(:lng)) + 
                    sin(radians(:lat)) * 
                    sin(radians(latitude))
                )) AS distance
                FROM parkings
                WHERE deleted_at IS NULL
            ) AS parkings_with_distance
            WHERE distance <= :radius
            ORDER BY distance ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countAvailableSpots(string $parkingId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT available_spots FROM parkings 
            WHERE id = :id AND deleted_at IS NULL
        ');
        $stmt->execute(['id' => $parkingId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int)$row['available_spots'] : 0;
    }

    public function create(Parking $parking): ?Parking
    {
        return $this->save($parking);
    }

    public function update(Parking $parking): ?Parking
    {
        return $this->save($parking);
    }

    public function save(Parking $parking): ?Parking
    {
        if ($parking->getId() === null) {
            return $this->insert($parking);
        } else {
            return $this->updateEntity($parking);
        }
    }

    private function insert(Parking $parking): Parking
    {
        $id = $this->generateUuidV4();
        $address = $parking->getAddress();
        $coordinates = $parking->getCoordinates();
        $openingHours = $parking->getOpeningHours();
        
        // Convertir OpeningHours en JSON
        $openingHoursJson = $this->openingHoursToJson($openingHours);
        
        // Pour les tarifs, on utilise le prix de base pour l'instant
        // En production, on devrait récupérer depuis MongoDB via pricing_grid_id
        $tarifs = $parking->getTarifs();
        $allTarifs = $tarifs->all();
        $basePrice = !empty($allTarifs) ? $allTarifs[0]->getPrice()->getAmount() : 0.0;

        $stmt = $this->pdo->prepare('
            INSERT INTO parkings (
                id, owner_id, title, description, 
                address, city, postal_code, 
                latitude, longitude, 
                price_per_hour, is_available, 
                total_spots, available_spots, 
                opening_hours, created_at, updated_at
            ) VALUES (
                :id, :owner_id, :title, :description,
                :address, :city, :postal_code,
                :latitude, :longitude,
                :price_per_hour, :is_available,
                :total_spots, :available_spots,
                :opening_hours, :created_at, :updated_at
            )
        ');

        $now = new \DateTime();
        $stmt->execute([
            'id' => $id,
            'owner_id' => $parking->getOwnerId(),
            'title' => $parking->getTitle(),
            'description' => $parking->getDescription(),
            'address' => $address->getStreet(),
            'city' => $address->getCity(),
            'postal_code' => $address->getPostalCode(),
            'latitude' => $coordinates->getLatitude(),
            'longitude' => $coordinates->getLongitude(),
            'price_per_hour' => $basePrice,
            'is_available' => $parking->getIsAvailable() ? 'true' : 'false',
            'total_spots' => $parking->getTotalSpots(),
            'available_spots' => $parking->getAvailableSpots(),
            'opening_hours' => json_encode($openingHoursJson),
            'created_at' => $parking->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $parking->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);

        // Reconstruire avec l'ID
        return new Parking(
            $parking->getOwnerId(),
            $parking->getTitle(),
            $parking->getAddress(),
            $parking->getCoordinates(),
            $parking->getTotalSpots(),
            $parking->getTarifs(),
            $parking->getOpeningHours(),
            $parking->getCreatedAt(),
            $parking->getUpdatedAt(),
            $parking->getDescription(),
            $id
        );
    }

    private function updateEntity(Parking $parking): Parking
    {
        $address = $parking->getAddress();
        $coordinates = $parking->getCoordinates();
        $openingHours = $parking->getOpeningHours();
        $openingHoursJson = $this->openingHoursToJson($openingHours);
        
        $tarifs = $parking->getTarifs();
        $basePrice = $tarifs->all()[0]->getPrice()->getAmount() ?? 0.0;

        $stmt = $this->pdo->prepare('
            UPDATE parkings SET
                title = :title,
                description = :description,
                address = :address,
                city = :city,
                postal_code = :postal_code,
                latitude = :latitude,
                longitude = :longitude,
                price_per_hour = :price_per_hour,
                is_available = :is_available,
                total_spots = :total_spots,
                available_spots = :available_spots,
                opening_hours = :opening_hours,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $parking->getId(),
            'title' => $parking->getTitle(),
            'description' => $parking->getDescription(),
            'address' => $address->getStreet(),
            'city' => $address->getCity(),
            'postal_code' => $address->getPostalCode(),
            'latitude' => $coordinates->getLatitude(),
            'longitude' => $coordinates->getLongitude(),
            'price_per_hour' => $basePrice,
            'is_available' => $parking->getIsAvailable() ? 'true' : 'false',
            'total_spots' => $parking->getTotalSpots(),
            'available_spots' => $parking->getAvailableSpots(),
            'opening_hours' => json_encode($openingHoursJson),
            'updated_at' => $parking->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);

        return $parking;
    }

    public function delete(string $id): bool
    {
        // Soft delete
        $stmt = $this->pdo->prepare('
            UPDATE parkings 
            SET deleted_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Parking
    {
        // Construire Address
        $address = new Address(
            $row['address'] ?? '',
            $row['city'] ?? '',
            $row['postal_code'] ?? '',
            'FR' // Default country
        );

        // Construire GPSCoordinates
        $coordinates = new GPSCoordinates(
            (float)$row['latitude'],
            (float)$row['longitude']
        );

        // Construire OpeningHours depuis JSON
        $openingHoursData = json_decode($row['opening_hours'] ?? '{}', true);
        $openingHours = $this->jsonToOpeningHours($openingHoursData);

        // Construire TarifCollection (simplifié - en production, récupérer depuis MongoDB)
        $tarifs = $this->createTarifCollectionFromPrice((float)$row['price_per_hour']);

        return new Parking(
            $row['owner_id'],
            $row['title'],
            $address,
            $coordinates,
            (int)$row['total_spots'],
            $tarifs,
            $openingHours,
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at']),
            $row['description'],
            $row['id']
        );
    }

    private function openingHoursToJson(OpeningHours $openingHours): array
    {
        $dayNames = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday'
        ];

        $result = [];
        $slots = $openingHours->getAllSlots();

        // Si aucun slot, retourner un format par défaut (fermé)
        if (empty($slots)) {
            foreach ($dayNames as $dayName) {
                $result[$dayName] = ['open' => '00:00', 'close' => '00:00'];
            }
            return $result;
        }

        // Grouper les slots par jour
        $slotsByDay = [];
        foreach ($slots as $slot) {
            $startDay = $slot->getStartDay();
            $endDay = $slot->getEndDay();
            
            if ($startDay <= $endDay) {
                for ($d = $startDay; $d <= $endDay; $d++) {
                    if (!isset($slotsByDay[$d])) {
                        $slotsByDay[$d] = [];
                    }
                    $slotsByDay[$d][] = $slot;
                }
            } else {
                // Wraps week boundary
                for ($d = $startDay; $d <= 7; $d++) {
                    if (!isset($slotsByDay[$d])) {
                        $slotsByDay[$d] = [];
                    }
                    $slotsByDay[$d][] = $slot;
                }
                for ($d = 1; $d <= $endDay; $d++) {
                    if (!isset($slotsByDay[$d])) {
                        $slotsByDay[$d] = [];
                    }
                    $slotsByDay[$d][] = $slot;
                }
            }
        }

        // Convertir chaque jour en format JSON
        // Le format JSON attendu est simplifié : un seul créneau par jour
        foreach ($dayNames as $dayNum => $dayName) {
            if (isset($slotsByDay[$dayNum]) && !empty($slotsByDay[$dayNum])) {
                // Trouver le slot qui commence ce jour ou qui est actif ce jour
                $bestSlot = null;
                $bestStartMinute = null;
                $bestEndMinute = null;
                
                foreach ($slotsByDay[$dayNum] as $slot) {
                    $slotStartDay = $slot->getStartDay();
                    $slotEndDay = $slot->getEndDay();
                    $slotStartMinute = $slot->getStartMinute();
                    $slotEndMinute = $slot->getEndMinute();
                    
                    if ($slotStartDay === $dayNum) {
                        // Le slot commence ce jour
                        if ($slotEndDay === $dayNum) {
                            // Slot qui commence et finit le même jour
                            $bestSlot = $slot;
                            $bestStartMinute = $slotStartMinute;
                            $bestEndMinute = $slotEndMinute;
                            break;
                        } else {
                            // Slot qui commence ce jour et finit un autre jour
                            $bestSlot = $slot;
                            $bestStartMinute = $slotStartMinute;
                            $bestEndMinute = 1439; // Fin de journée (23:59)
                        }
                    } elseif ($slotEndDay === $dayNum) {
                        // Le slot finit ce jour (commencé la veille)
                        if (!$bestSlot || $bestStartMinute === null) {
                            $bestSlot = $slot;
                            $bestStartMinute = 0; // Début de journée (00:00)
                            $bestEndMinute = $slotEndMinute;
                        }
                    } else {
                        // Le slot couvre ce jour mais ne commence ni ne finit ce jour
                        // (slot qui s'étend sur plusieurs jours)
                        if (!$bestSlot) {
                            $bestSlot = $slot;
                            $bestStartMinute = 0;
                            $bestEndMinute = 1439;
                        }
                    }
                }
                
                if ($bestSlot && $bestStartMinute !== null && $bestEndMinute !== null) {
                    $result[$dayName] = [
                        'open' => $this->minutesToTime($bestStartMinute),
                        'close' => $this->minutesToTime($bestEndMinute)
                    ];
                } else {
                    // Par défaut, jour ouvert 24/7 si un slot existe
                    $result[$dayName] = ['open' => '00:00', 'close' => '23:59'];
                }
            } else {
                // Jour fermé
                $result[$dayName] = ['open' => '00:00', 'close' => '00:00'];
            }
        }

        return $result;
    }

    private function jsonToOpeningHours(array $data): OpeningHours
    {
        $dayNames = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];

        $timeSlots = [];

        foreach ($dayNames as $dayName => $dayNum) {
            if (isset($data[$dayName]) && is_array($data[$dayName])) {
                $openTime = $data[$dayName]['open'] ?? '00:00';
                $closeTime = $data[$dayName]['close'] ?? '00:00';
                
                // Ignorer les jours fermés (00:00 - 00:00)
                if ($openTime === '00:00' && $closeTime === '00:00') {
                    continue;
                }

                $startMinute = $this->timeToMinutes($openTime);
                $endMinute = $this->timeToMinutes($closeTime);

                // Si closeTime est avant openTime, le slot s'étend jusqu'au lendemain
                if ($endMinute < $startMinute) {
                    // Slot qui s'étend jusqu'au jour suivant
                    $endDay = ($dayNum % 7) + 1;
                    $timeSlots[] = new \App\Domain\ValueObjects\TimeSlot(
                        $dayNum,
                        $startMinute,
                        $endDay,
                        $endMinute
                    );
                } else {
                    // Slot normal (même jour)
                    $timeSlots[] = new \App\Domain\ValueObjects\TimeSlot(
                        $dayNum,
                        $startMinute,
                        $dayNum,
                        $endMinute
                    );
                }
            }
        }

        return new OpeningHours($timeSlots);
    }

    /**
     * Convertit des minutes (0-1439) en format "HH:MM"
     */
    private function minutesToTime(int $minutes): string
    {
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Convertit un format "HH:MM" en minutes (0-1439)
     */
    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }

    private function createTarifCollectionFromPrice(float $pricePerHour): TarifCollection
    {
        // Créer un tarif basique depuis le prix par heure stocké en SQL
        // 
        // NOTE: En production, les tarifs complexes (tranches de 15min, tarifs spéciaux week-end, etc.)
        // devraient être récupérés depuis MongoDB via le champ `pricing_grid_id`.
        // Cette méthode est un fallback pour les parkings qui n'ont pas encore de grille tarifaire MongoDB.
        //
        // Pour une implémentation complète, il faudrait :
        // 1. Vérifier si `pricing_grid_id` existe dans la row
        // 2. Si oui, récupérer le document depuis MongoDB
        // 3. Parser le document et créer les Tarif correspondants avec leurs TimeSlot
        // 4. Sinon, utiliser ce fallback
        
        if ($pricePerHour <= 0) {
            // Prix invalide, créer un tarif par défaut
            $pricePerHour = 1.0;
        }
        
        $price = \App\Domain\ValueObjects\Price\Price::fromFloat($pricePerHour, 'EUR');
        $tarif = new Tarif(
            'Tarif standard (par heure)',
            $price,
            null // Pas de créneau spécifique, applicable tout le temps
        );
        
        return new TarifCollection([$tarif]);
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

