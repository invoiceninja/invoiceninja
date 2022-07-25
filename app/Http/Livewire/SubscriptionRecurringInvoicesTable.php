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
use App\Models\RecurringInvoice;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionRecurringInvoicesTable extends Component
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
        $query = RecurringInvoice::query()
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', $this->company->id)
            ->whereNotNull('subscription_id')
            ->where('is_deleted', false)
            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.subscriptions-recurring-invoices-table', [
            'recurring_invoices' => $query,
        ]);
    }
}
