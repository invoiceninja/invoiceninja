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

use App\Models\Invoice;
use App\Services\ClientPortal\PaymentAmountValidator;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\WithSecureContext;
use Livewire\Component;

class UnderOverPayment extends Component
{
    use MakesHash;
    use WithSecureContext;

    public $payableAmount;

    public $currency;

    public $invoice_amount;

    public $errors = '';

    public $payableInvoices = [];

    public $_key;

    public function mount(): void
    {

        $_context = $this->getContext($this->_key);

        $contact = $_context['contact'] ?? auth()->guard('contact')->user();

        $this->invoice_amount = array_sum(array_column($_context['payable_invoices'], 'amount'));
        $this->currency = $contact->client->currency();
        $this->payableInvoices = $_context['payable_invoices'];
    }

    public function checkValue(array $payable_invoices): void
    {
        /** Ensure the checkValue is comparing against the same list of invoices as the context */
        $this->errors = '';
        $_context = $this->getContext($this->_key);
        $settings = $_context['settings'];

        $contact = $_context['contact'] ?? auth()->guard('contact')->user();
        $currency = $contact->client->currency();

        $context_payable_invoices = collect($_context['payable_invoices'] ?? [])->keyBy('invoice_id');
        $submitted_payable_invoices = collect($payable_invoices);
        $submitted_invoice_ids = $submitted_payable_invoices->pluck('invoice_id');

        $has_invalid_invoice_id = $submitted_invoice_ids->contains(function ($invoice_id) use ($context_payable_invoices): bool {
            return ! is_string($invoice_id) || $invoice_id === '' || ! $context_payable_invoices->has($invoice_id);
        });

        if ($context_payable_invoices->isEmpty() || $has_invalid_invoice_id) {
            $this->setError(ctrans('texts.no_payable_invoices_selected'));

            return;
        }

        $invoices = Invoice::on($_context['db'] ?? $contact->getConnectionName())
            ->withTrashed()
            ->where('client_id', $contact->client_id)
            ->where('is_deleted', 0)
            ->whereIn('id', $this->transformKeys($context_payable_invoices->keys()->all()))
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->isPayable())
            ->keyBy('hashed_id');

        if ($invoices->count() !== $context_payable_invoices->count()) {
            $this->setError(ctrans('texts.no_payable_invoices_selected'));

            return;
        }

        $submitted_payable_invoices = $submitted_payable_invoices->keyBy('invoice_id');
        $payable_invoices = [];

        foreach ($context_payable_invoices as $invoice_id => $context_payable_invoice) {
            $submitted_payable_invoice = $submitted_payable_invoices->get($invoice_id, []);
            $formatted_amount = $submitted_payable_invoice['formatted_amount'] ?? $context_payable_invoice['formatted_amount'];
            $error = PaymentAmountValidator::validate(
                $invoices->get($invoice_id),
                $formatted_amount,
                $settings,
                $currency->precision,
            );

            if ($error !== null) {
                $this->setError($error);

                return;
            }

            $amount = Number::parseFloat($formatted_amount);
            $payable_invoices[] = array_merge($context_payable_invoice, [
                'amount' => $amount,
                'formatted_amount' => Number::formatValue($amount, $currency),
                'formatted_currency' => Number::formatMoney($amount, $contact->client),
            ]);
        }

        $input_amount = Number::roundValue(collect($payable_invoices)->sum('amount'), $currency->precision);

        $this->setContext($this->_key, 'payable_invoices', $payable_invoices);
        $this->dispatch('payable-amount', payable_amount: $input_amount);
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $_context = $this->getContext($this->_key);

        return render('flow2.under-over-payments', [
            'settings' => $_context['settings'],
        ]);
    }

    private function setError(string $error): void
    {
        $this->errors = $error;
        $this->dispatch('errorMessageUpdate', errors: $this->errors);
    }
}
