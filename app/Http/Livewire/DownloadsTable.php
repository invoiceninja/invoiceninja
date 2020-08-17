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

    public $status = [
        'resources',
    ];

    public function statusChange($status)
    {
        if (in_array($status, $this->status)) {
            return $this->status = array_diff($this->status, [$status]);
        }

        array_push($this->status, $status);
    }

    public function render()
    {
        $query = auth()->user()->client->documents();

        if (in_array('resources', $this->status) && !in_array('client', $this->status)) {
            $query = $query->where('documentable_type', '!=', 'App\Models\Client');
        }

        if (in_array('client', $this->status) && !in_array('resources', $this->status)) {
            $query = $query->where('documentable_type', 'App\Models\Client');
        }

        $query = $query
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.downloads-table', [
            'downloads' => $query,
        ]);
    }
}
