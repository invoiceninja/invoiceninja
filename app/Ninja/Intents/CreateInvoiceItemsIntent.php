<?php namespace App\Ninja\Intents;

use Exception;
use App\Models\EntityModel;
use App\Models\Invoice;

class CreateInvoiceItemsIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->invoice();
        $invoiceItems = $this->parseInvoiceItems();

        $data = [
            'public_id' => $invoice->public_id,
            'invoice_items' => array_merge($invoice->invoice_items->toArray(), $invoiceItems),
        ];

        var_dump($data);
        $valid = EntityModel::validate($data, ENTITY_INVOICE, $invoice);

        if ($valid !== true) {
            throw new Exception($valid);
        }

        $invoice = $this->invoiceRepo->save($data, $invoice);

        $invoiceItems = array_slice($invoice->invoice_items->toArray(), count($invoiceItems) * -1);
        $invoiceItemIds = array_map(function($item) {
            return $item['public_id'];
        }, $invoiceItems);

        $this->setEntities(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        return view('bots.skype.invoice', [
                'invoice' => $invoice->load('invoice_items')
            ])->render();
    }
}
