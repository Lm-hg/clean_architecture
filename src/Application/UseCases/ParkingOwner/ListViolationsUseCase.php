<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedAccessException;

class ListViolationsUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private AbonnementRepositoryInterface $abonnementRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository,
        AbonnementRepositoryInterface $abonnementRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
        $this->abonnementRepository = $abonnementRepository;
    }

    public function execute(string $parkingId, string $ownerId, ?\DateTime $checkTime = null): array
    {
        // Vérifier que le parking existe et appartient au propriétaire
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new EntityNotFoundException('Parking non trouvé');
        }

        if ($parking->getOwnerId() !== $ownerId) {
            throw new UnauthorizedAccessException('Vous n\'avez pas accès à ce parking');
        }

        $checkTime = $checkTime ?? new \DateTime();
        
        // Récupérer tous les stationnements actifs (sans sortie)
        $activeStationnements = $this->stationnementRepository->findActiveByParkingId($parkingId);

        $violations = [];
        $legitStationnements = [];

        foreach ($activeStationnements as $stationnement) {
            $userId = $stationnement->getUserId();
            $entryTime = $stationnement->getEntryTime();
            
            // Vérifier s'il y a une réservation valide
            $hasValidReservation = $this->hasValidReservation($userId, $parkingId, $entryTime, $checkTime);
            
            // Vérifier s'il y a un abonnement valide
            $hasValidAbonnement = $this->hasValidAbonnement($userId, $parkingId, $entryTime, $checkTime);

            if (!$hasValidReservation && !$hasValidAbonnement) {
                $violations[] = [
                    'stationnement' => $stationnement,
                    'user_id' => $userId,
                    'entry_time' => $entryTime,
                    'duration_minutes' => intval(($checkTime->getTimestamp() - $entryTime->getTimestamp()) / 60),
                    'violation_type' => 'no_valid_reservation_or_subscription',
                    'has_penalty' => $stationnement->getHasPenalty() ?? false,
                    'penalty_amount' => $stationnement->getPenaltyAmount() ?? 0
                ];
            } else {
                $legitStationnements[] = [
                    'stationnement' => $stationnement,
                    'user_id' => $userId,
                    'has_reservation' => $hasValidReservation,
                    'has_abonnement' => $hasValidAbonnement
                ];
            }
        }

        // Trier les violations par durée décroissante (plus problématique en premier)
        usort($violations, function ($a, $b) {
            return $b['duration_minutes'] <=> $a['duration_minutes'];
        });

        return [
            'parking_id' => $parkingId,
            'parking_title' => $parking->getTitle(),
            'check_time' => $checkTime,
            'statistics' => [
                'total_active_stationnements' => count($activeStationnements),
                'violations_count' => count($violations),
                'legitimate_stationnements' => count($legitStationnements),
                'violation_rate' => count($activeStationnements) > 0 ? 
                    round(count($violations) / count($activeStationnements) * 100, 2) : 0
            ],
            'violations' => $violations,
            'legitimate_stationnements' => $legitStationnements
        ];
    }

    private function hasValidReservation(string $userId, string $parkingId, \DateTime $entryTime, \DateTime $checkTime): bool
    {
        // Récupérer les réservations actives de l'utilisateur pour ce parking
        $reservations = $this->reservationRepository->findActiveByUserAndParking($userId, $parkingId, $checkTime);

        foreach ($reservations as $reservation) {
            $startTime = $reservation->getStartTime();
            $endTime = $reservation->getEndTime();

            // Vérifier si l'entrée et le moment de vérification sont dans la période de réservation
            if ($entryTime >= $startTime && $entryTime <= $endTime && $checkTime <= $endTime) {
                return true;
            }
        }

        return false;
    }

    private function hasValidAbonnement(string $userId, string $parkingId, \DateTime $entryTime, \DateTime $checkTime): bool
    {
        // Récupérer les abonnements actifs de l'utilisateur pour ce parking
        $abonnements = $this->abonnementRepository->findActiveByUserAndParking($userId, $parkingId, $checkTime);

        foreach ($abonnements as $abonnement) {
            // Vérifier si l'abonnement couvre la période actuelle
            if ($this->isTimeSlotValid($abonnement, $entryTime, $checkTime)) {
                return true;
            }
        }

        return false;
    }

    private function isTimeSlotValid($abonnement, \DateTime $entryTime, \DateTime $checkTime): bool
    {
        $timeSlots = $abonnement->getTimeSlots();

        // Si pas de créneaux définis, l'abonnement est valide H24
        if (empty($timeSlots)) {
            return true;
        }

        // Vérifier si l'entrée et le moment de check sont dans un créneau valide
        foreach ($timeSlots as $slot) {
            if ($this->isTimeInSlot($entryTime, $slot) && $this->isTimeInSlot($checkTime, $slot)) {
                return true;
            }
        }

        return false;
    }

    private function isTimeInSlot(\DateTime $time, $timeSlot): bool
    {
        $dayOfWeek = (int)$time->format('N'); // 1 = Lundi, 7 = Dimanche
        $timeStr = $time->format('H:i');

        return $timeSlot->getDayOfWeek() === $dayOfWeek &&
               $timeStr >= $timeSlot->getStartTime() &&
               $timeStr <= $timeSlot->getEndTime();
    }
}