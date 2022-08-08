<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class MigrationFailed extends Mailable
{
    public $exception;

    public $content;

    public $company;

    public $is_system = false;

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
        App::setLocale($this->company->getLocale());

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->text('email.migration.failed_text')
            ->view('email.migration.failed', [
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
                'is_system' => $this->is_system,
            ]);
    }
}
