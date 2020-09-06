<?php

namespace App\Http\Livewire;

use App\Models\Credit;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class CreditsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public function render()
    {
        $query = Credit::query()
            ->where('client_id', auth('contact')->user()->client->id)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.credits-table', [
            'credits' => $query,
        ]);
    }
}
