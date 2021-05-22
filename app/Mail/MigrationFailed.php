<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationFailed extends Mailable
{
    // use Queueable, SerializesModels;

    public $exception;
    public $content;
    public $settings;
    /**
     * Create a new message instance.
     *
     * @param $content
     * @param $exception
     */
    public function __construct($exception, $content = null, $settings)
    {
        $this->exception = $exception;
        $this->content = $content;
        $this->settings = $settings;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('email.migration.failed', ['settings' => $this->settings]);
    }
}
