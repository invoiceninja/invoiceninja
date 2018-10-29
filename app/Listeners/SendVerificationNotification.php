<?php

namespace App\Listeners;

use App\Libraries\MultiDB;
use App\Mail\VerifyUser;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendVerificationNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        //send confirmation email using $event->user
        MultiDB::setDB($event->user->db);

        Mail::to($event->user->email)
            //->cc('')
            //->bcc('')
            ->queue(new VerifyUser($event->user));
    }
}
