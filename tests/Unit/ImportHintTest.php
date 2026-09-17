<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Http\Controllers\ImportController;
use App\Import\Definitions\ExpenseMap;
use App\Import\Definitions\InvoiceMap;
use ReflectionMethod;
use Tests\TestCase;

class ImportHintTest extends TestCase
{
    private ImportController $controller;

    private ReflectionMethod $setImportHints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ImportController();
        $this->setImportHints = new ReflectionMethod($this->controller, 'setImportHints');
    }

    public function testTaxAmountAndAmountHintsUseTheMostSpecificExactMatch(): void
    {
        $this->assertSame(
            ['expense.tax_amount1', 'expense.amount'],
            $this->hintedMappings('expense', ExpenseMap::importable(), ['Tax Amount 1', 'Amount'])
        );

        $this->assertSame(
            ['expense.amount', 'expense.tax_amount1'],
            $this->hintedMappings('expense', ExpenseMap::importable(), ['Amount', 'Tax Amount 1'])
        );
    }

    public function testDueDateAndDateHintsAreIndependentOfHeaderOrder(): void
    {
        $this->assertSame(
            ['invoice.due_date', 'invoice.date'],
            $this->hintedMappings('invoice', InvoiceMap::importable(), ['Due Date', 'Date'])
        );

        $this->assertSame(
            ['invoice.date', 'invoice.due_date'],
            $this->hintedMappings('invoice', InvoiceMap::importable(), ['Date', 'Due Date'])
        );
    }

    public function testQualifiedRelatedEntityHeaderBeatsThePrimaryEntityField(): void
    {
        $this->assertSame(
            ['payment.date', 'invoice.date'],
            $this->hintedMappings('invoice', InvoiceMap::importable(), ['Payment Date', 'Date'])
        );
    }

    public function testCanonicalMappingKeysCanBeUsedAsHeaders(): void
    {
        $this->assertSame(
            ['expense.tax_amount1'],
            $this->hintedMappings('expense', ExpenseMap::importable(), ['expense.tax_amount1'])
        );
    }

    public function testAmbiguousAndSubstringOnlyHeadersAreNotGuessed(): void
    {
        $this->assertSame(
            [null],
            $this->hintedMappings('expense', ['invoice.date', 'quote.date'], ['Date'])
        );

        $this->assertSame(
            [null],
            $this->hintedMappings('expense', ExpenseMap::importable(), ['Settlement Amount'])
        );
    }

    /**
     * @param array<int, string> $availableMappings
     * @param array<int, string> $headers
     * @return array<int, string|null>
     */
    private function hintedMappings(string $entity, array $availableMappings, array $headers): array
    {
        $hints = $this->setImportHints->invoke(
            $this->controller,
            $entity,
            $availableMappings,
            $headers
        );

        return array_map(
            fn (?int $hint): ?string => $hint === null ? null : $availableMappings[$hint],
            $hints
        );
    }
}
