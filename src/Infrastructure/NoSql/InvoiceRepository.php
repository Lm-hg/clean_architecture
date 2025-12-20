<?php

declare(strict_types=1);

namespace Infrastructure\NoSql;

use Infrastructure\NoSql\JsonFileService;

/**
 * Invoice Repository - JSON Implementation
 * Handles detailed invoices and billing documents
 * Clean Architecture - Infrastructure Layer
 */
class InvoiceRepository
{
    private JsonFileService $jsonService;
    private string $collection = 'invoices';

    public function __construct(JsonFileService $jsonService)
    {
        $this->jsonService = $jsonService;
    }

    /**
     * Create new invoice
     */
    public function createInvoice(array $invoiceData): string
    {
        $document = [
            'invoice_id' => $invoiceData['invoice_id'] ?? $this->generateInvoiceId(),
            'user_id' => $invoiceData['user_id'],
            'invoice_number' => $invoiceData['invoice_number'] ?? $this->generateInvoiceNumber(),
            'date' => $invoiceData['date'] ?? date('c'),
            'items' => $invoiceData['items'] ?? [],
            'subtotal' => $invoiceData['subtotal'] ?? 0.0,
            'tax_rate' => $invoiceData['tax_rate'] ?? 0.20,
            'tax_amount' => $invoiceData['tax_amount'] ?? 0.0,
            'total' => $invoiceData['total'] ?? 0.0,
            'payment_status' => $invoiceData['payment_status'] ?? 'pending',
            'payment_method' => $invoiceData['payment_method'] ?? null,
            'pdf_path' => $invoiceData['pdf_path'] ?? null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        return $this->jsonService->insertOne($this->collection, $document);
    }

    /**
     * Get invoice by ID
     */
    public function getInvoiceById(string $invoiceId): ?array
    {
        return $this->jsonService->findOne($this->collection, ['invoice_id' => $invoiceId]);
    }

    /**
     * Get invoice by invoice number
     */
    public function getInvoiceByNumber(string $invoiceNumber): ?array
    {
        return $this->jsonService->findOne($this->collection, ['invoice_number' => $invoiceNumber]);
    }

    /**
     * Get invoices for user
     */
    public function getInvoicesForUser(string $userId): array
    {
        return $this->jsonService->find($this->collection, ['user_id' => $userId]);
    }

    /**
     * Update invoice status
     */
    public function updateInvoiceStatus(string $invoiceId, string $status): bool
    {
        $filter = ['invoice_id' => $invoiceId];
        $update = [
            'payment_status' => $status,
            'updated_at' => date('c'),
        ];

        return $this->jsonService->updateOne($this->collection, $filter, $update);
    }

    /**
     * Update invoice PDF path
     */
    public function updateInvoicePdfPath(string $invoiceId, string $pdfPath): bool
    {
        $filter = ['invoice_id' => $invoiceId];
        $update = [
            'pdf_path' => $pdfPath,
            'updated_at' => date('c'),
        ];

        return $this->jsonService->updateOne($this->collection, $filter, $update);
    }

    /**
     * Get all invoices with filters
     */
    public function getInvoices(array $filters = []): array
    {
        return $this->jsonService->find($this->collection, $filters);
    }

    /**
     * Get invoices by date range
     */
    public function getInvoicesByDateRange(string $startDate, string $endDate): array
    {
        $allInvoices = $this->jsonService->find($this->collection, []);
        
        return array_filter($allInvoices, function($invoice) use ($startDate, $endDate) {
            $invoiceDate = $invoice['date'];
            return $invoiceDate >= $startDate && $invoiceDate <= $endDate;
        });
    }

    /**
     * Get invoices by payment status
     */
    public function getInvoicesByStatus(string $status): array
    {
        return $this->jsonService->find($this->collection, ['payment_status' => $status]);
    }

    /**
     * Calculate total revenue
     */
    public function calculateTotalRevenue(array $filters = []): float
    {
        $invoices = $this->getInvoices($filters);
        $total = 0.0;
        
        foreach ($invoices as $invoice) {
            if ($invoice['payment_status'] === 'paid') {
                $total += (float) $invoice['total'];
            }
        }
        
        return $total;
    }

    /**
     * Generate unique invoice ID
     */
    private function generateInvoiceId(): string
    {
        return 'inv_' . uniqid('', true);
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $allInvoices = $this->jsonService->find($this->collection, []);
        $currentYearInvoices = array_filter($allInvoices, function($invoice) use ($year) {
            return strpos($invoice['invoice_number'], "INV-{$year}-") === 0;
        });
        
        $nextNumber = count($currentYearInvoices) + 1;
        return sprintf('INV-%s-%06d', $year, $nextNumber);
    }
}