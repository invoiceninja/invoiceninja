<?php namespace App\Ninja\Intents;

use Exception;
use App\Models\EntityModel;

class CreateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
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

        var_dump($data);
        $valid = EntityModel::validate($data, ENTITY_INVOICE);

        if ($valid !== true) {
            throw new Exception($valid);
        }

        $invoice = $this->invoiceRepo->save($data);
        $invoiceItemIds = array_map(function($item) {
            return $item['public_id'];
        }, $invoice->invoice_items->toArray());

        $this->setEntityType(ENTITY_INVOICE);
        $this->setEntities(ENTITY_CLIENT, $client->public_id);
        $this->setEntities(ENTITY_INVOICE, $invoice->public_id);
        $this->setEntities(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        return view('bots.skype.invoice', [
                'invoice' => $invoice
            ])->render();
    }
}
