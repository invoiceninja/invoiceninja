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
use App\Models\Credit;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class CreditsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);
    }

    public function render()
    {
        $query = Credit::query()
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', $this->company->id)
            ->where('status_id', '<>', Credit::STATUS_DRAFT)
            ->where('is_deleted', 0)
            ->where(function ($query) {
                $query->whereDate('due_date', '>=', now())
                      ->orWhereNull('due_date');
                //->orWhere('due_date', '=', '');
            })
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.credits-table', [
            'credits' => $query,
        ]);
    }
}
