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

use App\Utils\ClientPortal\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactPasswordlessLogin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var string
     */
    public $email;

    public $url = 'https://google.com';

    /**
     * Create a new message instance.
     *
     * @param string $email
     * @param string $redirect
     */
    public function __construct(string $email, string $redirect = '')
    {
        $this->email = $email;

        $this->url = MagicLink::create($email, $redirect);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('email.billing.passwordless-login');
    }
}
