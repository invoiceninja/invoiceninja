<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use Livewire\Component;

class InvoiceSummary extends Component
{
    public $context;

    public $invoice;

    public function mount()
    {
        //@TODO for a single invoice - show all details, for multi-invoices, only show the summaries
        $this->invoice = $this->context['invitation']->invoice;
    }

    public function render()
    {
        return render('components.livewire.invoice-summary',[
            'invoice' => $this->invoice
        ]);
    }
}
