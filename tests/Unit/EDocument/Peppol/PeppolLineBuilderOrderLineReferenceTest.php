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

namespace Tests\Unit\EDocument\Peppol;

use ReflectionMethod;
use Tests\TestCase;
use App\Models\Invoice;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Standards\Peppol\PeppolLineBuilder;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNoteLineType\CreditNoteLine;

/**
 * Coverage for PeppolLineBuilder::addOrderLineReference() (BT-132).
 *
 * No DB and no XML validation: the Peppol document is mocked and the private
 * method is invoked directly. The proxy value lives on the line item in the
 * UBL shape: e_invoice->InvoiceLine->OrderLineReference->LineID.
 */
class PeppolLineBuilderOrderLineReferenceTest extends TestCase
{
    private function builder(?string $po_number): PeppolLineBuilder
    {
        $invoice = new Invoice();
        $invoice->po_number = $po_number;

        $peppol = $this->createMock(Peppol::class);
        $peppol->method('getInvoiceModel')->willReturn($invoice);

        return new PeppolLineBuilder($peppol);
    }

    private function item(?string $root, ?string $line_id): object
    {
        if ($root === null) {
            return (object) ['product_key' => 'Widget'];
        }

        return json_decode(json_encode([
            'product_key' => 'Widget',
            'e_invoice' => [$root => ['OrderLineReference' => ['LineID' => $line_id]]],
        ]));
    }

    private function apply(PeppolLineBuilder $builder, InvoiceLine|CreditNoteLine $line, object $item, bool $is_credit_note): void
    {
        $method = new ReflectionMethod(PeppolLineBuilder::class, 'addOrderLineReference');
        $method->setAccessible(true);
        $method->invoke($builder, $line, $item, $is_credit_note);
    }

    public function testReferenceIsSetWhenValueAndPoNumberArePresent(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item('InvoiceLine', '00010'), false);

        $this->assertTrue(isset($line->OrderLineReference));
        $this->assertCount(1, $line->OrderLineReference);
        $this->assertSame('00010', $line->OrderLineReference[0]->LineID->value);
    }

    public function testValueKeepsLeadingZeros(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item('InvoiceLine', '  00010 '), false);

        $this->assertSame('00010', $line->OrderLineReference[0]->LineID->value);
    }

    public function testReferenceIsSkippedWithoutPoNumber(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder(''), $line, $this->item('InvoiceLine', '00010'), false);

        $this->assertFalse(isset($line->OrderLineReference));
    }

    public function testReferenceIsSkippedWithNullPoNumber(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder(null), $line, $this->item('InvoiceLine', '00010'), false);

        $this->assertFalse(isset($line->OrderLineReference));
    }

    public function testReferenceIsSkippedWithSingleCharacterPoNumber(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder('X'), $line, $this->item('InvoiceLine', '00010'), false);

        $this->assertFalse(isset($line->OrderLineReference));
    }

    public function testReferenceIsSkippedWithoutValue(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item(null, null), false);

        $this->assertFalse(isset($line->OrderLineReference));
    }

    public function testReferenceIsSkippedWithBlankValue(): void
    {
        $line = new InvoiceLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item('InvoiceLine', '   '), false);

        $this->assertFalse(isset($line->OrderLineReference));
    }

    public function testCreditNoteReadsTheCreditNoteLineKey(): void
    {
        $line = new CreditNoteLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item('CreditNoteLine', '00020'), true);

        $this->assertSame('00020', $line->OrderLineReference[0]->LineID->value);
    }

    public function testCreditNoteIgnoresTheInvoiceLineKey(): void
    {
        $line = new CreditNoteLine();

        $this->apply($this->builder('PO-12345'), $line, $this->item('InvoiceLine', '00010'), true);

        $this->assertFalse(isset($line->OrderLineReference));
    }
}
