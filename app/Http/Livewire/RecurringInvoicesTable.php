<?php

namespace App\Http\Livewire;

use App\Models\RecurringInvoice;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class RecurringInvoicesTable extends Component
{
    use WithPagination, WithSorting;

    public $per_page = 10;

    public function render()
    {
        $query = RecurringInvoice::query();

        // ->whereClientId(auth()->user()->client->id) // auth()->user() null.

        $query = $query
            ->whereIn('status_id', [RecurringInvoice::STATUS_PENDING, RecurringInvoice::STATUS_ACTIVE, RecurringInvoice::STATUS_COMPLETED])
            ->orderBy('status_id', 'asc')
            ->with('client')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.recurring-invoices-table', [
            'invoices' => $query
        ]);
    }
}
