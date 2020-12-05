<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;
    public $content;

    /**
     * Create a new message instance.
     *
     * @param $content
     * @param $exception
     */
    public function __construct($exception, $content = null)
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
        
        return $this->from(config('mail.from.address'), config('mail.from.name'))

                    ->view('email.migration.failed');
    }
}
