<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompanyImportFailure extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $settings;

    public $logo;

    public $title;

    public $message;

    public $whitelabel;

    public $user_message;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company, $user_message)
    {
        $this->company = $company;
        $this->user_message = $user_message;
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
        $this->title = ctrans('texts.max_companies');
        $this->message = ctrans('texts.max_companies_desc');
        $this->whitelabel = $this->company->account->isPaid();

        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject(ctrans('texts.company_import_failure_subject'))
                    ->view('email.migration.max_companies');
    }
}
