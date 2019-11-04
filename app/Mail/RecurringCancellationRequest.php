<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecurringCancellationRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $recurring_invoice

    public function __construct(RecurringInvoice $recurring_invoice)
    {
        $this->recurring_invoice = $recurring_invoice
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //iterate in here on the interested parties

        return $this->view('view.name');
    }
}
