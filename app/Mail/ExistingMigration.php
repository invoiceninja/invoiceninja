<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExistingMigration extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $settings;

    public $logo;

    public $company_name;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company)
    {
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->settings = $this->company->settings;
        $this->logo = $this->company->present()->logo();
        $this->company_name = $this->company->present()->name();

         return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->view('email.migration.existing');
    }
}
