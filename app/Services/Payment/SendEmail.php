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

namespace App\Services\Payment;

use App\Jobs\Payment\EmailPayment;

class SendEmail
{
    public $payment;

    public $contact;

    public function __construct($payment, $contact)
    {
        $this->payment = $payment;

        $this->contact = $contact;
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {
        $this->payment->client->contacts->each(function ($contact) {
            if ($contact->send_email && $contact->email) {
                EmailPayment::dispatchNow($this->payment, $this->payment->company, $contact);
            }
        });
    }
}
