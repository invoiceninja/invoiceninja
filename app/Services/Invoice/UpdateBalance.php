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

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\AbstractService;

class UpdateBalance extends AbstractService
{
    public function __construct(public Invoice $invoice, public float $balance_adjustment, public bool $is_draft)
    {
    }

    public function run()
    {
        if ($this->invoice->is_deleted) {
            return $this->invoice;
        }

        $this->invoice->increment('balance', floatval($this->balance_adjustment));

        if ($this->invoice->balance == 0 && ! $this->is_draft) {
            $this->invoice->status_id = Invoice::STATUS_PAID;
        }

        return $this->invoice;
    }
}
