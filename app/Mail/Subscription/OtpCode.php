<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Subscription;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class OtpCode extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $contact;

    public $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($company, $contact, $code)
    {
        $this->company = $company;
        $this->contact = $contact;
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->locale());

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.otp_code_subject'))
            ->text('email.admin.generic_text')
            ->view('email.client.generic')
            ->with([
                'settings' => $this->company->settings,
                'logo' => $this->company->present()->logo(),
                'title' => ctrans('texts.otp_code_subject'),
                'content' => ctrans('texts.otp_code_body', ['code' => $this->code]),
                'whitelabel' => $this->company->account->isPaid(),
                'url' => false,
                'button' => false,
                'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
            ]);
    }
}
