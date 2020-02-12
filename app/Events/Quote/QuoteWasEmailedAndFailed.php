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

    /**
     * @var array
     */
    public $errors;

    /**
     * QuoteWasEmailedAndFailed constructor.
     * @param Quote $quote
     * @param array $errors
     */
    public function __construct(Quote $quote, array $errors)
    {
        $this->quote = $quote;

        $this->errors = $errors;
    }
}
