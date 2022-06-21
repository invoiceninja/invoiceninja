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
use Livewire\Component;

class RecurringInvoiceCancellation extends Component
{
    /**
     * @var RecurringInvoice
     */
    public $invoice;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);
    }

    public function render()
    {
        return render('components.livewire.recurring-invoice-cancellation');
    }

    public function processCancellation()
    {
        if ($this->invoice->subscription) {
            return $this->invoice->subscription->service()->handleCancellation($this->invoice);
        }

        return redirect()->route('client.recurring_invoices.request_cancellation', ['recurring_invoice' => $this->invoice->hashed_id]);
    }
}
