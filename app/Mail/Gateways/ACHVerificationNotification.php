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

namespace App\Mail\Gateways;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ACHVerificationNotification extends Mailable
{
    use SerializesModels;

    /**
     * @var Company
     */
    public $company;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject(ctrans('texts.ach_verification_notification_label'))
            ->view('email.gateways.ach-verification-notification', [
                'logo' => $this->company->present()->logo(),
                'settings' => $this->company->settings,
                'company' => $this->company,
            ]);
    }
}
