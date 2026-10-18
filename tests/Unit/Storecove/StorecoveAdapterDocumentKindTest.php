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

namespace Tests\Unit\Storecove;

use App\Models\Invoice;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveAdapter;
use App\Services\EDocument\UblDocumentKind;
use App\Services\EDocument\UblDocumentKindMismatchException;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNote;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Tests\TestCase;

class StorecoveAdapterDocumentKindTest extends TestCase
{
    public function testTransformFromPeppolRejectsMismatchedDocumentKind(): void
    {
        $adapter = new StorecoveAdapter(new Storecove());

        $this->expectException(UblDocumentKindMismatchException::class);
        $this->expectExceptionMessage('UblDocumentKind::Invoice does not match Peppol CreditNote document.');

        $adapter->transformFromPeppol(
            new Invoice(),
            new CreditNote(),
            UblDocumentKind::Invoice,
        );
    }

    public function testTransformFromPeppolRejectsCreditKindWithInvoiceDocument(): void
    {
        $adapter = new StorecoveAdapter(new Storecove());

        $this->expectException(UblDocumentKindMismatchException::class);
        $this->expectExceptionMessage('UblDocumentKind::CreditNote does not match Peppol Invoice document.');

        $adapter->transformFromPeppol(
            new Invoice(),
            new PeppolInvoice(),
            UblDocumentKind::CreditNote,
        );
    }
}
