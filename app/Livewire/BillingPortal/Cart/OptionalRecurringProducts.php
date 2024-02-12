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

class OptionalRecurringProducts extends Component
{
    public Subscription $subscription;

    public function render(): \Illuminate\View\View
    {
        return view('billing-portal.v3.cart.optional-recurring-products');
    }
}
