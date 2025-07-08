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

namespace App\Mail\Gateways;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class ACHVerificationNotification extends Mailable
{
    use SerializesModels;

    /**
     * @var Company
     */
    public $company;

    /**
     * @var string
     */
    public $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company, string $url)
    {
        $this->company = $company;

        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        return $this
            ->subject(ctrans('texts.ach_verification_notification_label'))
            ->text('email.gateways.ach-verification-notification_text')
            ->view('email.gateways.ach-verification-notification', [
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
                'company' => $this->company,
            ]);
    }
}
