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
use App\Utils\Traits\WithSecureContext;
use Livewire\Component;

class UnderOverPayment extends Component
{
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
        /** Ensure the checkValue is comparing against the same list of invoices as the context */

        $submitted_payable_invoices = $submitted_payable_invoices->keyBy('invoice_id');
        $payable_invoices = [];

        foreach ($context_payable_invoices as $invoice_id => $context_payable_invoice) {
            $submitted_payable_invoice = $submitted_payable_invoices->get($invoice_id, []);
            $amount = Number::parseFloat($submitted_payable_invoice['formatted_amount'] ?? $context_payable_invoice['formatted_amount']);
            $input_amount = Number::roundValue($amount, $currency->precision);
            $invoice_amount = Number::roundValue((float) $context_payable_invoice['amount'], $currency->precision);

            if (! $settings->client_portal_allow_under_payment && $input_amount < $invoice_amount) {
                $this->setError(ctrans('texts.minimum_required_payment', ['amount' => $invoice_amount]));

                return;
            }

            if ($settings->client_portal_allow_under_payment) {
                if ($invoice_amount < $settings->client_portal_under_payment_minimum && $input_amount < $invoice_amount) {
                    $this->setError(ctrans('texts.minimum_required_payment', ['amount' => $invoice_amount]));

                    return;
                } elseif ($invoice_amount >= $settings->client_portal_under_payment_minimum && $input_amount < $settings->client_portal_under_payment_minimum) {
                    $this->setError(ctrans('texts.minimum_required_payment', ['amount' => $settings->client_portal_under_payment_minimum]));

                    return;
                }
            }

            if (! $settings->client_portal_allow_over_payment && $input_amount > $invoice_amount) {
                $this->setError(ctrans('texts.over_payments_disabled'));

                return;
            }

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
