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

namespace App\Livewire\Flow2;

use App\Utils\Number;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Utils\Traits\WithSecureContext;

class InvoiceSummary extends Component
{
    use WithSecureContext;

    public $invoices;

    public $amount;

    public function mount()
    {
        //@TODO for a single invoice - show all details, for multi-invoices, only show the summaries
        // $this->invoices = $this->getContext()['invoices']; // $this->context['invitation']->invoice;
        
        $contact = $this->getContext()['contact'];
        $this->invoices = $this->getContext()['payable_invoices'];
        $this->amount = Number::formatMoney($this->getContext()['amount'], $contact->client);

    }

    #[On(self::CONTEXT_UPDATE)]
    public function onContextUpdate(): void
    {
        // refactor logic for updating the price for eg if it changes with under/over pay
        $contact = $this->getContext()['contact'];
        $this->invoices = $this->getContext()['payable_invoices'];
        $this->amount = Number::formatMoney($this->getContext()['amount'], $contact->client);

        // $this->invoices = $this->getContext()['invoices'];
    }


    public function downloadDocument($invoice_hashed_id)
    {

        $contact = $this->getContext()['contact'];
        $_invoices = $this->getContext()['invoices'];
        $i = $_invoices->first(function ($i) use($invoice_hashed_id){
            return $i->hashed_id == $invoice_hashed_id;
        });

        $file_name = $i->numberFormatter().'.pdf';

        $file = (new \App\Jobs\Entity\CreateRawPdf($i->invitations()->where('client_contact_id', $contact->id)->first()))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $contact = $this->getContext()['contact'];
        
        return render('flow2.invoices-summary', [
            'client' => $contact->client,
        ]);
        
    }
}
