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

use App\Models\CompanyGateway;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class RFF extends Component
{
    public array $context;

    #[On('passed-required-fields-check')]
    public function continue(): void
    {
        $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());
        $this->dispatch('purchase.next');
    }
    
    public function render()
    {
        $gateway = CompanyGateway::findOrFail($this->context['form']['company_gateway_id']);
        $countries = Cache::get('countries');
        
        return view('billing-portal.v3.rff', [
            'gateway' => $gateway->driver(
                auth()->guard('contact')->user()->client
            ),
            'countries' => $countries,
            'company' => $gateway->company,
        ]);
    }
}
