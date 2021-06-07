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
use App\Models\Client;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentsTable extends Component
{
    use WithPagination, WithSorting;

    public $client;

    public $per_page = 10;

    public $company;

    public function mount($client)
    {

        MultiDB::setDb($this->company->db);

        $this->client = $client;
    }

    public function render()
    {
        $query = $this->client
            ->documents()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.documents-table', [
            'documents' => $query,
        ]);
    }
}
