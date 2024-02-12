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

class OneTimeProducts extends Component
{
    public Subscription $subscription;

    public array $context;

    public function render()
    {
        return view('billing-portal.v3.cart.one-time-products');
    }
}
