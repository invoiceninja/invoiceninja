<?php namespace App\Listeners;

use App\Events\QuoteApproved;
use App\Ninja\Mailers\UserMailer;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleQuoteApproved {

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
	 * @param  QuoteApproved  $event
	 * @return void
	 */
	public function handle(QuoteApproved $event)
	{
        $invoice = $event->invoice;

        foreach ($invoice->account->users as $user)
        {
            if ($user->{'notify_approved'})
            {
                $this->userMailer->sendNotification($user, $invoice, 'approved');
            }
        }
	}

}
