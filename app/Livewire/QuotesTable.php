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

    public string $sort = 'date';

    public bool $sort_asc = false;

    public int $company_id;

    public string $db;

    public function mount(): void
    {
        MultiDB::setDb($this->db);
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

    public function toggleStatus(string $value): void
    {
        if (in_array($value, $this->status)) {
            $this->status = array_values(array_diff($this->status, [$value]));
        } else {
            $this->status[] = $value;
        }

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
            $client = auth()->guard('contact')->user()->client;
            $today = now($client->timezone()->name)->toDateString();
            $statuses = array_map('strval', $this->status);

            $query->where(function (Builder $query) use ($statuses, $today) {
                if (in_array((string) Quote::STATUS_EXPIRED, $statuses, true)) {
                    $query->orWhere(function (Builder $query) use ($today) {
                        $query->where('status_id', Quote::STATUS_SENT)
                            ->where('due_date', '<', $today);
                    });
                }

                if (in_array((string) Quote::STATUS_SENT, $statuses, true)) {
                    $query->orWhere(function (Builder $query) use ($today) {
                        $query->where('status_id', Quote::STATUS_SENT)
                            ->where(function (Builder $query) use ($today) {
                                $query->whereNull('due_date')->orWhere('due_date', '>=', $today);
                            });
                    });
                }

                if (in_array((string) Quote::STATUS_APPROVED, $statuses, true)) {
                    $query->orWhereIn('status_id', [Quote::STATUS_APPROVED, Quote::STATUS_CONVERTED]);
                }

                if (in_array((string) Quote::STATUS_REJECTED, $statuses, true)) {
                    $query->orWhere('status_id', Quote::STATUS_REJECTED);
                }

                if (in_array((string) Quote::STATUS_CANCELLED, $statuses, true)) {
                    $query->orWhere('status_id', Quote::STATUS_CANCELLED);
                }
            });
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
