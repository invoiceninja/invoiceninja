<?php

namespace App\Services\EDocument\Standards\Validation\Verifactu;

use App\Services\EDocument\Standards\Verifactu\Models\Invoice;
use InvalidArgumentException;

class InvoiceValidator
{
    /**
     * Validate an invoice against AEAT business rules
     */
    public function validate(Invoice $invoice): array
    {
        $errors = [];

        // Validate NIF format
        $errors = array_merge($errors, $this->validateNif($invoice));

        // Validate date formats
        $errors = array_merge($errors, $this->validateDates($invoice));

        // Validate invoice numbers
        $errors = array_merge($errors, $this->validateInvoiceNumbers($invoice));

        // Validate amounts
        $errors = array_merge($errors, $this->validateAmounts($invoice));

        // Validate tax rates
        $errors = array_merge($errors, $this->validateTaxRates($invoice));

        // Validate business logic
        $errors = array_merge($errors, $this->validateBusinessLogic($invoice));

        return $errors;
    }

    /**
     * Validate NIF format (Spanish tax identification)
     */
    private function validateNif(Invoice $invoice): array
    {
        $errors = [];

        // Check emitter NIF
        if ($invoice->getTercero() && $invoice->getTercero()->getNif()) {
            $nif = $invoice->getTercero()->getNif();
            if (!$this->isValidNif($nif)) {
                $errors[] = "Invalid emitter NIF format: {$nif}";
            }
        }

        // Check system NIF
        if ($invoice->getSistemaInformatico() && $invoice->getSistemaInformatico()->getNif()) {
            $nif = $invoice->getSistemaInformatico()->getNif();
            if (!$this->isValidNif($nif)) {
                $errors[] = "Invalid system NIF format: {$nif}";
            }
        }

        return $errors;
    }

    /**
     * Validate date formats
     */
    private function validateDates(Invoice $invoice): array
    {
        $errors = [];

        // Validate FechaHoraHusoGenRegistro format (YYYY-MM-DDTHH:MM:SS+HH:MM)
        $fechaHora = $invoice->getFechaHoraHusoGenRegistro();
        if ($fechaHora && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $fechaHora)) {
            $errors[] = "Invalid FechaHoraHusoGenRegistro format. Expected: YYYY-MM-DDTHH:MM:SS+HH:MM, Got: {$fechaHora}";
        }


        return $errors;
    }


    /**
     * Validate amounts
     */
    private function validateAmounts(Invoice $invoice): array
    {
        $errors = [];

        // Validate total amounts
        if ($invoice->getImporteTotal() <= 0) {
            $errors[] = "ImporteTotal must be greater than 0";
        }

        if ($invoice->getCuotaTotal() < 0) {
            $errors[] = "CuotaTotal cannot be negative (use rectification invoice for negative amounts)";
        }

        // Validate decimal places (AEAT expects 2 decimal places)
        if (fmod($invoice->getImporteTotal() * 100, 1) !== 0.0) {
            $errors[] = "ImporteTotal must have maximum 2 decimal places";
        }

        if (fmod($invoice->getCuotaTotal() * 100, 1) !== 0.0) {
            $errors[] = "CuotaTotal must have maximum 2 decimal places";
        }

        return $errors;
    }

    /**
     * Validate tax rates
     */
    private function validateTaxRates(Invoice $invoice): array
    {
        $errors = [];

        // Check if desglose exists and has valid tax rates
        // if ($invoice->getDesglose()) {
        //     $desglose = $invoice->getDesglose();

        //     // Validate tax rates are standard Spanish rates
        //     $validRates = [0, 4, 10, 21];

        //     // This would need to be implemented based on your Desglose structure
        //     // $taxRate = $desglose->getTipoImpositivo();
        //     // if (!in_array($taxRate, $validRates)) {
        //     //     $errors[] = "Invalid tax rate: {$taxRate}. Valid rates are: " . implode(', ', $validRates);
        //     // }
        // }

        return $errors;
    }

    /**
     * Validate business logic rules
     */
    private function validateBusinessLogic(Invoice $invoice): array
    {
        $errors = [];

        // Check for required fields based on invoice type
        if (in_array($invoice->getTipoFactura(), ['R1','R2']) && !$invoice->getTipoRectificativa()) {
            $errors[] = "Rectification invoices (R1/R2) must specify TipoRectificativa";
        }

        // Check for simplified invoice requirements
        if ($invoice->getTipoFactura() === 'F2' && !$invoice->getFacturaSimplificadaArt7273()) {
            $errors[] = "Simplified invoices (F2) must specify FacturaSimplificadaArt7273";
        }

        // Check for system information requirements
        if (!$invoice->getSistemaInformatico()) {
            $errors[] = "SistemaInformatico is required for all invoices";
        }

        // Check for encadenamiento requirements
        if (!$invoice->getEncadenamiento()) {
            $errors[] = "Encadenamiento is required for all invoices";
        }

        return $errors;
    }

    /**
     * Check if NIF format is valid for Spanish tax identification
     */
    private function isValidNif(string $nif): bool
    {
        // Basic format validation for Spanish NIFs
        // Company NIFs: Letter + 8 digits (e.g., B12345678)
        // Individual NIFs: 8 digits + letter (e.g., 12345678A)

        $pattern = '/^([A-Z]\d{8}|\d{8}[A-Z])$/';
        return preg_match($pattern, $nif) === 1;
    }

    /**
     * Get validation rules as array for documentation
     */
    public function getValidationRules(): array
    {
        return [
            'nif' => [
                'format' => 'Company: Letter + 8 digits (B12345678), Individual: 8 digits + letter (12345678A)',
                'required' => true
            ],
            'dates' => [
                'FechaHoraHusoGenRegistro' => 'YYYY-MM-DDTHH:MM:SS+HH:MM',
                'FechaExpedicionFactura' => 'YYYY-MM-DD'
            ],
            'amounts' => [
                'decimal_places' => 'Maximum 2 decimal places',
                'positive' => 'ImporteTotal must be positive',
                'tax_rates' => 'Valid rates: 0%, 4%, 10%, 21%'
            ],
            'invoice_numbers' => [
                'min_length' => 'Test numbers should be at least 10 characters',
                'characters' => 'Only letters, numbers, hyphens, underscores'
            ],
            'business_logic' => [
                'R1_invoices' => 'Must specify TipoRectificativa',
                'F2_invoices' => 'Must specify FacturaSimplificadaArt7273',
                'required_fields' => 'SistemaInformatico and Encadenamiento are required'
            ]
        ];
    }
}
