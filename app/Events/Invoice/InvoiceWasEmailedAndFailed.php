<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Invoice;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasEmailedAndFailed.
 */
class InvoiceWasEmailedAndFailed 
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * @var array
     */
    public $errors;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(Invoice $invoice, array $errors)
    {
        $this->invoice = $invoice;
        
        $this->errors = $errors;
    }
}
