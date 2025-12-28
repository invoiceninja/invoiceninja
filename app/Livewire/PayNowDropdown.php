<?php

/**
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

    public $db;

    public $company_id;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($this->total);
    }

    public function render()
    {
        return render('components.livewire.pay-now-dropdown');
    }
}
