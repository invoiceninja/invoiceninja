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

use App\Models\Credit;
use App\Models\Invoice;
use ReflectionClass;
use Tests\TestCase;
use App\Services\EDocument\Standards\Peppol;

/**
 * Guards UBL credit-note line signs used by EntityLevel / XSLT validation.
 *
 * Price stays >= 0 (BR-27). Quantity carries any leftover sign so
 * price × qty == line amount (PEPPOL-EN16931-R120). An internal offset
 * line keeps the opposite sign of the credit lines (BR-CO-10 / BR-CO-13).
 */
class PeppolCreditNoteLineSignTest extends TestCase
{
    private function peppolWithAmount(float $amount, bool $credit = true): Peppol
    {
        $entity = $credit ? new Credit() : new Invoice();
        $entity->amount = $amount;

        $peppol = (new ReflectionClass(Peppol::class))->newInstanceWithoutConstructor();

        $invoice = new \ReflectionProperty(Peppol::class, 'invoice');
        $invoice->setAccessible(true);
        $invoice->setValue($peppol, $entity);

        return $peppol;
    }

    public function testCreditDocumentKeepsOffsetLineNegative(): void
    {
        $peppol = $this->peppolWithAmount(6534.0);

        $primary = $peppol->normalizeCreditNoteLine(1.0, 9490.0, 9490.0);
        $offset = $peppol->normalizeCreditNoteLine(-1.0, 4590.0, -4590.0);
        $secondary = $peppol->normalizeCreditNoteLine(1.0, 400.0, 400.0);
        $tertiary = $peppol->normalizeCreditNoteLine(1.0, 100.0, 100.0);

        $this->assertSame(1.0, $primary['quantity']);
        $this->assertSame(9490.0, $primary['price']);
        $this->assertSame(9490.0, $primary['line_total']);

        $this->assertSame(-1.0, $offset['quantity']);
        $this->assertSame(4590.0, $offset['price']);
        $this->assertSame(-4590.0, $offset['line_total']);

        $this->assertEqualsWithDelta(
            $offset['price'] * $offset['quantity'],
            $offset['line_total'],
            0.001
        );

        $sum = $primary['line_total'] + $offset['line_total'] + $secondary['line_total'] + $tertiary['line_total'];
        $this->assertEqualsWithDelta(5400.0, $sum, 0.01);
        $this->assertNotEquals(14580.0, $sum);
    }

    public function testEconomicSignProjectsNegativeCostIntoQuantityWhenLineHasAllowance(): void
    {
        $peppol = $this->peppolWithAmount(6534.0);

        $line = $peppol->normalizeCreditNoteLine(2.0, -100.0, -180.0, true);

        $this->assertSame(-2.0, $line['quantity']);
        $this->assertSame(100.0, $line['price']);
        $this->assertSame(-180.0, $line['line_total']);
    }

    public function testNegativeInvoiceDocumentDoesNotAbsOffsetLine(): void
    {
        $peppol = $this->peppolWithAmount(-6534.0, credit: false);

        $primary = $peppol->normalizeCreditNoteLine(-1.0, 9490.0, -9490.0);
        $offset = $peppol->normalizeCreditNoteLine(1.0, 4590.0, 4590.0);

        $this->assertSame(1.0, $primary['quantity']);
        $this->assertSame(9490.0, $primary['line_total']);

        $this->assertSame(-1.0, $offset['quantity']);
        $this->assertSame(-4590.0, $offset['line_total']);
        $this->assertSame(4590.0, $offset['price']);

        $this->assertEqualsWithDelta(
            $offset['price'] * $offset['quantity'],
            $offset['line_total'],
            0.001
        );
    }

    public function testNegativeDocumentWithSameSignLineAllowancePreservesQuantity(): void
    {
        $peppol = $this->peppolWithAmount(-180.0, credit: false);

        $line = $peppol->normalizeCreditNoteLine(-2.0, 100.0, -180.0, true);

        $this->assertSame(2.0, $line['quantity']);
        $this->assertSame(100.0, $line['price']);
        $this->assertSame(180.0, $line['line_total']);
    }

    public function testNegativeCreditWithLineAllowancePreservesQuantity(): void
    {
        $peppol = $this->peppolWithAmount(-4131.0);

        $line = $peppol->normalizeCreditNoteLine(-1.0, 4590.0, -4131.0, true);

        $this->assertSame(1.0, $line['quantity']);
        $this->assertSame(4590.0, $line['price']);
        $this->assertSame(4131.0, $line['line_total']);
    }

    public function testNegativeDocumentWithFlatAmountDiscountPreservesQuantity(): void
    {
        $peppol = $this->peppolWithAmount(-180.0, credit: false);

        $line = $peppol->normalizeCreditNoteLine(-2.0, 100.0, -180.0, true);

        $this->assertSame(2.0, $line['quantity']);
        $this->assertSame(100.0, $line['price']);
        $this->assertSame(180.0, $line['line_total']);
    }

    public function testNegativeDocumentWithOpposingLineAllowanceNegatesQuantity(): void
    {
        $peppol = $this->peppolWithAmount(-320.0, credit: false);

        $line = $peppol->normalizeCreditNoteLine(2.0, 100.0, 180.0, true);

        $this->assertSame(-2.0, $line['quantity']);
        $this->assertSame(100.0, $line['price']);
        $this->assertSame(-180.0, $line['line_total']);
    }

    public function testNegativeDocumentMixedLinesProjectOpposingAndSameSign(): void
    {
        $invoice = new Invoice();
        $invoice->amount = -3923.91;
        $invoice->line_items = [
            (object) ['cost' => 500.0, 'quantity' => 1.0],
            (object) ['cost' => 4590.0, 'quantity' => -1.0],
        ];

        $peppol = (new ReflectionClass(Peppol::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(Peppol::class, 'invoice');
        $prop->setAccessible(true);
        $prop->setValue($peppol, $invoice);

        $primary = $peppol->normalizeCreditNoteLine(1.0, 500.0, 500.0, false);
        $offset = $peppol->normalizeCreditNoteLine(-1.0, 4590.0, -4131.0, true);

        $this->assertSame(-1.0, $primary['quantity']);
        $this->assertSame(-500.0, $primary['line_total']);
        $this->assertSame(1.0, $offset['quantity']);
        $this->assertSame(4131.0, $offset['line_total']);
        $this->assertEqualsWithDelta(3631.0, $primary['line_total'] + $offset['line_total'], 0.001);
    }
}
