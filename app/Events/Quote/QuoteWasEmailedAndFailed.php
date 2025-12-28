<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasEmailedAndFailed.
 */
class QuoteWasEmailedAndFailed
{
    use SerializesModels;

    /**
     * @var Quote
     */
    public $quote;

    public $company;

    public $errors;

    public $event_vars;

    /**
     * QuoteWasEmailedAndFailed constructor.
     * @param Quote $quote
     * @param array $errors
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Quote $quote, array $errors, Company $company, array $event_vars)
    {
        $this->quote = $quote;

        $this->errors = $errors;

        $this->company = $company;

        $this->event_vars = $event_vars;
    }
}
