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

namespace App\Events\RecurringQuote;

use App\Models\Company;
use App\Models\RecurringQuote;
use Illuminate\Queue\SerializesModels;

/**
 * Class RecurringQuoteWasRestored.
 */
class RecurringQuoteWasRestored
{
    use SerializesModels;

    /**
     * @var RecurringQuote
     */
    public $recurring_quote;

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
    public function __construct(RecurringQuote $recurring_quote, $fromDeleted, Company $company, array $event_vars)
    {
        $this->recurring_quote = $recurring_quote;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
