<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use Livewire\Component;

class SubscriptionPlanSwitch extends Component
{
    public $subscription;

    public $target_subscription;

    public $contact;

    public $methods = [];

    public $total;

    public function mount()
    {
        $this->methods = $this->contact->client->service()->getPaymentMethods(100);

        $this->total = 1;
    }

    public function handleBeforePaymentEvents()
    {
        // ..
    }

    public function render()
    {
        return render('components.livewire.subscription-plan-switch');
    }
}
