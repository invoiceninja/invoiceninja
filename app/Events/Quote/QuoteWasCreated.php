<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Quote;

use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasCreated.
 */
class QuoteWasCreated
{
    use SerializesModels;
    
    public $quote;

    public $company;

    public $event_vars;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Quote $quote, Company $company, array $event_vars)
    {
        $this->quote = $quote;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
