<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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
        $this->payment->load('company', 'client.contacts');

        $this->payment->client->contacts->each(function ($contact) {
            if ($contact->email) {
                // dispatchSync always returns 0, in this case we can handle it without returning false;
                return EmailPayment::dispatchSync($this->payment, $this->payment->company, $contact);
                //     return false;
                //11-01-2021 only send payment receipt to the first contact
            }
        });
    }
}
