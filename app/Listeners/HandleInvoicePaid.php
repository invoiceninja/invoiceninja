<?php namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleInvoicePaid {

    protected $userMailer;
    protected $contactMailer;

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
    public function __construct(UserMailer $userMailer, ContactMailer $contactMailer)
    {
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
    }   

	/**
	 * Handle the event.
	 *
	 * @param  InvoicePaid  $event
	 * @return void
	 */
	public function handle(InvoicePaid $event)
	{
        $payment = $event->payment;
        $invoice = $payment->invoice;
        
        $this->contactMailer->sendPaymentConfirmation($payment);
                
        foreach ($invoice->account->users as $user)
        {
            if ($user->{'notify_paid'})
            {
                $this->userMailer->sendNotification($user, $invoice, 'paid', $payment);
            }
        }
	}

}
