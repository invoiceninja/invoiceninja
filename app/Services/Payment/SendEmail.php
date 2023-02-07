<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
        $this->payment->load('company', 'client.contacts','invoices');

        $contact = $this->payment->client->contacts()->first();

        // if ($contact?->email)
        //     EmailPayment::dispatch($this->payment, $this->payment->company, $contact)->delay(now()->addSeconds(2));


        $this->payment->invoices->sortByDesc('id')->first(function ($invoice){

            $invoice->invitations->each(function ($invitation) {

                if(!$invitation->contact->trashed() && $invitation->contact->email) {

                    EmailPayment::dispatch($this->payment, $this->payment->company, $invitation->contact)->delay(now()->addSeconds(2));

                }

            });

        });
         
    }
}
