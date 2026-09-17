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

use App\Models\Expense;
use Tests\TestCase;

/**
 */
class ExpenseInclusiveTaxTest extends TestCase
{
    private function makeExpense(float $amount, float $rate1 = 0, float $rate2 = 0, float $rate3 = 0): Expense
    {
        $expense = new Expense();
        $expense->amount = $amount;
        $expense->uses_inclusive_taxes = true;
        $expense->calculate_tax_by_amount = false;
        $expense->tax_rate1 = $rate1;
        $expense->tax_rate2 = $rate2;
        $expense->tax_rate3 = $rate3;

        return $expense;
    }

    public function testSingleInclusiveTaxUnchanged()
    {
        // net = 10 / 1.10 = 9.09, tax = 0.91 (identical to the legacy formula)
        $expense = $this->makeExpense(10, 10);

        $this->assertEquals(0.91, $expense->getTaxAmount());
        $this->assertEquals(9.09, $expense->getNetAmount());
    }

    public function testDoubleInclusiveTaxIsAdditiveAndReconciles()
    {
        // Canonical, tax-anchored: 1000 @ 2x10% -> each tax round(1000*10/120)=83.33,
        // total 166.66, net 833.34 (old overlap gave 181.82 / net 818.18)
        $expense = $this->makeExpense(1000, 10, 10);

        $this->assertEquals(166.66, $expense->getTaxAmount());
        $this->assertEquals(833.34, $expense->getNetAmount());

        // net + tax must equal the gross EXACTLY
        $this->assertEquals(1000, round($expense->getNetAmount() + $expense->getTaxAmount(), 2));
    }

    public function testInclusiveIsInverseOfExclusive()
    {
        // Exclusive net 1000 @ 2x10% -> gross 1200; inclusive of 1200 recovers net 1000.
        $expense = $this->makeExpense(1200, 10, 10);

        $this->assertEquals(200, $expense->getTaxAmount());
        $this->assertEquals(1000, $expense->getNetAmount());
    }
}
