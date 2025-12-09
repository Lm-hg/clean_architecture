<?php

declare(strict_types=1);

namespace Infrastructure\NoSql;

use Infrastructure\NoSql\JsonFileService;

/**
 * Pricing Grid Repository - JSON Implementation
 * Handles flexible pricing data, tariffs, and pricing grids
 * Clean Architecture - Infrastructure Layer
 */
class PricingGridRepository
{
    private JsonFileService $jsonService;
    private string $collection = 'pricing_grids';

    public function __construct(JsonFileService $jsonService)
    {
        $this->jsonService = $jsonService;
    }

    /**
     * Save pricing grid for a parking
     */
    public function savePricingGrid(string $parkingId, array $pricingData): string
    {
        $document = [
            'parking_id' => $parkingId,
            'name' => $pricingData['name'] ?? 'Default Pricing Grid',
            'currency' => $pricingData['currency'] ?? 'EUR',
            'rates' => $pricingData['rates'] ?? $this->getDefaultRates(),
            'special_rates' => $pricingData['special_rates'] ?? [],
            'validFrom' => $pricingData['validFrom'] ?? date('c'),
            'validTo' => $pricingData['validTo'] ?? null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        return $this->jsonService->insertOne($this->collection, $document);
    }

    /**
     * Get pricing grid by parking ID
     */
    public function getPricingGridByParkingId(string $parkingId): ?array
    {
        $filter = ['parking_id' => $parkingId];
        $results = $this->jsonService->find($this->collection, $filter);
        
        // Filter for valid dates (simple implementation)
        $current = date('c');
        foreach ($results as $result) {
            if ($result['validTo'] === null || $result['validTo'] >= $current) {
                return $result;
            }
        }
        
        return null;
    }

    /**
     * Update pricing grid
     */
    public function updatePricingGrid(string $parkingId, array $pricingData): bool
    {
        $filter = ['parking_id' => $parkingId];
        $update = [
            'rates' => $pricingData['rates'] ?? $this->getDefaultRates(),
            'special_rates' => $pricingData['special_rates'] ?? [],
            'updated_at' => date('c'),
        ];

        return $this->jsonService->updateOne($this->collection, $filter, $update);
    }

    /**
     * Get all pricing grids
     */
    public function getAllPricingGrids(): array
    {
        return $this->jsonService->find($this->collection, []);
    }

    /**
     * Delete pricing grid
     */
    public function deletePricingGrid(string $parkingId): bool
    {
        return $this->jsonService->deleteOne($this->collection, ['parking_id' => $parkingId]);
    }

    /**
     * Calculate parking cost based on duration
     */
    public function calculateCost(string $parkingId, int $durationMinutes): float
    {
        $pricingGrid = $this->getPricingGridByParkingId($parkingId);
        if (!$pricingGrid) {
            // Fallback to default rates
            $rates = $this->getDefaultRates();
        } else {
            $rates = $pricingGrid['rates'];
        }

        return $this->calculateCostFromRates($rates, $durationMinutes);
    }

    /**
     * Get default pricing rates
     */
    private function getDefaultRates(): array
    {
        return [
            '15min' => 0.30,
            '30min' => 0.60,
            '1h' => 1.20,
            '2h' => 3.20,
            '4h' => 5.00,
            'day' => 12.00,
            'night' => 2.00,
        ];
    }

    /**
     * Calculate cost from rates and duration
     */
    private function calculateCostFromRates(array $rates, int $durationMinutes): float
    {
        $durationHours = $durationMinutes / 60.0;

        // Day rate (over 8 hours)
        if ($durationHours > 8) {
            return $rates['day'] ?? 12.00;
        }

        // Hourly calculations
        if ($durationHours >= 4) {
            return $rates['4h'] ?? 5.00;
        }

        if ($durationHours >= 2) {
            return $rates['2h'] ?? 3.20;
        }

        if ($durationHours >= 1) {
            return $rates['1h'] ?? 1.20;
        }

        if ($durationMinutes >= 30) {
            return $rates['30min'] ?? 0.60;
        }

        if ($durationMinutes >= 15) {
            return $rates['15min'] ?? 0.30;
        }

        // Minimum charge for less than 15 minutes
        return $rates['15min'] ?? 0.30;
    }
}