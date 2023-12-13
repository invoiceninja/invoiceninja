<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Libraries\MultiDB;
use Livewire\Component;

class PayNowDropdown extends Component
{
    public $total;

    public $methods;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($this->total);
    }

    public function render()
    {
        return render('components.livewire.pay-now-dropdown');
    }
}
