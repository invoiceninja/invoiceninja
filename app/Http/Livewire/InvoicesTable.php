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
use App\Models\Invoice;
use App\Utils\Traits\WithSorting;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicesTable extends Component
{
    use WithPagination, WithSorting;

    public $per_page = 10;

    public $status = [];

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->sort_asc = false;

        $this->sort_field = 'date';
    }

    public function render()
    {
        $local_status = [];

        $query = Invoice::query()
            ->with('client.gateway_tokens', 'client.contacts')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', false);

        if (in_array('paid', $this->status)) {
            $local_status[] = Invoice::STATUS_PAID;
        }

        if (in_array('unpaid', $this->status)) {
            $local_status[] = Invoice::STATUS_SENT;
            $local_status[] = Invoice::STATUS_PARTIAL;
        }

        if (in_array('overdue', $this->status)) {
            $local_status[] = Invoice::STATUS_SENT;
            $local_status[] = Invoice::STATUS_PARTIAL;
        }

        if (count($local_status) > 0) {
            $query = $query->whereIn('status_id', array_unique($local_status));
        }

        if (in_array('overdue', $this->status)) {
            $query = $query->where(function ($query) {
                $query
                    ->orWhere('due_date', '<', Carbon::now())
                    ->orWhere('partial_due_date', '<', Carbon::now());
            });
        }

        $query = $query
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('status_id', '<>', Invoice::STATUS_DRAFT)
            ->where('status_id', '<>', Invoice::STATUS_CANCELLED)
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.invoices-table', [
            'invoices' => $query,
            'gateway_available' => ! empty(auth()->user()->client->service()->getPaymentMethods(-1)),
        ]);
    }
}
