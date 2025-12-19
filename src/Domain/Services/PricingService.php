<?php
declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Entities\Parking;
use App\Domain\Entities\Stationnement;
use App\Domain\ValueObjects\Price\Price;
use App\Domain\ValueObjects\Pricing\TarifCollection;

/**
 * Service de calcul de prix pour les stationnements
 * Gère le calcul par tranches de 15 minutes selon les grilles tarifaires
 */
class PricingService
{
    /**
     * Calcule le prix d'un stationnement selon la durée et les tarifs du parking
     * 
     * @param Stationnement $stationnement
     * @param Parking $parking
     * @param \DateTime $exitTime
     * @return Price
     */
    public function calculateParkingPrice(
        Stationnement $stationnement,
        Parking $parking,
        \DateTime $exitTime
    ): Price {
        error_log("PricingService: Calculating price for parking " . $parking->getId());
        
        // Si l'utilisateur a un abonnement valide, le prix est gratuit
        if ($stationnement->hasAbonnement()) {
            error_log("PricingService: Has abonnement, price = 0");
            return Price::fromFloat(0.0, 'EUR');
        }

        // Calculer la durée en minutes
        $duration = $exitTime->getTimestamp() - $stationnement->getEntryTime()->getTimestamp();
        $durationInMinutes = (int)($duration / 60);
        error_log("PricingService: Duration = $durationInMinutes minutes");

        // Calculer le nombre de tranches de 15 minutes (arrondi au supérieur)
        // Minimum 1 tranche même si la durée est inférieure à 15 minutes
        $quarters = max(1, (int)ceil($durationInMinutes / 15));
        error_log("PricingService: Quarters = $quarters");

        // Récupérer les tarifs du parking
        $tarifs = $parking->getTarifs();
        error_log("PricingService: Tarifs count = " . count($tarifs->all()));

        // Calculer le prix total en parcourant chaque tranche de 15min
        $totalPrice = 0.0;
        $currentTime = clone $stationnement->getEntryTime();

        for ($i = 0; $i < $quarters; $i++) {
            // Récupérer le tarif applicable pour cette tranche
            $applicablePrice = $tarifs->getApplicablePrice($currentTime);
            
            if ($applicablePrice === null) {
                error_log("PricingService: No applicable price found, using first tarif");
                // Si aucun tarif applicable, utiliser le premier tarif disponible
                $allTarifs = $tarifs->all();
                if (!empty($allTarifs)) {
                    $applicablePrice = $allTarifs[0]->getPrice();
                    error_log("PricingService: First tarif price = " . $applicablePrice->getAmount());
                } else {
                    // Prix par défaut si pas de tarif
                    $applicablePrice = Price::fromFloat(1.0, 'EUR');
                    error_log("PricingService: Using default price 1.0");
                }
            } else {
                error_log("PricingService: Applicable price = " . $applicablePrice->getAmount());
            }

            // Prix pour une tranche de 15min = prix par heure / 4
            $pricePerQuarter = $applicablePrice->getAmount() / 4;
            $totalPrice += $pricePerQuarter;

            // Avancer de 15 minutes pour la prochaine tranche
            $currentTime->modify('+15 minutes');
        }

        error_log("PricingService: Total price = $totalPrice");
        return Price::fromFloat($totalPrice, 'EUR');
    }

    /**
     * Calcule le prix d'une réservation selon sa durée
     * 
     * @param Parking $parking
     * @param \DateTime $startTime
     * @param \DateTime $endTime
     * @return Price
     */
    public function calculateReservationPrice(
        Parking $parking,
        \DateTime $startTime,
        \DateTime $endTime
    ): Price {
        $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
        $durationInMinutes = (int)($duration / 60);
        $quarters = (int)ceil($durationInMinutes / 15);

        $tarifs = $parking->getTarifs();
        $totalPrice = 0.0;
        $currentTime = clone $startTime;

        for ($i = 0; $i < $quarters; $i++) {
            $applicablePrice = $tarifs->getApplicablePrice($currentTime);
            
            if ($applicablePrice === null) {
                $allTarifs = $tarifs->all();
                if (!empty($allTarifs)) {
                    $applicablePrice = $allTarifs[0]->getPrice();
                } else {
                    $applicablePrice = Price::fromFloat(1.0, 'EUR');
                }
            }

            $pricePerQuarter = $applicablePrice->getAmount() / 4;
            $totalPrice += $pricePerQuarter;

            $currentTime->modify('+15 minutes');
        }

        return Price::fromFloat($totalPrice, 'EUR');
    }
}

