<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentsTable extends Component
{
    use WithPagination, WithSorting;

    public $client;

    public $per_page = 10;

    public $company;

    public string $tab = 'documents';

    protected $query;

    public function mount($client)
    {
        MultiDB::setDb($this->company->db);

        $this->client = $client;

        $this->query = $this->documents();
    }

    public function render()
    {
        return render('components.livewire.documents-table', [
            'documents' => $this->query->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')->paginate($this->per_page),
        ]);
    }

    public function updateResources(string $resource)
    {
        $this->tab = $resource;

        switch ($resource) {
            case 'documents':
                $this->query = $this->documents();
                break;

            case 'credits':
                $this->query = $this->credits();
                break;

            case 'expenses':
                $this->query = $this->expenses();
                break;

            default:
                $this->query = $this->documents();
                break;
        }
    }

    protected function documents()
    {
        return $this->client->documents();
    }

    protected function credits()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Credit::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function expenses()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Expense::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }
}
