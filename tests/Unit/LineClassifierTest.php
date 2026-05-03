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

use Tests\TestCase;
use App\Services\Report\TaxPeriod\LineClassifier;

class LineClassifierTest extends TestCase
{
    private function item(array $props): object
    {
        $defaults = [
            'expense_id' => '',
            'task_id' => '',
            'tax_id' => '',
            'type_id' => '1',
        ];

        return (object) array_merge($defaults, $props);
    }

    public function testExpenseIdWinsOverEverything(): void
    {
        $line = $this->item([
            'expense_id' => 'abc',
            'task_id' => 'xyz',
            'tax_id' => '2',
            'type_id' => '1',
        ]);

        $this->assertSame(LineClassifier::EXPENSE, LineClassifier::classify($line));
    }

    public function testTaskIdWinsOverProductTaxId(): void
    {
        $line = $this->item([
            'task_id' => 'xyz',
            'tax_id' => '1',
            'type_id' => '1',
        ]);

        $this->assertSame(LineClassifier::LABOR, LineClassifier::classify($line));
    }

    public function testProductTaxIdServiceReturnsService(): void
    {
        $line = $this->item([
            'tax_id' => '2',
            'type_id' => '1',
        ]);

        $this->assertSame(LineClassifier::SERVICE, LineClassifier::classify($line));
    }

    public function testProductTaxIdDigitalReturnsDigital(): void
    {
        $line = $this->item(['tax_id' => '3']);
        $this->assertSame(LineClassifier::DIGITAL, LineClassifier::classify($line));
    }

    public function testProductTaxIdShippingReturnsShipping(): void
    {
        $line = $this->item(['tax_id' => '4']);
        $this->assertSame(LineClassifier::SHIPPING, LineClassifier::classify($line));
    }

    public function testProductTaxIdExemptCategoriesReturnExempt(): void
    {
        foreach (['5', '6', '8', '9', '10'] as $tax_id) {
            $line = $this->item(['tax_id' => $tax_id]);
            $this->assertSame(
                LineClassifier::EXEMPT,
                LineClassifier::classify($line),
                "tax_id {$tax_id} should classify as exempt",
            );
        }
    }

    public function testTypeIdServiceFallback(): void
    {
        $line = $this->item([
            'tax_id' => '',
            'type_id' => '2',
        ]);

        $this->assertSame(LineClassifier::SERVICE, LineClassifier::classify($line));
    }

    public function testTypeIdFeeReturnsFee(): void
    {
        foreach (['3', '4', '5'] as $type_id) {
            $line = $this->item(['tax_id' => '', 'type_id' => $type_id]);
            $this->assertSame(
                LineClassifier::FEE,
                LineClassifier::classify($line),
                "type_id {$type_id} should classify as fee",
            );
        }
    }

    public function testTypeIdExpenseReturnsExpense(): void
    {
        $line = $this->item(['tax_id' => '', 'type_id' => '6']);
        $this->assertSame(LineClassifier::EXPENSE, LineClassifier::classify($line));
    }

    public function testDefaultIsProduct(): void
    {
        $line = $this->item([]);
        $this->assertSame(LineClassifier::PRODUCT, LineClassifier::classify($line));
    }

    public function testEmptyZeroIdsAreIgnored(): void
    {
        $line = $this->item([
            'expense_id' => '0',
            'task_id' => '0',
            'tax_id' => '1',
        ]);

        $this->assertSame(LineClassifier::PRODUCT, LineClassifier::classify($line));
    }
}
