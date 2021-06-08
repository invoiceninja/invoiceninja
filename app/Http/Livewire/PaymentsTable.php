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

use App\Libraries\MultiDB;
use App\Models\Payment;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentsTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;

    public $user;

    public $company;
    
    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->user = auth()->user();

    }

    public function render()
    {
        $query = Payment::query()
            ->with('type', 'client')
            ->where('client_id', auth('contact')->user()->client->id)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.payments-table', [
            'payments' => $query,
        ]);
    }
}
