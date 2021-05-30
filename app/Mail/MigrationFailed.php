<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MigrationFailed extends Mailable
{

    public $exception;
    public $content;
    public $settings;
    public $company;
    /**
     * Create a new message instance.
     *
     * @param $content
     * @param $exception
     */
    public function __construct($exception, $company, $content = null)
    {
        $this->exception = $exception;
        $this->content = $content;
        $this->settings = $company->settings;
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('email.migration.failed', ['settings' => $this->settings, 'company' => $this->company]);
    }
}
