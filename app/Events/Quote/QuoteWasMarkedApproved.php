<?php
namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasMarkedSent.
 */
class QuoteWasMarkedApproved
{
    use SerializesModels;
    /**
     * @var Quote
     */
    public $quote;

    public $company;

    /**
     * QuoteWasMarkedApproved constructor.
     * @param Quote $quote
     * @param Company $company
     */
    public function __construct(Quote $quote, Company $company)
    {
        $this->quote = $quote;
        $this->company = $company;
    }
}
