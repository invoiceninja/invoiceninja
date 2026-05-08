<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\Quote;
use App\Utils\Traits\WithBulkSelection;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesTable extends Component
{
    use WithBulkSelection;
    use WithPagination;

    public int $per_page = 10;

    public array $status = [];

    public string $sort = 'status_id';

    public bool $sort_asc = true;

    public int $company_id;

    public string $db;

    public string $sort_field = 'date';

    public function mount(): void
    {
        MultiDB::setDb($this->db);

        $this->sort_asc = false;

        $this->sort_field = 'date';
    }

    public function sortBy(string $field): void
    {
        $this->sort === $field
            ? $this->sort_asc = ! $this->sort_asc
            : $this->sort_asc = true;

        $this->sort = $field;

        $this->resetSelection();
    }

    public function updatedStatus(): void
    {
        $this->resetSelection();
    }

    protected function selectablePageIds(): array
    {
        return $this->buildQuery()
            ->paginate($this->per_page, ['id'], 'page', $this->getPage())
            ->pluck('hashed_id')
            ->toArray();
    }

    private function buildQuery(): Builder
    {
        $query = Quote::query()
            ->with('client.contacts', 'company')
            ->when($this->sort == 'number', function ($q) {
                $q->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . ($this->sort_asc ? 'asc' : 'desc'));
            })
            ->when($this->sort != 'number', function ($q) {
                $q->orderBy($this->sort, ($this->sort_asc ? 'asc' : 'desc'));
            });

        if (count($this->status) > 0) {
            if (in_array('-1', $this->status)) {
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

            if (in_array('5', $this->status)) {
                $query->where('status_id', Quote::STATUS_REJECTED);
            }
        }

        return $query
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('is_deleted', 0)
            ->where('status_id', '<>', Quote::STATUS_DRAFT)
            ->withTrashed();
    }

    public function render(): Factory|View
    {
        $quotes = $this->buildQuery()->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $quotes,
        ]);
    }
}
