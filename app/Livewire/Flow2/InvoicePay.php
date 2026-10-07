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

namespace App\Livewire\Flow2;

use App\Utils\Number;
use App\Models\Invoice;
use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\Models\CompanyGateway;
use App\Services\Client\RFFService;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use Livewire\Attributes\Computed;
use App\Utils\Traits\WithSecureContext;
use Livewire\Attributes\Locked;

class InvoicePay extends Component
{
    use MakesDates;
    use MakesHash;
    use WithSecureContext;

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

    #[Locked]
    public string $payment_attempt_key = '';

    #[Locked]
    public $invitation_id;

    public $invoices;

    public $variables;

    #[Locked]
    public $db;

    public $settings;

    public $terms_accepted = false;

    public $signature_accepted = false;

    public $payment_method_accepted = false;

    public $under_over_payment = false;

    public $required_fields = false;

    public $docu_ninja_active = false;
    
    public $docu_ninja_ready = false;

    public ?int $signing_invitation_id = null;

    public ?string $signing_key = null;
    
    public array $unsigned_invitation_queue = [];

    // #[On('update.context')]
    // public function handleContext(string $key, string $property, $value): self
    // {
    //     $this->setContext($key, $property, $value);

    //     return $this;
    // }

    #[On('terms-accepted')]
    public function termsAccepted()
    {
        $this->terms_accepted = true;
    }

    #[On('signature-captured')]
    public function signatureCaptured($base64)
    {

        $this->signature_accepted = true;
        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id);
        $invite->signature_base64 = $base64;
        $invite->signature_date = now()->addSeconds($invite->contact->client->timezone_offset());
        $this->setContext($invite->key, 'signature', $base64); // $this->context['signature'] = $base64;
        $invite->save();

    }

    #[On('docuninja-signature-captured')]
    public function docuNinjaSignatureCaptured()
    {
        $signed_id = $this->signing_invitation_id ?? $this->invitation_id;
        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($signed_id);

        if (!$invite->invoice->sync?->dn_completed) {
            $invite->invoice->sync->dn_completed = true;
            $invite->invoice->saveQuietly();
        }

        if (!empty($this->unsigned_invitation_queue)) {
            $nextId = array_shift($this->unsigned_invitation_queue);
            $nextInv = \App\Models\InvoiceInvitation::with('contact.client')->withTrashed()->find($nextId);

            if ($nextInv) {
                $this->advanceSigning($nextInv);
                return;
            }
        }

        $this->signing_invitation_id = null;
        $this->signing_key = null;
        $this->docu_ninja_ready = false;
        $this->signature_accepted = true;
    }

    private function advanceSigning(\App\Models\InvoiceInvitation $inv): void
    {
        $this->signing_invitation_id = $inv->id;
        $this->signing_key = $inv->key;
        $this->docu_ninja_ready = false;

        $this->bulkSetContext($inv->key, [
            'contact' => $inv->contact,
            'settings' => $inv->contact->client->getMergedSettings(),
            'db' => $this->db,
            'invitation_id' => $inv->id,
            'entity_type' => 'invoice',
        ]);
    }

    /** We need to have a valid docuninja payload prior to calling the DocuNinja component. */
    #[On('docuninja-loader-ready')]
    public function docuninjaLoaderReady()
    {
        $this->docu_ninja_ready = true;
    }

    #[On('payable-amount')]
    public function payableAmount($payable_amount)
    {
        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id);
        // $this->setContext($invite->key, 'amount', $payable_amount);

        $this->bulkSetContext($invite->key, [
            'amount' => $payable_amount,
            'payment_processed' => null,
        ]);
        $this->under_over_payment = false;
    }

    #[On('payment-method-selected')]
    public function paymentMethodSelected($company_gateway_id, $gateway_type_id, $amount)
    {
        $this->payment_attempt_key = implode(':', [
            $company_gateway_id,
            $gateway_type_id,
            Number::parseFloat($amount),
            md5(json_encode($this->invoices)),
        ]);

        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id);

        $invoices = $this->markPayableInvoicesSent($invite->contact->client_id);
        $payable_invoices = $this->buildPayableInvoicesPayload($invoices);

        $this->bulkSetContext($invite->key, [
            'company_gateway_id' => $company_gateway_id,
            'gateway_type_id' => $gateway_type_id,
            'amount' => array_sum(array_column($payable_invoices, 'amount')),
            'invoices' => $invoices,
            'payable_invoices' => $payable_invoices,
            'pre_payment' => false,
            'is_recurring' => false,
            'payment_processed' => null,
        ]);

        $this->payment_method_accepted = true;

        $company_gateway = CompanyGateway::query()->find($company_gateway_id);

        if (!$company_gateway) {
            return $this->required_fields = false;
        }

        $this->checkRequiredFields($company_gateway, $gateway_type_id);
    }

    #[On('required-fields')]
    public function requiredFieldsFilled()
    {
        $this->required_fields = false;
    }

    private function checkRequiredFields(CompanyGateway $company_gateway, $gateway_type_id)
    {
        $invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id);

        /** @var \App\Models\ClientContact $contact */
        $contact = $this->getContext($invite->key)['contact'];

        $driver = $company_gateway->driver($contact->client);
        $driver->setPaymentMethod($gateway_type_id);

        $fields = $driver->getClientRequiredFields();

        $force_rff = $company_gateway->always_show_required_fields && !empty($fields);

        $this->setContext($invite->key, 'fields', $fields);

        if ($force_rff) {
            return $this->required_fields = true;
        }

        return $this->required_fields = !RFFService::passesExistingValues($contact, $fields);
    }

    #[Computed()]
    public function component(): string
    {

        if (!$this->terms_accepted) {
            return Terms::class;
        }

        /** Async loading of DocuNinja component needs to be done like this. ie. need full payload prior to passing in. */
        if ($this->docu_ninja_active && !$this->signature_accepted) {
            if ($this->docu_ninja_ready) {
                return \App\Livewire\Flow2\DocuNinja::class;
            } else {
                return \App\Livewire\Flow2\DocuNinjaLoader::class;
            }
        } elseif (!$this->signature_accepted && !$this->docu_ninja_active) {
            return Signature::class;
        }

        if ($this->under_over_payment) {
            return UnderOverPayment::class;
        }

        if (!$this->payment_method_accepted) {
            return PaymentMethod::class;
        }

        if ($this->required_fields) {
            return RequiredFields::class;
        }

        return ProcessPayment::class;

    }

    #[Computed()]
    public function componentUniqueId(string $slot = 'main'): string
    {
        // return "purchase-" . md5(microtime());
        $component = $this->component();
        
        return 'purchase-' . md5(implode('|', [
            $slot,
            $component ,
            $this->signing_invitation_id ?? $this->invitation_id,
            $this->signing_key ?? '',
            $component === ProcessPayment::class ? $this->payment_attempt_key : '',
        ]));

    }

    public function mount()
    {

        MultiDB::setDb($this->db);

        // @phpstan-ignore-next-line
        $invite = \App\Models\InvoiceInvitation::with('contact.client', 'company')->withTrashed()->find($this->invitation_id);

        $client = $invite->contact->client;
        $settings = $client->getMergedSettings();

        $this->docu_ninja_active = $invite->company->docuninjaActive(); //Is the company an Active DocuNinja User - or bypass completely if signed!

        $this->bulkSetContext($invite->key, [
            'contact' => $invite->contact,
            'settings' => $settings,
            'db' => $this->db,
            'invitation_id' => $this->invitation_id,
            'entity_type' => 'invoice',
        ]);

        $invoices = $this->loadPayableInvoices($invite->contact->client_id);
                        
        //required fields
        $this->terms_accepted = !$settings->show_accept_invoice_terms;
        $this->signature_accepted = !$settings->require_invoice_signature;
        $this->under_over_payment = $settings->client_portal_allow_over_payment || $settings->client_portal_allow_under_payment;
        $this->required_fields = false;

        /** for multi invoice payments, build a queue of unsigned invitations so the client signs each in turn */
        if ($this->docu_ninja_active && $settings->require_invoice_signature) {
            $unsigned = $invoices->filter(fn ($i) => $i->sync?->dn_completed !== true);

            $queue = $unsigned->map(function ($invoice) use ($invite) {
                return \App\Models\InvoiceInvitation::where('invoice_id', $invoice->id)
                    ->where('client_contact_id', $invite->client_contact_id)
                    ->first();
            })->filter()->values();

            if ($queue->isEmpty()) {
                $this->signature_accepted = true;
            } else {
                $this->signature_accepted = false;
                $this->unsigned_invitation_queue = $queue->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
                $this->advanceSigning($queue->first());
            }
        } elseif ($invoices->every(fn ($i) => $i->sync?->dn_completed === true)) {
            $this->signature_accepted = true;
        }

        $payable_invoices = $this->buildPayableInvoicesPayload($invoices);

        $this->bulkSetContext($invite->key, [
            'variables' => $this->variables,
            'invoices' => $invoices,
            'settings' => $settings,
            'amount' => array_sum(array_column($payable_invoices, 'amount')),
            'payable_invoices' => $payable_invoices,
            'gateway_fee' => false,
        ]);

        $this->dispatch(self::CONTEXT_READY);

    }

    private function loadPayableInvoices(int $client_id)
    {
        return Invoice::withTrashed()
            ->with('client')
            ->whereIn('id', $this->transformKeys($this->invoices))
            ->where('client_id', $client_id)
            ->where('is_deleted', 0)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->isPayable())
            ->values();
    }

    private function markPayableInvoicesSent(int $client_id)
    {
        return Invoice::withTrashed()
            ->with('client')
            ->whereIn('id', $this->transformKeys($this->invoices))
            ->where('client_id', $client_id)
            ->where('is_deleted', 0)
            ->get()
            ->map(function (Invoice $invoice): ?Invoice {
                if ($invoice->status_id === Invoice::STATUS_DRAFT) {
                    $invoice = $invoice->service()->markSent()->save();
                }

                return $invoice->isPayable() ? $invoice : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Invoice>  $invoices
     * @return array<int, array<string, mixed>>
     */
    private function buildPayableInvoicesPayload($invoices): array
    {
        return $invoices->map(function (Invoice $invoice): array {
            $amount = $this->payableAmountFor($invoice);

            return [
                'invoice_id' => $invoice->hashed_id,
                'amount' => $amount,
                'formatted_amount' => Number::formatValue($amount, $invoice->client->currency()),
                'formatted_currency' => Number::formatMoney($amount, $invoice->client),
                'number' => $invoice->number,
                'date' => $invoice->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()),
                'due_date' => $invoice->translateDate($invoice->due_date, $invoice->client->date_format(), $invoice->client->locale()),
                'terms' => $invoice->terms,
            ];
        })->values()->all();
    }

    private function payableAmountFor(Invoice $invoice): float
    {
        if ($invoice->partial > 0) {
            return (float) $invoice->partial;
        }

        if ($invoice->status_id === Invoice::STATUS_DRAFT) {
            return (float) $invoice->amount;
        }

        return (float) $invoice->balance;
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        MultiDB::setDb($this->db);

        //@phpstan-ignore-next-line
        $invite = \App\Models\InvoiceInvitation::with('contact.client', 'company')->withTrashed()->find($this->invitation_id);

        return render('flow2.invoice-pay', ['_key' => $invite->key]);
    }

    public function exception($e, $stopPropagation)
    {

        app('sentry')->captureException($e);
        nlog($e->getMessage());
        $stopPropagation();

    }
}
