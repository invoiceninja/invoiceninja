<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;
    public $message;

    /**
     * Create a new message instance.
     *
     * @param $message
     * @param $exception
     */
    public function __construct($exception, $message = null)
    {
        $this->exception = $exception;
        $this->message = 'Oops, looks like something went wrong with your migration. Please try again, later.';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('noreply@invoiceninja.com')
                ->view('email.migration.failed');
    }
}
