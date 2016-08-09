<?php namespace App\Ninja\Intents;

use App\Models\EntityModel;
use App\Models\Invoice;

class CreateInvoiceItemsIntent extends BaseIntent
{
    public function process()
    {
        $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');

        $invoiceId = $this->getCurrentState(ENTITY_INVOICE, true);
        $invoice = Invoice::scope($invoiceId)->first();

        $invoiceItems = $this->parseInvoiceItems();
        $data = [
            'invoice_items' => $invoiceItems
        ];

        $valid = EntityModel::validate($data, ENTITY_INVOICE, $invoice);

        if ($valid !== true) {
            return view('bots.skype.message', [
                    'message' => $valid
                ])->render();
        }

        $invoice = $invoiceRepo->save($data, $invoice);

        $invoiceItems = array_slice($invoice->invoice_items->toArray(), count($invoiceItems) * -1);
        $invoiceItemIds = array_map(function($item) {
            return $item['public_id'];
        }, $invoiceItems);

        $this->setState(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        return view('bots.skype.invoice', [
                'invoice' => $invoice
            ])->render();
    }
}
