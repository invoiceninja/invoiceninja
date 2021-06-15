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
            ->where('client_id', auth('contact')->user()->client->id)
            ->whereNotNull('subscription_id')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.subscriptions-recurring-invoices-table', [
            'recurring_invoices' => $query,
        ]);
    }
}
