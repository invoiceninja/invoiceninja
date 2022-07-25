<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\RecurringInvoice;

use App\Models\Company;
use App\Models\RecurringInvoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class RecurringInvoiceWasRestored.
 */
class RecurringInvoiceWasRestored
{
    use SerializesModels;

    /**
     * @var RecurringInvoice
     */
    public $recurring_invoice;

    public $fromDeleted;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param $fromDeleted
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(RecurringInvoice $recurring_invoice, $fromDeleted, Company $company, array $event_vars)
    {
        $this->recurring_invoice = $recurring_invoice;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
