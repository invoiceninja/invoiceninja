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

use App\Models\RecurringInvoice;
use Livewire\Component;

class RecurringInvoiceCancellation extends Component
{
    /**
     * @var RecurringInvoice
     */
    public $invoice;

    public function processCancellation()
    {
        if ($this->invoice->subscription) {
            return $this->invoice->subscription->service()->handleCancellation();
        }

        return redirect()->route('client.recurring_invoices.request_cancellation', ['recurring_invoice' => $this->invoice->hashed_id]);
    }

    public function render()
    {
        return render('components.livewire.recurring-invoice-cancellation');
    }
}
