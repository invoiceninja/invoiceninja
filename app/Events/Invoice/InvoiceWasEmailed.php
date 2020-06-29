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

use App\Models\InvoiceInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasEmailed.
 */
class InvoiceWasEmailed
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invitation;

    public $company;
    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(InvoiceInvitation $invitation, $company)
    {
        $this->invitation = $invitation;
        $this->company = $company;
    }
}
