<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail;

use App\Models\Company;
use App\Utils\ClientPortal\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactPasswordlessLogin extends Mailable
{
    /** @var string */
    public $email;

    /** @var string */
    public $url;

    /** @var Company */
    public $company;

    /**
     * Create a new message instance.
     *
     * @param string $email
     * @param string $redirect
     */
    public function __construct(string $email, Company $company, string $redirect = '')
    {
        $this->email = $email;

        $this->company = $company;

        $this->url = MagicLink::create($email, $company->id, $redirect);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject(ctrans('texts.account_passwordless_login'))
            ->view('email.billing.passwordless-login', [
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
                'company' => $this->company->settings,
            ]);
    }
}
