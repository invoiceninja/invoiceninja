<?php

namespace App\Ninja\Intents;

use App\Models\EntityModel;
use App\Models\Invoice;
use Exception;

class UpdateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->stateInvoice();
        $invoiceItems = $this->requestInvoiceItems();

        $data = array_merge($this->requestFields(), [
            'public_id' => $invoice->public_id,
            'invoice_items' => array_merge($invoice->invoice_items->toArray(), $invoiceItems),
        ]);

        // map the cost and qty fields to the invoice items
        if (isset($data['cost']) || isset($data['quantity'])) {
            foreach ($data['invoice_items'] as $key => $item) {
                // if it's new or we recently created it
                if (empty($item['public_id']) || in_array($item['public_id'], $this->entities(ENTITY_INVOICE_ITEM))) {
                    $data['invoice_items'][$key]['cost'] = isset($data['cost']) ? $data['cost'] : $item['cost'];
                    $data['invoice_items'][$key]['qty'] = isset($data['quantity']) ? $data['quantity'] : $item['qty'];
                }
            }
        }

        //var_dump($data);

        $valid = EntityModel::validate($data, ENTITY_INVOICE, $invoice);

        if ($valid !== true) {
            throw new Exception($valid);
        }

        $invoice = $this->invoiceRepo->save($data, $invoice);

        $invoiceItems = array_slice($invoice->invoice_items->toArray(), count($invoiceItems) * -1);
        $invoiceItemIds = array_map(function ($item) {
            return $item['public_id'];
        }, $invoiceItems);

        $this->setStateEntities(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        $response = $invoice
            ->load('invoice_items')
            ->present()
            ->skypeBot;

        return $this->createResponse(SKYPE_CARD_RECEIPT, $response);
    }
}
