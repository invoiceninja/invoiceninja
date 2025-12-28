<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceAutoBillSuccess.
 */
class InvoiceAutoBillSuccess
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public Invoice $invoice, public Company $company, public array $event_vars)
    {
    }
}
