<?php

namespace App\Http\Livewire;

use App\Models\Quote;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;
    public $status = [];

    public function render()
    {
        $query = Quote::query()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

        if (count($this->status) > 0) {
            $query = $query->whereIn('status_id', $this->status);
        }

        $query = $query
            ->where('client_id', auth('contact')->user()->client->id)
            ->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $query,
        ]);
    }
}
