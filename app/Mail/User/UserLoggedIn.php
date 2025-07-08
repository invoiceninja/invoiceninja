<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\User;

use App\Models\User;
use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class UserLoggedIn extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public User $user, public Company $company, public string $ip)
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        $text = ctrans('texts.new_login_description', ['email' => $this->user->email, 'ip' => $this->ip, 'time' => now()]);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.new_login_detected'))
            ->text('email.admin.generic_text', [
                'title' => ctrans('texts.new_login_detected'),
                'body' => $text,
            ])
            ->view('email.admin.notification')
            ->with([
                'settings' => $this->company->settings,
                'logo' => $this->company->present()->logo(),
                'title' => ctrans('texts.new_login_detected'),
                'body' => ctrans('texts.new_login_description', ['email' => $this->user->email, 'ip' => $this->ip, 'time' => now()]),
                'whitelabel' => $this->company->account->isPaid(),
            ]);
    }
}
