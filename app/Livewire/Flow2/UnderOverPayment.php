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

    public function mount()
    {
        $invoice = $this->context['invoice'];
        $invoice_amount = $invoice->partial > 0 ? $invoice->partial : $invoice->balance;
        $this->currency = $invoice->client->currency();
        $this->payableAmount = Number::formatValue($invoice_amount, $this->currency);
    }

    public function render()
    {
        
        return render('components.livewire.under-over-payments',[
            'settings' => $this->context['settings'],
        ]);
    }
}
