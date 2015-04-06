<?php namespace App\Listeners;

use App\Events\InvoiceSent;
use App\Ninja\Mailers\UserMailer;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleInvoiceSent {

    protected $userMailer;

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
    public function __construct(UserMailer $userMailer)
    {
        $this->userMailer = $userMailer;
    }   

	/**
	 * Handle the event.
	 *
	 * @param  InvoiceSent  $event
	 * @return void
	 */
	public function handle(InvoiceSent $event)
	{
        $invoice = $event->invoice;

        foreach ($invoice->account->users as $user)
        {
            if ($user->{'notify_sent'})
            {
                $this->userMailer->sendNotification($user, $invoice, 'sent');
            }
        }
	}

}
