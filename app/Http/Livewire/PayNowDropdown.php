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

class PayNowDropdown extends Component
{
    public $total;

    public $methods;

    public function mount(int $total)
    {
        $this->total = $total;

        $this->methods = auth()->user()->client->service()->getPaymentMethods($total);
    }

    public function render()
    {
        return render('components.livewire.pay-now-dropdown');
    }
}
