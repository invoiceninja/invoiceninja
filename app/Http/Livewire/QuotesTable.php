<?php

namespace App\Http\Livewire;

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

    public function statusChange($status)
    {
        if (in_array($status, $this->status)) {
            return $this->status = array_diff($this->status, [$status]);
        }

        array_push($this->status, $status);
    }

    public function render()
    {
        $query = Quote::query()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->where('company_id', auth('contact')->user()->company->id);
        
        if (in_array('draft', $this->status)) {
            $query = $query->orWhere('status_id', Quote::STATUS_DRAFT);
        }

        if (in_array('sent', $this->status)) {
            $query = $query->orWhere('status_id', Quote::STATUS_SENT);
        }

        if (in_array('approved', $this->status)) {
            $query = $query->orWhere('status_id', Quote::STATUS_APPROVED);
        }

        if (in_array('expired', $this->status)) {
            $query = $query->orWhere('status_id', Quote::STATUS_EXPIRED);
        }

        $query = $query->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $query
        ]);
    }
}
