<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;

class MigrationFailed extends Mailable
{
    public $exception;

    public $content;

    public $company;

    /**
     * Create a new message instance.
     *
     * @param $content
     * @param $exception
     */
    public function __construct($exception, Company $company, $content = null)
    {
        $this->exception = $exception;
        $this->content = $content;
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('email.migration.failed', [
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
            ]);
    }
}
