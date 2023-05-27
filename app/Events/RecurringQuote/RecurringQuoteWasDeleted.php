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
