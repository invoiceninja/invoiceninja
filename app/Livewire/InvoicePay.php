<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\Livewire\Flow2\Terms;
use App\Livewire\Flow2\Signature;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use App\Livewire\Flow2\PaymentMethod;
use App\Livewire\Flow2\ProcessPayment;

class InvoicePay extends Component
{
    public $invitation_id;

    public $db;

    public $settings;

    public $terms_accepted = false;
    
    public $signature_accepted = false;

    public $payment_method_accepted = false;

    public array $context = [];

    #[On('update.context')]
    public function handleContext(string $property, $value): self
    {

        data_set($this->context, $property, $value);

        return $this;
    }

    #[On('terms-accepted')]
    public function termsAccepted()
    {
        nlog("Terms accepted");
        // $this->invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id)->withoutRelations();
        $this->terms_accepted =true;
    }

    #[On('signature-captured')]
    public function signatureCaptured($base64)
    {
        nlog("signature captured");

        $this->signature_accepted = true;
        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id)->withoutRelations();
        $invite->signature_base64 = $base64;
        $invite->signature_date = now()->addSeconds($invite->contact->client->timezone_offset());
        $this->context['signature'] = $base64;
        $invite->save();
    
    }

    #[On('payment-method-selected')]
    public function paymentMethodSelected($company_gateway_id, $gateway_type_id, $amount)
    {       
        $this->context['company_gateway_id'] = $company_gateway_id;
        $this->context['gateway_type_id'] = $gateway_type_id;
        $this->context['amount'] = $amount;
        $this->context['pre_payment'] = false;
        $this->context['is_recurring'] = false;
        $this->context['payable_invoices'] = ['invoice_id' => $this->context['invoice']->hashed_id, 'amount' => $this->context['invoice']->balance];
        $this->context['invitation_id'] = $this->invitation_id;
        
        // $this->invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id)->withoutRelations();
        $this->payment_method_accepted =true;
    }

    #[Computed()]
    public function component(): string
    {
        if(!$this->terms_accepted)
            return Terms::class;

        if(!$this->signature_accepted)
            return Signature::class;

        if(!$this->payment_method_accepted)
            return PaymentMethod::class;

        return ProcessPayment::class;
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-".md5(time());
    }

    public function mount()
    {
        
        MultiDB::setDb($this->db);

        // @phpstan-ignore-next-line
        $invite = \App\Models\InvoiceInvitation::with('invoice','contact.client','company')->withTrashed()->find($this->invitation_id);
        $client = $invite->contact->client;
        $variables = ($invite && auth()->guard('contact')->user()->client->getSetting('show_accept_invoice_terms')) ? (new HtmlEngine($invite))->generateLabelsAndValues() : false;
        $settings = $client->getMergedSettings();

        $this->terms_accepted = !$settings->show_accept_invoice_terms;
        $this->signature_accepted = !$settings->require_invoice_signature;
        
        $this->context['variables'] = $variables;
        $this->context['invoice'] = $invite->invoice;
        $this->context['settings'] = $settings;
    }

    public function render()
    {
         return render('components.livewire.invoice-pay', [
            'context' => $this->context
        ]);
    }
}