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

namespace App\Services\Quickbooks\Cdc\Creators;

use App\Models\Invoice;

/**
 * Creates Ninja invoices for QuickBooks Invoices and SalesReceipts that
 * appeared in the CDC window and do not yet exist locally.
 *
 * NOTE ON PAYMENTS: creating a new invoice via the existing syncToNinja path
 * also imports and links the payments already attached to that invoice in QB
 * (via QbInvoice::syncNinjaInvoice's payment loop — the only working QB->Ninja
 * payment-linking code today). Payments applied to invoices that ALREADY exist
 * in Ninja are intentionally out of scope for this create-only pass.
 */
class CdcInvoiceCreator extends AbstractCdcCreator
{
    public function qbEntities(): array
    {
        // syncNinjaInvoice branches on IPPSalesReceipt (marks the invoice paid),
        // so both types can be handed to the same handler.
        return ['Invoice', 'SalesReceipt'];
    }

    public function ninjaEntity(): string
    {
        return 'invoice';
    }

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function persist(array $records): void
    {
        // $records are pre-filtered to new-only. For a new invoice, syncToNinja
        // creates it and attaches its linked payments; it never runs the update
        // branch here because none of these records exist in Ninja yet.
        $this->service->invoice->syncToNinja($records);
    }
}
