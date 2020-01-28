<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;

    /**
     * Create a new message instance.
     *
     * @param $exception
     */
    public function __construct($exception)
    {
        $this->exception = $exception;
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
