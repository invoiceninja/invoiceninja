<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
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
        $this->updateResources(request()->tab ?: $this->tab);

        return render('components.livewire.documents-table', [
            'documents' => $this->query
                ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
                ->withTrashed()
                ->paginate($this->per_page),
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
               // $this->query = $this->expenses();
                break;

            case 'invoices':
                $this->query = $this->invoices();
                break;

            case 'payments':
                $this->query = $this->payments();
                break;

            case 'projects':
                $this->query = $this->projects();
                break;

            case 'quotes':
                $this->query = $this->quotes();
                break;

            case 'recurringInvoices':
                $this->query = $this->recurringInvoices();
                break;

            case 'tasks':
                $this->query = $this->tasks();
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

    protected function invoices()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Invoice::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function payments()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Payment::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function projects()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Project::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function quotes()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Quote::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function recurringInvoices()
    {
        return Document::query()
            ->whereHasMorph('documentable', [RecurringInvoice::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }

    protected function tasks()
    {
        return Document::query()
            ->whereHasMorph('documentable', [Task::class], function ($query) {
                $query->where('client_id', $this->client->id);
            });
    }
}
