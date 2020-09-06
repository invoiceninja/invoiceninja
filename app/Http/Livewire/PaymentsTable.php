<?php

namespace App\Http\Livewire;

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

    public function mount()
    {
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
