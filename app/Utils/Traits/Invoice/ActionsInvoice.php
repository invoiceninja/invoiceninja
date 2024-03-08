<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Invoice;

use App\Models\Invoice;

trait ActionsInvoice
{
    public function invoiceDeletable($invoice): bool
    {
        if ($invoice->status_id <= Invoice::STATUS_SENT &&
            $invoice->is_deleted == false &&
            $invoice->deleted_at == null &&
            $invoice->balance == 0) {
            return true;
        }

        return false;
    }

    public function invoiceCancellable($invoice): bool
    {
        if (($invoice->status_id == Invoice::STATUS_SENT ||
             $invoice->status_id == Invoice::STATUS_PARTIAL) &&
             $invoice->is_deleted == false &&
             $invoice->deleted_at == null) {
            return true;
        }

        return false;
    }

    public function invoiceReversable($invoice): bool
    {
        if (($invoice->status_id == Invoice::STATUS_SENT ||
             $invoice->status_id == Invoice::STATUS_PARTIAL ||
             $invoice->status_id == Invoice::STATUS_CANCELLED ||
             $invoice->status_id == Invoice::STATUS_PAID) &&
             $invoice->is_deleted == false &&
             $invoice->deleted_at == null) {
            return true;
        }

        return false;
    }
}
