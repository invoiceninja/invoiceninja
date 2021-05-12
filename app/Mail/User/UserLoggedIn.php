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

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn extends Mailable
{
    // use Queueable, SerializesModels;

    public $company;

    public $user;

    public $ip;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $company, $ip)
    {
        $this->company = $company;
        $this->user = $user;
        $this->ip = $ip;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject(ctrans('texts.new_login_detected'))
                    ->view('email.admin.notification')
                    ->with([
                        'settings' => $this->company->settings,
                        'logo' => $this->company->present()->logo(),
                        'title' => ctrans('texts.new_login_detected'),
                        'body' => ctrans('texts.new_login_description', ['email' =>$this->user->email, 'ip' => $this->ip, 'time' => now()]),
                        'whitelabel' => $this->company->account->isPaid(),
                    ]);
    }
}
