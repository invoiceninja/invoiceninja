<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use App\Models\Document;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentsTable extends Component
{
    use WithPagination, WithSorting;

    public $client;

    public $per_page = 10;

    public $status = [
        'resources',
    ];

    public function mount($client)
    {
        $this->client = $client;
    }

    public function statusChange($status)
    {
        if (in_array($status, $this->status)) {
            return $this->status = array_diff($this->status, [$status]);
        }

        array_push($this->status, $status);
    }

    public function render()
    {
        $query = $this->client->documents();

        if (in_array('resources', $this->status) && ! in_array('client', $this->status)) {
            $query = $query->where('documentable_type', '!=', \App\Models\Client::class);
        }

        if (in_array('client', $this->status) && ! in_array('resources', $this->status)) {
            $query = $query->where('documentable_type', \App\Models\Client::class);
        }

        $query = $query
            ->where('is_public', true)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.documents-table', [
            'documents' => $query,
        ]);
    }
}
