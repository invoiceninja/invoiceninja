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

namespace App\Listeners\Invoice;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateInvoiceInvitations implements ShouldQueue
{

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

    	$payment = $event->payment;
    	$invoices = $payment->invoices;


		/**
		* Move this into an event
		*/
		$invoices->each(function ($invoice) use($payment) {

			$invoice->status_id = Invoice::STATUS_PAID;
			$invoice->save();
			$invoice->invitations()->update(['transaction_reference' => $payment->transaction_reference]);

		});

    }
}
