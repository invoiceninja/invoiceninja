<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\RecurringInvoices;

use App\Models\Invoice;
use Livewire\Component;

class UpdateAutoBilling extends Component
{
    /** @var \App\Models\RecurringInvoice */
    public $invoice;

    public function updateAutoBilling(): void
    {
        if ($this->invoice->auto_bill == 'optin' || $this->invoice->auto_bill == 'optout') {
            $this->invoice->auto_bill_enabled = ! $this->invoice->auto_bill_enabled;
            $this->invoice->saveQuietly();

            Invoice::where('recurring_id', $this->invoice->id)
                        ->whereIn('status_id', [2,3])
                        ->where('is_deleted', 0)
                        ->where('balance', '>', 0)
                        ->update(['auto_bill_enabled' => $this->invoice->auto_bill_enabled]);
        }
    }

    public function render()
    {
        return render('components.livewire.recurring-invoices-switch-autobilling');
    }
}
