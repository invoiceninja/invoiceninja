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
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionInvoicesTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public function render()
    {
        $query = Invoice::query()
            ->where('client_id', auth('contact')->user()->client->id)
            ->whereNotNull('subscription_id')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->where('balance', '=', 0)
            ->paginate($this->per_page);

        return render('components.livewire.subscriptions-invoices-table', [
            'invoices' => $query,
        ]);
    }
}
