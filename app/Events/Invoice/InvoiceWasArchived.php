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

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasArchived.
 */
class InvoiceWasArchived
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Invoice $invoice, Company $company, array $event_vars)
    {
        $this->invoice = $invoice;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
