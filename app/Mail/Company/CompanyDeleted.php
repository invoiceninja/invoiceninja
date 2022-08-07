<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Company;

use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CompanyDeleted extends Mailable
{
    // use Queueable, SerializesModels;

    public $account;

    public $company;

    public $user;

    public $settings;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company, $user, $account, $settings)
    {
        $this->company = $company;
        $this->user = $user;
        $this->account = $account;
        $this->settings = $settings;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.company_deleted'))
            ->view('email.admin.company_deleted')
            ->with([
                'settings' => $this->settings,
                'logo' => '',
                'title' => ctrans('texts.company_deleted'),
                'body' => ctrans('texts.company_deleted_body', ['company' => $this->company, 'user' => $this->user->present()->name(), 'time' => now()]),
                'whitelabel' => $this->account->isPaid(),
            ]);
    }
}
