<?php

namespace App\Mail\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteWasApproved extends Mailable
{
   // use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))->view('email.quotes.approved');
    }
}
