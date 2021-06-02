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

namespace App\Mail;

use App\Models\Company;
use App\Utils\ClientPortal\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactPasswordlessLogin extends Mailable
{

    /**
     * @var string
     */
    public $email;

    public $url;

    /**
     * Create a new message instance.
     *
     * @param string $email
     * @param string $redirect
     */
    public function __construct(string $email, $company_id, string $redirect = '')
    {
        $this->email = $email;

        $this->url = MagicLink::create($email, $company_id, $redirect);
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
            ->view('email.billing.passwordless-login');
    }
}
