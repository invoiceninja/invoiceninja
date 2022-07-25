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

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasRestored.
 */
class QuoteWasRestored
{
    use SerializesModels;

    public $quote;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Quote $quote
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Quote $quote, $fromDeleted, Company $company, array $event_vars)
    {
        $this->quote = $quote;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
