<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\RecurringInvoice;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class RecurringInvoicesTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->sort_asc = false;

        $this->sort_field = 'date';
    }

    public function render()
    {
        $query = RecurringInvoice::query();

        $query = $query
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('company_id', $this->company->id)
            ->whereIn('status_id', [RecurringInvoice::STATUS_ACTIVE])
            ->orderBy('status_id', 'asc')
            ->with('client')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->withTrashed()
            ->where('is_deleted', false)
            ->paginate($this->per_page);

        return render('components.livewire.recurring-invoices-table', [
            'invoices' => $query,
        ]);
    }
}
