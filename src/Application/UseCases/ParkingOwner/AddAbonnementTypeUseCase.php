<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Entities\Abonnement;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\ValueObjects\TimeSlot;

class AddAbonnementTypeUseCase
{
    private AbonnementRepositoryInterface $abonnementRepository;
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(
        AbonnementRepositoryInterface $abonnementRepository,
        ParkingRepositoryInterface $parkingRepository
    ) {
        $this->abonnementRepository = $abonnementRepository;
        $this->parkingRepository = $parkingRepository;
    }

    public function execute(string $parkingId, string $ownerId, array $abonnementData): Abonnement
    {
        // Vérifier que le parking existe et appartient au propriétaire
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new EntityNotFoundException('Parking non trouvé');
        }

        if ($parking->getOwnerId() !== $ownerId) {
            throw new UnauthorizedAccessException('Vous n\'avez pas accès à ce parking');
        }

        // Validation des données d'abonnement
        $this->validateAbonnementData($abonnementData);

        // Créer les créneaux horaires
        $timeSlots = [];
        if (isset($abonnementData['time_slots'])) {
            foreach ($abonnementData['time_slots'] as $slotData) {
                $timeSlots[] = TimeSlot::fromHm(
                    $slotData['day_of_week'],
                    $slotData['start_time'],
                    $slotData['end_time']
                );
            }
        }

        // Déterminer les dates de début et fin par défaut
        $now = new \DateTime();
        $startDate = isset($abonnementData['start_date']) ? 
            new \DateTime($abonnementData['start_date']) : $now;
        $endDate = isset($abonnementData['end_date']) ? 
            new \DateTime($abonnementData['end_date']) : 
            (clone $startDate)->modify('+1 month');

        // Créer l'abonnement type (template pour les utilisateurs)
        $abonnement = new Abonnement(
            userId: 'template', // ID spécial pour les templates
            parkingId: $parkingId,
            type: $abonnementData['type'],
            startDate: $startDate,
            endDate: $endDate,
            monthlyPrice: (float)$abonnementData['monthly_price'],
            timeSlots: $timeSlots
        );

        // Marquer comme template dans les métadonnées si supporté
        if (method_exists($abonnement, 'setIsTemplate')) {
            $abonnement->setIsTemplate(true);
        }

        // Sauvegarder l'abonnement template
        $savedAbonnement = $this->abonnementRepository->save($abonnement);

        if ($savedAbonnement === null) {
            throw new \RuntimeException('Erreur lors de la création du type d\'abonnement');
        }

        return $savedAbonnement;
    }

    private function validateAbonnementData(array $data): void
    {
        if (!isset($data['type']) || empty(trim($data['type']))) {
            throw new \InvalidArgumentException('Le type d\'abonnement est requis');
        }

        if (!isset($data['monthly_price']) || $data['monthly_price'] <= 0) {
            throw new \InvalidArgumentException('Le prix mensuel doit être supérieur à 0');
        }

        // Validation des créneaux horaires si fournis
        if (isset($data['time_slots']) && is_array($data['time_slots'])) {
            foreach ($data['time_slots'] as $slot) {
                if (!isset($slot['day_of_week']) || $slot['day_of_week'] < 1 || $slot['day_of_week'] > 7) {
                    throw new \InvalidArgumentException('Jour de la semaine invalide (1-7)');
                }
                
                if (!isset($slot['start_time']) || !isset($slot['end_time'])) {
                    throw new \InvalidArgumentException('Heures de début et fin requises pour chaque créneau');
                }

                // Validation du format d'heure (HH:MM)
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $slot['start_time']) ||
                    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $slot['end_time'])) {
                    throw new \InvalidArgumentException('Format d\'heure invalide (HH:MM attendu)');
                }
            }
        }

        // Validation des dates si fournies
        if (isset($data['start_date'])) {
            try {
                new \DateTime($data['start_date']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Format de date de début invalide');
            }
        }

        if (isset($data['end_date'])) {
            try {
                $endDate = new \DateTime($data['end_date']);
                if (isset($data['start_date'])) {
                    $startDate = new \DateTime($data['start_date']);
                    if ($endDate <= $startDate) {
                        throw new \InvalidArgumentException('La date de fin doit être après la date de début');
                    }
                }
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Format de date de fin invalide');
            }
        }
    }
}