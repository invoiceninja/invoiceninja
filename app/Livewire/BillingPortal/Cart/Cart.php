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

use App\Libraries\MultiDB;
use App\Models\Subscription;
use Livewire\Component;

class Cart extends Component
{
    public Subscription $subscription;

    public array $context;

    public function handleSubmit()
    {
        $this->dispatch('purchase.next');
    }

    public function showOptionalProductsLabel()
    {
        $optional = [
            ...$this->context['bundle']['optional_recurring_products'] ?? [], 
            ...$this->context['bundle']['optional_one_time_products'] ?? [],
        ];

        return count($optional) > 0;
    }

    public function render()
    {
        return view('billing-portal.v3.cart.cart');
    }
}
