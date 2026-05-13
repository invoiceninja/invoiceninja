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
use App\Models\PurchaseOrder;
use App\Utils\Traits\WithBulkSelection;
use App\Utils\Traits\WithSorting;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersTable extends Component
{
    use WithBulkSelection;
    use WithPagination;
    use WithSorting;

    public int $per_page = 10;

    public array $status = [];

    public string $db;

    public function mount(): void
    {
        MultiDB::setDb($this->db);

        $this->sort_asc = false;

        $this->sort_field = 'date';
    }

    public function updatedStatus(): void
    {
        $this->resetSelection();
    }

    public function sortBy($field): void
    {
        $this->sort_field === $field
            ? $this->sort_asc = ! $this->sort_asc
            : $this->sort_asc = true;

        $this->sort_field = $field;

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
        $local_status = [];

        $query = PurchaseOrder::query()
            ->with('vendor.contacts')
            ->where('company_id', auth()->guard('vendor')->user()->company_id)
            ->whereIn('status_id', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_ACCEPTED])
            ->where('is_deleted', false)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

        if (in_array('sent', $this->status)) {
            $local_status[] = PurchaseOrder::STATUS_SENT;
        }

        if (in_array('accepted', $this->status)) {
            $local_status[] = PurchaseOrder::STATUS_ACCEPTED;
        }

        if (count($local_status) > 0) {
            $query = $query->whereIn('status_id', array_unique($local_status));
        }

        return $query
            ->where('vendor_id', auth()->guard('vendor')->user()->vendor_id)
            ->withTrashed();
    }

    public function render(): Factory|View
    {
        $purchase_orders = $this->buildQuery()->paginate($this->per_page);

        return render('components.livewire.purchase-orders-table', [
            'purchase_orders' => $purchase_orders,
        ]);
    }
}
