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

    public function mount()
    {

        $this->invoice_amount = array_sum(array_column($this->getContext()['payable_invoices'], 'amount'));
        $this->currency = $this->getContext()['invitation']->contact->client->currency();
        $this->payableInvoices = $this->getContext()['payable_invoices'];
    }

    public function checkValue(array $payableInvoices)
    {
        $this->errors = '';

        $settings = $this->getContext()['settings'];
        $input_amount = 0;

        foreach($payableInvoices as $key=>$invoice){
            $input_amount += Number::parseFloat($invoice['formatted_amount']);
            $payableInvoices[$key]['amount'] = $input_amount;
        }

        if($settings->client_portal_allow_under_payment && $settings->client_portal_under_payment_minimum != 0)
        {
            if($input_amount <= $settings->client_portal_under_payment_minimum){
                // return error message under payment too low.
                $this->errors = ctrans('texts.minimum_required_payment', ['amount' => $settings->client_portal_under_payment_minimum]);
                $this->dispatch('errorMessageUpdate', errors: $this->errors);
            }
        }

        if(!$settings->client_portal_allow_over_payment && ($input_amount > $this->invoice_amount)){
            $this->errors = ctrans('texts.over_payments_disabled');
            $this->dispatch('errorMessageUpdate', errors: $this->errors);

        }

        if(!$this->errors){
            $this->getContext()['payable_invoices'] = $payableInvoices;
            $this->dispatch('payable-amount',  payable_amount: $input_amount );
        }
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return render('flow2.under-over-payments');
    }
}
