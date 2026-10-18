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

namespace App\Mail\Ninja;

use App\Models\Company;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class StripeConnectFailed extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public User $user, public Company $company) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        $title = ctrans('texts.stripe_connect_migration_title');
        $content = ctrans('texts.stripe_connect_migration_desc');
        $whitelabel = $this->company->account->isPaid();

        return $this->from(config('ninja.contact.email'))
            ->subject($title)
            ->text('email.admin.generic_text', [
                'title' => $title,
                'content' => $content,
                'whitelabel' => $whitelabel,
            ])
            ->view('email.admin.generic')
            ->with([
                'settings' => $this->company->settings,
                'logo' => $this->company->present()->logo(),
                'title' => $title,
                'content' => $content,
                'whitelabel' => $whitelabel,
                'url' => false,
                'button' => false,
            ]);
    }
}
