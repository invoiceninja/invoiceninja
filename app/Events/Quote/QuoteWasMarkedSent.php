<?php
namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasMarkedSent.
 */
class QuoteWasMarkedSent
{
    use SerializesModels;
    /**
     * @var Invoice
     */
    public $quote;
    public $company;

    /**
     * Create a new event instance.
     *
     * @param Quote $quote
     */
    public function __construct(Quote $quote, Company $company)
    {
        $this->quote = $quote;
        $this->company = $company;
    }
}
