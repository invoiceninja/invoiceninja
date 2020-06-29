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

namespace App\Events\Invoice;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasRestored.
 */
class InvoiceWasRestored
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;
    
    public $fromDeleted;

    public $company;
    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param $fromDeleted
     */
    public function __construct(Invoice $invoice, $fromDeleted, $company)
    {
        $this->invoice = $invoice;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
    }
}
