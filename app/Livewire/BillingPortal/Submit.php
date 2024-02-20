<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal;

use Livewire\Component;
use App\Services\ClientPortal\InstantPayment;

class Submit extends Component
{
    public array $context;

    public function mount()
    {
        // This is right place to check if everything is set up correctly.
        // <input type="hidden" name="action" value="payment">
        // <input type="hidden" name="invoices[]" value="{{ $context['form']['invoice_hashed_id'] ?? '' }}">
        // <input type="hidden" name="payable_invoices[0][amount]" value="{{ $context['form']['payable_amount'] ?? '' }}">
        // <input type="hidden" name="payable_invoices[0][invoice_id]" value="{{ $context['form']['invoice_hashed_id'] ?? '' }}">
        // <input type="hidden" name="company_gateway_id" value="{{ $context['form']['company_gateway_id'] ?? '' }}"/>
        // <input type="hidden" name="payment_method_id" value="{{ $context['form']['payment_method_id'] ?? '' }}"/>
            //hash
            //sidebar = h

        // $request = new \Illuminate\Http\Request([
        //     'sidebar' => 'hidden',
        //     'hash' => $this->context['hash'],
        //     'action' => 'payment',
        //     'invoices[]' => $this->context['form']['invoice_hashed_id'],
        //     'payable_invoices[0][amount]' => $this->context['form']['payable_amount'],
        //     'payable_invoices[0][invoice_id]' => $this->context['form']['invoice_hashed_id'],
        //     'company_gateway_id' => $this->context['form']['company_gateway_id'],
        //     'payment_method_id' => $this->context['form']['payment_method_id'],
        // ]);
        
        // return (new InstantPayment($request))->run();


        $this->dispatch('purchase.submit');
    }

    public function render()
    {
        return <<<'HTML'
            <div></div>    
        HTML;
    }
}
