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

    public function mount()
    {
        $this->sort_asc = false;
    }

    public function render()
    {
        $local_status = [];

        $query = Invoice::query()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

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
            ->where('client_id', auth('contact')->user()->client->id)
            ->where('status_id', '<>', Invoice::STATUS_DRAFT)
            ->paginate($this->per_page);

        return render('components.livewire.invoices-table', [
            'invoices' => $query,
        ]);
    }
}
