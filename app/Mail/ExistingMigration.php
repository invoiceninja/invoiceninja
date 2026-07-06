<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

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
        App::setLocale($this->company->getLocale());

        $this->settings = $this->company->settings;
        $this->logo = $this->company->present()->logo();
        $this->company_name = $this->company->present()->name();

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->text('email.migration.existing_text')
            ->view('email.migration.existing');
    }
}
