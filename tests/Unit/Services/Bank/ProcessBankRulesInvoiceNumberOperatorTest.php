<?php

namespace Tests\Unit\Services\Bank;

use App\Models\Invoice;
use App\Models\BankTransaction;
use App\Services\Bank\ProcessBankRules;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ProcessBankRulesInvoiceNumberOperatorTest extends TestCase
{
    public function testInvoiceNumberContainsMatchesEmbeddedNumber(): void
    {
        $this->assertTrue($this->matchesInvoiceNumber(
            description: 'Payment received for INV-001',
            invoiceNumber: 'INV-001',
            operator: 'contains'
        ));
    }

    public function testInvoiceNumberStartsWithDoesNotMatchEmbeddedNumber(): void
    {
        $this->assertFalse($this->matchesInvoiceNumber(
            description: 'Payment received for INV-001',
            invoiceNumber: 'INV-001',
            operator: 'starts_with'
        ));
    }

    public function testInvoiceNumberIsDoesNotMatchEmbeddedNumber(): void
    {
        $this->assertFalse($this->matchesInvoiceNumber(
            description: 'Payment received for INV-001',
            invoiceNumber: 'INV-001',
            operator: 'is'
        ));
    }

    public function testInvoiceNumberIsMatchesExactDescription(): void
    {
        $this->assertTrue($this->matchesInvoiceNumber(
            description: 'INV-001',
            invoiceNumber: 'INV-001',
            operator: 'is'
        ));
    }

    private function matchesInvoiceNumber(string $description, string $invoiceNumber, string $operator): bool
    {
        $bankTransaction = new BankTransaction();
        $bankTransaction->description = $description;

        $invoice = new Invoice();
        $invoice->number = $invoiceNumber;

        $service = new ProcessBankRules($bankTransaction);

        $property = new ReflectionProperty(ProcessBankRules::class, 'invoices');
        $property->setAccessible(true);
        $property->setValue($service, collect([$invoice]));

        $method = new ReflectionMethod(ProcessBankRules::class, 'searchInvoiceNumber');
        $method->setAccessible(true);

        $matchedInvoice = null;

        return $method->invokeArgs($service, [
            &$matchedInvoice,
            [
                'search_key' => 'description',
                'operator' => $operator,
                'value' => '$invoice.number',
            ],
        ]);
    }
}
