<?php

namespace App\Ninja\Intents;

use App\Models\EntityModel;
use Exception;

class CreateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $client = $this->requestClient();
        $invoiceItems = $this->requestInvoiceItems();

        if (! $client) {
            throw new Exception(trans('texts.client_not_found'));
        }

        $data = array_merge($this->requestFields(), [
            'client_id' => $client->id,
            'invoice_items' => $invoiceItems,
        ]);

        //var_dump($data);

        $valid = EntityModel::validate($data, ENTITY_INVOICE);

        if ($valid !== true) {
            throw new Exception($valid);
        }

        $invoiceService = app('App\Services\InvoiceService');
        $invoice = $invoiceService->save($data);

        $invoiceItemIds = array_map(function ($item) {
            return $item['public_id'];
        }, $invoice->invoice_items->toArray());

        $this->setStateEntityType(ENTITY_INVOICE);
        $this->setStateEntities(ENTITY_CLIENT, $client->public_id);
        $this->setStateEntities(ENTITY_INVOICE, $invoice->public_id);
        $this->setStateEntities(ENTITY_INVOICE_ITEM, $invoiceItemIds);

        return $this->createResponse(SKYPE_CARD_RECEIPT, $invoice->present()->skypeBot);
    }
}
