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

use App\Events\Payment\PaymentWasEmailed;
use App\Jobs\Payment\EmailPayment;
use App\Models\ClientContact;
use App\Models\Payment;
use App\Utils\Ninja;

class SendEmail
{
    public function __construct(public Payment $payment, public ?ClientContact $contact)
    {
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {
        $this->payment->load('company', 'client.contacts', 'invoices');

        if (!$this->contact) {
            $this->contact = $this->payment->client->contacts()->first();
        }

        // $this->payment->invoices->sortByDesc('id')->first(function ($invoice) {
        //     $invoice->invitations->each(function ($invitation) {
        //         if (!$invitation->contact->trashed() && $invitation->contact->email) {
        EmailPayment::dispatch($this->payment, $this->payment->company, $this->contact);
                    
        event(new PaymentWasEmailed($this->payment, $this->payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        //         }
        //     });
        // });
    }
}
