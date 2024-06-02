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

namespace App\Livewire\BillingPortal\Cart;

use App\Models\Subscription;
use Livewire\Component;

class OptionalOneTimeProducts extends Component
{
    public Subscription $subscription;

    public array $context;

    public function quantity($id, $value): void
    {
        $this->dispatch('purchase.context', property: "bundle.optional_one_time_products.{$id}.quantity", value: $value);
    }

    public function render(): \Illuminate\View\View
    {
        return view('billing-portal.v3.cart.optional-one-time-products');
    }
}
