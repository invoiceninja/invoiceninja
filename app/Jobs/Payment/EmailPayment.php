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

namespace App\Jobs\Payment;

use App\Events\Payment\PaymentWasEmailed;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Engine\PaymentEmailEngine;
use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Payment;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $email_builder;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param Payment $payment
     * @param $email_builder
     * @param $contact
     * @param $company
     */
    public function __construct(public Payment $payment, private Company $company, private ?ClientContact $contact)
    {
        $this->settings = $payment->client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::setDb($this->company->db);

        $this->payment->load('invoices');

        if (!$this->contact) {
            $this->contact = $this->payment->client->contacts()->orderBy('is_primary', 'desc')->orderBy('send_email', 'desc')->first();
        }

        if (!$this->contact) {
            return;
        }

        if ($this->company->is_disabled) {
            nlog("company disabled");
            return;
        }

        $this->contact->load('client');

        if ($this->payment->client->getSetting('payment_email_all_contacts') && $this->payment->invoices && $this->payment->invoices->count() >= 1) {
            $this->emailAllContacts();
            return;
        }

        $email_builder = (new PaymentEmailEngine($this->payment, $this->contact))->build();

        $invitation = null;

        $nmo = new NinjaMailerObject();

        if ($this->payment->invoices && $this->payment->invoices->count() >= 1) {

            $invitation = $this->payment->invoices->first()->invitations()->where('client_contact_id', $this->contact->id)->first();
            
            if(!$invitation) {
                $invitation = $this->payment->invoices->first()->invitations()->first();
            }

            if ($invitation) {
                $nmo->invitation = $invitation;
            }
        }

        $nmo->mailable = new TemplateEmail($email_builder, $this->contact, $invitation);
        $nmo->to_user = $this->contact;
        $nmo->settings = $this->settings;
        $nmo->company = $this->company;
        $nmo->entity = $this->payment;
        $nmo->cc = collect($this->payment->client->cc_contacts())
        ->map(fn ($address) => $address->address)
        ->toArray();

        (new NinjaMailerJob($nmo))->handle();

        event(new PaymentWasEmailed($this->payment, $this->payment->company, $this->contact, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

    }

    private function emailAllContacts(): void
    {

        $invoice = $this->payment->invoices->first();

        $validInvitations = $invoice->invitations->filter(function ($invite) {
            return $invite->contact->send_email && filter_var($invite->contact->email, FILTER_VALIDATE_EMAIL) !== false;
        });

        if ($validInvitations->isEmpty()) {
            return;
        }

        $primaryInvite = $validInvitations->first();

        /** Contacts who have an invite and need a copy of the receipt */
        $ccEmails = $validInvitations->slice(1)->map(function ($invite) {
            return $invite->contact->email;
        })->values()->all();

        /** Merge in the CC only contacts who DON'T have an invite */
        $ccOnlyEmails = collect($this->payment->client->cc_contacts())
            ->map(fn ($address) => $address->address)
            ->toArray();

        $ccEmails = array_unique(array_merge($ccEmails, $ccOnlyEmails));

        $email_builder = (new PaymentEmailEngine($this->payment, $primaryInvite->contact))->build();

        $nmo = new NinjaMailerObject();
        $mailable = new TemplateEmail($email_builder, $primaryInvite->contact, $primaryInvite);

        if (!empty($ccEmails)) {
            $mailable->cc($ccEmails);
        }

        $nmo->mailable = $mailable;
        $nmo->to_user = $primaryInvite->contact;
        $nmo->settings = $this->settings;
        $nmo->company = $this->company;
        $nmo->entity = $this->payment;

        (new NinjaMailerJob($nmo))->handle();

        event(new PaymentWasEmailed($this->payment, $this->payment->company, $primaryInvite->contact, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

    }
}
