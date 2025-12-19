<?php

namespace App\Application\UseCases\Reservation;

use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\PricingService;
use App\Domain\Exceptions\EntityNotFoundException;

class GenerateInvoiceUseCase
{
    private ReservationRepositoryInterface $reservationRepository;
    private StationnementRepositoryInterface $stationnementRepository;
    private UserRepositoryInterface $userRepository;
    private PricingService $pricingService;

    public function __construct(
        ReservationRepositoryInterface $reservationRepository,
        StationnementRepositoryInterface $stationnementRepository,
        UserRepositoryInterface $userRepository,
        PricingService $pricingService
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->stationnementRepository = $stationnementRepository;
        $this->userRepository = $userRepository;
        $this->pricingService = $pricingService;
    }

    public function execute(string $reservationId, string $userId): array
    {
        // Récupérer la réservation
        $reservation = $this->reservationRepository->findById($reservationId);
        if ($reservation === null) {
            throw new EntityNotFoundException('Réservation non trouvée');
        }

        // Vérifier que la réservation appartient à l'utilisateur
        if ($reservation->getUserId() !== $userId) {
            throw new EntityNotFoundException('Réservation non trouvée');
        }

        // Récupérer l'utilisateur
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('Utilisateur non trouvé');
        }

        // Récupérer le stationnement associé s'il existe
        $stationnement = $this->stationnementRepository->findByReservationId($reservationId);

        // Calculer les détails de facturation
        $invoiceData = $this->calculateInvoiceDetails($reservation, $stationnement);

        // Générer le numéro de facture
        $invoiceNumber = $this->generateInvoiceNumber($reservation);

        // Construire la facture complète
        return [
            'invoice_number' => $invoiceNumber,
            'date' => new \DateTime(),
            'reservation' => [
                'id' => $reservation->getId(),
                'parking_id' => $reservation->getParkingId(),
                'start_time' => $reservation->getStartTime(),
                'end_time' => $reservation->getEndTime(),
                'status' => $reservation->getStatus(),
                'duration_minutes' => $this->calculateDurationMinutes($reservation->getStartTime(), $reservation->getEndTime())
            ],
            'customer' => [
                'id' => $user->getId(),
                'name' => $user->getNom() . ' ' . $user->getPrenom(),
                'email' => $user->getEmail()->getEmail()
            ],
            'stationnement' => $stationnement ? [
                'entry_time' => $stationnement->getEntryTime(),
                'exit_time' => $stationnement->getExitTime(),
                'actual_duration_minutes' => $stationnement->getExitTime() ? 
                    $this->calculateDurationMinutes($stationnement->getEntryTime(), $stationnement->getExitTime()) : null,
                'has_penalty' => $stationnement->getHasPenalty() ?? false
            ] : null,
            'billing_details' => $invoiceData,
            'totals' => [
                'subtotal' => $invoiceData['subtotal'],
                'penalty' => $invoiceData['penalty_amount'],
                'tax_rate' => $invoiceData['tax_rate'],
                'tax_amount' => $invoiceData['tax_amount'],
                'total_ttc' => $invoiceData['total_ttc']
            ],
            'payment' => [
                'status' => $reservation->getStatus() === 'completed' ? 'paid' : 'pending',
                'method' => 'credit_card', // À adapter selon le système de paiement
                'currency' => 'EUR'
            ],
            'legal_mentions' => $this->getLegalMentions()
        ];
    }

    private function calculateInvoiceDetails($reservation, $stationnement): array
    {
        $items = [];
        $subtotal = 0;
        $penaltyAmount = 0;

        // Ligne principale : réservation
        $reservationDuration = $this->calculateDurationMinutes($reservation->getStartTime(), $reservation->getEndTime());
        $reservationPrice = $reservation->getTotalPrice();
        
        $items[] = [
            'type' => 'reservation',
            'description' => "Réservation parking - {$reservationDuration} minutes",
            'start_time' => $reservation->getStartTime()->format('d/m/Y H:i'),
            'end_time' => $reservation->getEndTime()->format('d/m/Y H:i'),
            'duration_minutes' => $reservationDuration,
            'unit_price' => $reservationPrice / ($reservationDuration / 15), // Prix par tranche de 15min
            'quantity' => $reservationDuration / 15,
            'amount' => $reservationPrice
        ];
        $subtotal += $reservationPrice;

        // Si stationnement avec dépassement, ajouter la pénalité
        if ($stationnement && $stationnement->getHasPenalty()) {
            $penaltyAmount = $stationnement->getPenaltyAmount() ?? 20.0;
            
            $items[] = [
                'type' => 'penalty',
                'description' => 'Pénalité pour dépassement de créneau',
                'amount' => $penaltyAmount
            ];
        }

        // Si temps supplémentaire facturé
        if ($stationnement && $stationnement->getExitTime() && $stationnement->getPrice()) {
            $actualPrice = $stationnement->getPrice()->getAmount();
            $extraAmount = $actualPrice - $reservationPrice - $penaltyAmount;
            
            if ($extraAmount > 0) {
                $actualDuration = $this->calculateDurationMinutes($stationnement->getEntryTime(), $stationnement->getExitTime());
                $extraTime = $actualDuration - $reservationDuration;
                
                $items[] = [
                    'type' => 'extra_time',
                    'description' => "Temps supplémentaire - {$extraTime} minutes",
                    'duration_minutes' => $extraTime,
                    'amount' => $extraAmount
                ];
                $subtotal = $actualPrice - $penaltyAmount; // Recalculer le sous-total
            }
        }

        // Calcul TVA (20%)
        $taxRate = 0.20;
        $taxAmount = round(($subtotal + $penaltyAmount) * $taxRate, 2);
        $totalTTC = $subtotal + $penaltyAmount + $taxAmount;

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'penalty_amount' => $penaltyAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_ttc' => $totalTTC
        ];
    }

    private function calculateDurationMinutes(\DateTime $start, \DateTime $end): int
    {
        return intval(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    private function generateInvoiceNumber($reservation): string
    {
        $year = date('Y');
        $month = date('m');
        $reservationId = substr($reservation->getId(), -6); // Derniers 6 chars de l'ID
        
        return "FAC-{$year}{$month}-{$reservationId}";
    }

    private function getLegalMentions(): array
    {
        return [
            'company_name' => 'ParkingShare SAS',
            'address' => '123 Rue de la République, 75001 Paris',
            'siret' => '123 456 789 00012',
            'tva_number' => 'FR12345678901',
            'contact' => 'contact@parkingshare.fr',
            'mentions' => [
                'Facture émise conformément à l\'article 289 du CGI',
                'TVA sur les encaissements',
                'Paiement comptant - Aucun escompte accordé',
                'En cas de retard de paiement, pénalités de 3 fois le taux d\'intérêt légal'
            ]
        ];
    }
}