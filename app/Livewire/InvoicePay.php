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
use App\Livewire\Flow2\UnderOverPayment;
use App\Models\Invoice;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;

class InvoicePay extends Component
{
    use MakesDates;
    use MakesHash;

    public $invitation_id;

    public $invoices;

    public $variables;

    public $db;

    public $settings;

    public $terms_accepted = false;
    
    public $signature_accepted = false;

    public $payment_method_accepted = false;

    public $under_over_payment = false;

    public $required_fields = false;

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

    #[On('payable-amount')]
    public function payableAmount($payable_amount)
    {
        $this->context['payable_invoices'][0]['amount'] = Number::parseFloat($payable_amount);
        $this->under_over_payment = false;
    }

    #[On('payment-method-selected')]
    public function paymentMethodSelected($company_gateway_id, $gateway_type_id, $amount)
    {       
        //@TODO only handles single invoice scenario
        
        $this->payment_method_accepted = true;

        $this->context['company_gateway_id'] = $company_gateway_id;
        $this->context['gateway_type_id'] = $gateway_type_id;
        $this->context['amount'] = $amount;
        $this->context['pre_payment'] = false;
        $this->context['is_recurring'] = false;

        // $this->context['payable_invoices'] = ['invoice_id' => $this->context['invoice']->hashed_id, 'amount' => $amount];
        
        $this->context['invitation_id'] = $this->invitation_id;
        

    }

    #[Computed()]
    public function component(): string
    {
        if(!$this->terms_accepted)
            return Terms::class;

        if(!$this->signature_accepted)
            return Signature::class;

        if($this->under_over_payment)
            return UnderOverPayment::class;

        if(!$this->payment_method_accepted)
            return PaymentMethod::class;

        // if($this->ready)


            return ProcessPayment::class;
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-".md5(microtime());
    }

    public function mount()
    {
        
        MultiDB::setDb($this->db);

        // @phpstan-ignore-next-line
        $invite = \App\Models\InvoiceInvitation::with('contact.client','company')->withTrashed()->find($this->invitation_id);
        $client = $invite->contact->client;
        $settings = $client->getMergedSettings();
        $this->context['settings'] = $settings;

        $invoices = Invoice::find($this->transformKeys($this->invoices));
        $invoices = $invoices->filter(function ($i){
            
            $i = $i->service()
                ->markSent()
                ->removeUnpaidGatewayFees()
                ->save();

            return $i->isPayable();

        });

        //under-over / payment

        //required fields

        $this->terms_accepted = !$settings->show_accept_invoice_terms;
        $this->signature_accepted = !$settings->require_invoice_signature;
        $this->under_over_payment = $settings->client_portal_allow_over_payment || $settings->client_portal_allow_under_payment;
        $this->required_fields = false;

        $this->context['variables'] = $this->variables;
        $this->context['invoices'] = $invoices;
        $this->context['settings'] = $settings;
        $this->context['invitation'] = $invite;

        $this->context['payable_invoices'] = $invoices->map(function ($i){
            return [
                'invoice_id' => $i->hashed_id,
                'amount' => $i->partial > 0 ? $i->partial : $i->balance,
                'formatted_amount' => Number::formatValue($i->partial > 0 ? $i->partial : $i->balance, $i->client->currency()),
                'number' => $i->number,
                'date' => $i->translateDate($i->date, $i->client->date_format(), $i->client->locale())
            ];
        })->toArray();
        
    }

    public function render()
    {
         return render('components.livewire.invoice-pay', [
            'context' => $this->context
        ]);
    }
}