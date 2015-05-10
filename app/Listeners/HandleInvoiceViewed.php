<?php namespace App\Listeners;

use App\Events\InvoiceViewed;
use App\Ninja\Mailers\UserMailer;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleInvoiceViewed {

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
	 * @param  InvoiceViewed  $event
	 * @return void
	 */
	public function handle(InvoiceViewed $event)
	{
        $invoice = $event->invoice;

        foreach ($invoice->account->users as $user)
        {
            if ($user->{'notify_viewed'})
            {
                $this->userMailer->sendNotification($user, $invoice, 'viewed');
            }
        }
	}

}
