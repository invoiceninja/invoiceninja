<?php

namespace App\Http\Livewire;

use App\Models\Quote;
use App\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesTable extends Component
{
    use WithSorting;
    use WithPagination;

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
        // So $status_id will come in three way:
        // draft, sent, approved & expired. Need to transform them to real values.

        $query = Quote::query()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');
        
        if (count($this->status)) {
            $query = $query->whereIn('status_id', $this->status);
        }

        $query = $query->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $query
        ]);
    }
}
