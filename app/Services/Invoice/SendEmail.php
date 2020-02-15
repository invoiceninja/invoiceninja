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

namespace App\Services\Invoice;

use App\Jobs\Invoice\EmailInvoice;
use App\Models\Invoice;

class SendEmail
{

    protected $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

  	public function run()
  	{
        
        $this->invoice->invitations->each(function ($invitation) use ($template_style) {
        
            if ($invitation->contact->send_invoice && $invitation->contact->email) {

                EmailInvoice::dispatch($invitation, $this->invoice->company);   
            
            }

        });

  	}

}