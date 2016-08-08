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
        $invoiceItemIds = array_map(function($item) {
            return $item['public_id'];
        }, $invoice->invoice_items->toArray());

        $this->setState(ENTITY_CLIENT, [$client->public_id]);
        $this->setState(ENTITY_INVOICE, [$invoice->public_id]);
        $this->setState(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        return view('bots.skype.invoice', [
                'invoice' => $invoice
            ])->render();
    }
}
