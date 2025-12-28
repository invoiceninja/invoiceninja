<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\RecurringQuote;

use App\Models\Company;
use App\Models\RecurringQuote;
use Illuminate\Queue\SerializesModels;

/**
 * Class RecurringQuoteWasDeleted.
 */
class RecurringQuoteWasDeleted
{
    use SerializesModels;

    public function __construct(public RecurringQuote $recurring_quote, public Company $company, public array $event_vars)
    {
    }
}
