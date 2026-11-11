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

namespace Tests\Unit\EDocument;

use App\Models\Credit;
use App\Models\Invoice;
use App\Services\EDocument\UblDocumentKind;
use Tests\TestCase;

class UblDocumentKindTest extends TestCase
{
    public function testCreditModelResolvesToCreditNote(): void
    {
        $credit = new Credit();
        $credit->amount = 100;

        $this->assertSame(UblDocumentKind::CreditNote, UblDocumentKind::fromEntity($credit));
        $this->assertTrue(UblDocumentKind::fromEntity($credit)->isCreditNote());
    }

    public function testNegativeInvoiceResolvesToCreditNote(): void
    {
        $invoice = new Invoice();
        $invoice->amount = -250.50;

        $this->assertSame(UblDocumentKind::CreditNote, UblDocumentKind::fromEntity($invoice));
        $this->assertTrue(UblDocumentKind::fromEntity($invoice)->isCreditNote());
    }

    public function testPositiveInvoiceResolvesToInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->amount = 500;

        $this->assertSame(UblDocumentKind::Invoice, UblDocumentKind::fromEntity($invoice));
        $this->assertFalse(UblDocumentKind::fromEntity($invoice)->isCreditNote());
    }

    public function testZeroAmountInvoiceResolvesToInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->amount = 0;

        $this->assertSame(UblDocumentKind::Invoice, UblDocumentKind::fromEntity($invoice));
        $this->assertFalse(UblDocumentKind::fromEntity($invoice)->isCreditNote());
    }

    public function testTypeCodesMatchPeppolDocumentTypes(): void
    {
        $this->assertSame(380, UblDocumentKind::Invoice->typeCode());
        $this->assertSame(381, UblDocumentKind::CreditNote->typeCode());
    }
}
