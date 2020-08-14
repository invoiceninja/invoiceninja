<?php

namespace App\Http\Livewire;

use App\Models\Document;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class DownloadsTable extends Component
{
    use WithPagination, WithSorting;

    public $per_page = 10;

    public function render()
    {
        $query = auth()->user()->client->documents()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.downloads-table', [
            'downloads' => $query,
        ]);
    }
}
