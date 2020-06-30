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

use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasEmailed.
 */
class QuoteWasEmailed
{
    use SerializesModels;

    public $quote;

    public $company;

    /**
     * @var string
     */
    public $notes;

    /**
     * Create a new event instance.
     *
     * @param $quote
     */
    public function __construct($quote, $notes, $company)
    {
        $this->quote = $quote;
        $this->notes = $notes;
        $this->company = $company;
    }

}
