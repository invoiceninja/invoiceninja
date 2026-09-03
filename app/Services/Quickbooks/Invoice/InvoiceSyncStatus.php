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

namespace App\Services\Quickbooks\Invoice;

use App\DataMapper\InvoiceSync;
use App\Models\Invoice;

class InvoiceSyncStatus
{
    public function markSynced(
        Invoice $invoice,
        string $qb_id,
        string $sync_token = '',
        bool $clear_status_message = false
    ): void {
        $invoice->refresh();
        $sync = $invoice->sync ?? new InvoiceSync();
        $sync->markSynced($qb_id, $sync_token, $clear_status_message);
        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }

    public function markPushFailure(Invoice $invoice, string $message): void
    {
        $invoice->refresh();
        $sync = $invoice->sync ?? new InvoiceSync();
        $sync->markPushFailure($message);
        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }
}
