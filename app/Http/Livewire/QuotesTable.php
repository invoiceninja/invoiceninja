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
use App\Models\Quote;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;

    public $status = [];

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);
    }

    public function render()
    {
        $query = Quote::query()
            ->with('client.gateway_tokens', 'company', 'client.contacts')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

        if (count($this->status) > 0) {

            /* Special filter for expired*/
            if (in_array('-1', $this->status)) {
                // $query->whereDate('due_date', '<=', now()->startOfDay());

                $query->where(function ($query) {
                    $query->whereDate('due_date', '<=', now()->startOfDay())
                          ->whereNotNull('due_date')
                          ->where('status_id', '<>', Quote::STATUS_CONVERTED);
                });
            }

            if (in_array('2', $this->status)) {
                $query->where(function ($query) {
                    $query->whereDate('due_date', '>=', now()->startOfDay())
                          ->orWhereNull('due_date');
                })->where('status_id', Quote::STATUS_SENT);
            }

            if (in_array('3', $this->status)) {
                $query->whereIn('status_id', [Quote::STATUS_APPROVED, Quote::STATUS_CONVERTED]);
            }
        }

        $query = $query
            ->where('company_id', $this->company->id)
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('status_id', '<>', Quote::STATUS_DRAFT)
            // ->where(function ($query){
            //     $query->whereDate('due_date', '>=', now())
            //           ->orWhereNull('due_date');
            // })
            ->where('is_deleted', 0)
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $query,
        ]);
    }
}
