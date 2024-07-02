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
use Livewire\Component;

class UnderOverPayment extends Component
{

    public $context;

    public $payableAmount;

    public $currency;

    public $invoice_amount;

    public $errors = '';

    public function mount()
    {
        $invoice = $this->context['invoice'];
        $this->invoice_amount = $invoice->partial > 0 ? $invoice->partial : $invoice->balance;
        $this->currency = $invoice->client->currency();
        $this->payableAmount = Number::formatValue($this->invoice_amount, $this->currency);
    }

    public function checkValue($value)
    {

        $this->errors = '';

        $settings = $this->context['settings'];
        $input_amount = Number::parseFloat($value);

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

        if(!$this->errors)
            $this->dispatch('payable-amount',  payable_amount: $value );
    }

    public function render()
    {
        
        return render('components.livewire.under-over-payments',[
            'settings' => $this->context['settings'],
        ]);
    }
}
