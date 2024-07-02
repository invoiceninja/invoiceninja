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

use App\Utils\Number;
use App\Models\Invoice;
use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\Livewire\Flow2\Terms;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Livewire\Flow2\Signature;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use App\Livewire\Flow2\PaymentMethod;
use App\Livewire\Flow2\ProcessPayment;
use App\Livewire\Flow2\RequiredFields;
use App\Livewire\Flow2\UnderOverPayment;

class InvoicePay extends Component
{
    use MakesDates;
    use MakesHash;

     private $mappings = [
        'client_name' => 'name',
        'client_website' => 'website',
        'client_phone' => 'phone',

        'client_address_line_1' => 'address1',
        'client_address_line_2' => 'address2',
        'client_city' => 'city',
        'client_state' => 'state',
        'client_postal_code' => 'postal_code',
        'client_country_id' => 'country_id',

        'client_shipping_address_line_1' => 'shipping_address1',
        'client_shipping_address_line_2' => 'shipping_address2',
        'client_shipping_city' => 'shipping_city',
        'client_shipping_state' => 'shipping_state',
        'client_shipping_postal_code' => 'shipping_postal_code',
        'client_shipping_country_id' => 'shipping_country_id',

        'client_custom_value1' => 'custom_value1',
        'client_custom_value2' => 'custom_value2',
        'client_custom_value3' => 'custom_value3',
        'client_custom_value4' => 'custom_value4',

        'contact_first_name' => 'first_name',
        'contact_last_name' => 'last_name',
        'contact_email' => 'email',
        // 'contact_phone' => 'phone',
    ];

    public $client_address_array = [
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
    ];

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
        $this->context['company_gateway_id'] = $company_gateway_id;
        $this->context['gateway_type_id'] = $gateway_type_id;
        $this->context['amount'] = $amount;
        $this->context['pre_payment'] = false;
        $this->context['is_recurring'] = false;
        $this->context['invitation_id'] = $this->invitation_id;

        $this->payment_method_accepted = true;
        
        $company_gateway = CompanyGateway::find($company_gateway_id);

        $this->checkRequiredFields($company_gateway);

    }

    #[On('required-fields')]
    public function requiredFieldsFilled()
    {
        $this->required_fields = false;
    }

    private function checkRequiredFields(CompanyGateway $company_gateway)
    {
        
        $fields = $company_gateway->driver()->getClientRequiredFields();
        $this->context['fields'] = $fields;

        if($company_gateway->always_show_required_fields){
            return $this->required_fields = true;
        }

        $contact = $this->context['contact'];
               
        foreach ($fields as $index => $field) {
            $_field = $this->mappings[$field['name']];

            if (\Illuminate\Support\Str::startsWith($field['name'], 'client_')) {
                if (empty($contact->client->{$_field})
                || is_null($contact->client->{$_field})
                ) {
                    
                    return $this->required_fields = true;

                } 
            }

            if (\Illuminate\Support\Str::startsWith($field['name'], 'contact_')) {
                if (empty($contact->{$_field}) || is_null($contact->{$_field}) || str_contains($contact->{$_field}, '@example.com')) {
                    return $this->required_fields = true;
                } 
            }
        }

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

        if($this->required_fields) 
            return RequiredFields::class;
        
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
        $this->context['contact'] = $invite->contact;
        $this->context['settings'] = $settings;
        $this->context['db'] = $this->db;
        
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