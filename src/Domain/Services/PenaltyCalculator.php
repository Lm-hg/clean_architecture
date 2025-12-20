<?php
declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Entities\Stationnement;
use App\Domain\Entities\Reservation;
use App\Domain\Entities\Abonnement;

/**
 * Service de calcul des pénalités pour les stationnements
 */
class PenaltyCalculator
{
    public const DEFAULT_PENALTY_AMOUNT = 20.0;
    public const PENALTY_PER_HOUR_OVERRUN = 5.0; // 5€ par heure de dépassement

    /**
     * Calcule la pénalité pour une entrée tardive (arrivée après la fin de la réservation)
     * 
     * @param Stationnement $stationnement
     * @param Reservation $reservation
     * @return float Montant de la pénalité
     */
    public function calculateLateEntryPenalty(
        Stationnement $stationnement,
        Reservation $reservation
    ): float {
        $entryTime = $stationnement->getEntryTime();
        $reservationEnd = $reservation->getEndTime();
        
        // Si l'entrée est APRÈS la fin de la réservation, c'est une entrée tardive
        if ($entryTime > $reservationEnd) {
            // Pénalité de base pour entrée tardive
            return self::DEFAULT_PENALTY_AMOUNT;
        }
        
        return 0.0;
    }

    /**
     * Calcule le montant de la pénalité pour un stationnement qui dépasse une réservation
     * 
     * @param Stationnement $stationnement
     * @param Reservation $reservation
     * @return float Montant de la pénalité
     */
    public function calculateReservationOverrunPenalty(
        Stationnement $stationnement,
        Reservation $reservation
    ): float {
        if ($stationnement->getExitTime() === null) {
            return 0.0;
        }

        if (!$stationnement->exceedsReservation($reservation->getEndTime())) {
            return 0.0;
        }

        // Calculer le nombre d'heures de dépassement
        $overrunSeconds = $stationnement->getExitTime()->getTimestamp() - $reservation->getEndTime()->getTimestamp();
        $overrunHours = (int)ceil($overrunSeconds / 3600); // Arrondi au supérieur

        // Pénalité de base + pénalité par heure de dépassement
        return self::DEFAULT_PENALTY_AMOUNT + ($overrunHours * self::PENALTY_PER_HOUR_OVERRUN);
    }

    /**
     * Calcule le montant de la pénalité pour un stationnement qui dépasse un créneau d'abonnement
     * 
     * @param Stationnement $stationnement
     * @param Abonnement $abonnement
     * @param \DateTime $slotEndTime Fin du créneau autorisé par l'abonnement
     * @return float Montant de la pénalité
     */
    public function calculateAbonnementOverrunPenalty(
        Stationnement $stationnement,
        Abonnement $abonnement,
        \DateTime $slotEndTime
    ): float {
        if ($stationnement->getExitTime() === null) {
            return 0.0;
        }

        if (!$stationnement->exceedsAbonnementSlot($slotEndTime)) {
            return 0.0;
        }

        // Calculer le nombre d'heures de dépassement
        $overrunSeconds = $stationnement->getExitTime()->getTimestamp() - $slotEndTime->getTimestamp();
        $overrunHours = (int)ceil($overrunSeconds / 3600);

        // Pénalité réduite pour les abonnements (10€ de base au lieu de 20€)
        $basePenalty = 10.0;
        return $basePenalty + ($overrunHours * self::PENALTY_PER_HOUR_OVERRUN);
    }

    /**
     * Vérifie si une pénalité doit être appliquée et retourne le montant
     * 
     * @param Stationnement $stationnement
     * @param Reservation|null $reservation
     * @param Abonnement|null $abonnement
     * @param \DateTime|null $abonnementSlotEndTime
     * @return float Montant de la pénalité (0 si aucune pénalité)
     */
    public function calculatePenalty(
        Stationnement $stationnement,
        ?Reservation $reservation = null,
        ?Abonnement $abonnement = null,
        ?\DateTime $abonnementSlotEndTime = null
    ): float {
        $totalPenalty = 0.0;
        
        // Priorité à la réservation si les deux existent
        if ($reservation !== null && $stationnement->hasReservation()) {
            // Vérifier d'abord l'entrée tardive
            $lateEntryPenalty = $this->calculateLateEntryPenalty($stationnement, $reservation);
            $totalPenalty += $lateEntryPenalty;
            
            // Puis vérifier le dépassement à la sortie
            $overrunPenalty = $this->calculateReservationOverrunPenalty($stationnement, $reservation);
            $totalPenalty += $overrunPenalty;
            
            return $totalPenalty;
        }

        if ($abonnement !== null && $stationnement->hasAbonnement() && $abonnementSlotEndTime !== null) {
            return $this->calculateAbonnementOverrunPenalty($stationnement, $abonnement, $abonnementSlotEndTime);
        }

        return 0.0;
    }
}

