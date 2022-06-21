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

namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use Livewire\Component;

class PayNowDropdown extends Component
{
    public $total;

    public $methods;

    public $company;

    public function mount(int $total)
    {
        MultiDB::setDb($this->company->db);

        $this->total = $total;

        $this->methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($total);
    }

    public function render()
    {
        return render('components.livewire.pay-now-dropdown');
    }
}
