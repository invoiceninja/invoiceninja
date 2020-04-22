<?php

namespace App\Http\Livewire;

use App\Models\Invoice;
use App\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @todo: Integrate InvoiceFilters
 */
class InvoicesTable extends Component
{
    use WithPagination, WithSorting;

    public $per_page = 10;

    public $status = [];

    public function statusChange($status)
    {
        if (in_array($status, $this->status)) {
            return $this->status = array_diff($this->status, [$status]);
        }

        array_push($this->status, $status);
    }

    public function render()
    {
        $query = Invoice::query();
        $query = $query->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

        // So $status_id will come in three way:
        // paid, unpaid & overdue. Need to transform them to real values.

        if (count($this->status)) {
            $query = $query->whereIn('status_id', $this->status);
        }

        $query = $query->paginate($this->per_page);

        return render('components.livewire.invoices-table', [
            'invoices' => $query
        ]);
    }
}
