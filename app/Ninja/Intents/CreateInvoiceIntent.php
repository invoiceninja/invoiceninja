<?php namespace App\Ninja\Intents;

use App\Models\EntityModel;

class CreateInvoiceIntent extends BaseIntent
{
    public function process()
    {
        $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');

        $client = $this->parseClient();
        $invoiceItems = $this->parseInvoiceItems();

        if ( ! $client) {
            return view('bots.skype.message', [
                    'message' => trans('texts.client_not_found')
                ])->render();
        }

        $data = [
            'client_id' => $client->id,
            'invoice_items' => $invoiceItems,
        ];

        $valid = EntityModel::validate($data, ENTITY_INVOICE);

        if ($valid !== true) {
            return view('bots.skype.message', [
                    'message' => $valid
                ])->render();
        }

        $invoice = $invoiceRepo->save($data);

        $this->addState([$invoice->entityKey()]);

        return view('bots.skype.invoice', [
                'invoice' => $invoice
            ])->render();
    }
}
