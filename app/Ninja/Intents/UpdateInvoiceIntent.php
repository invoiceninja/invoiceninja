<?php namespace App\Ninja\Intents;

use Exception;
use App\Models\EntityModel;
use App\Models\Invoice;

class UpdateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->invoice();

        $data = $this->parseFields();

        dd($data);

        return view('bots.skype.invoice', [
                'invoice' => $invoice
            ])->render();
    }
}
