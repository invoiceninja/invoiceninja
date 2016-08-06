<?php namespace App\Ninja\Intents;

class CreateInvoiceIntent extends BaseIntent
{
    public function process()
    {
        $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');

        $client = $this->parseClient();
        $invoiceItems = $this->parseInvoiceItems();

        if ($client) {
            $data = [
                'client_id' => $client->id,
                'invoice_items' => $invoiceItems,
            ];

            $invoice = $invoiceRepo->save($data);

            return view('bots.skype.invoice', [
                    'invoice' => $invoice
                ])->render();
        } else {
            return view('bots.skype.message', [
                    'message' => trans('texts.client_not_found')
                ])->render();
        }
    }

    private function parseClient()
    {
        $clientRepo = app('App\Ninja\Repositories\ClientRepository');

        $client = false;

        foreach ($this->data->entities as $param) {
            if ($param->type == 'Client') {
                $client = $clientRepo->findPhonetically($param->entity);
            }
        }

        return $client;
    }

    private function parseInvoiceItems()
    {
        $productRepo = app('App\Ninja\Repositories\ProductRepository');

        $invoiceItems = [];

        foreach ($this->data->compositeEntities as $entity) {
            if ($entity->parentType == 'InvoiceItem') {
                $product = false;
                $qty = 1;
                foreach ($entity->children as $child) {
                    if ($child->type == 'Product') {
                        $product = $productRepo->findPhonetically($child->value);
                    } else {
                        $qty = $child->value;
                    }
                }

                $item = $product->toArray();
                $item['qty'] = $qty;
                $invoiceItems[] = $item;
            }
        }

        return $invoiceItems;
    }

}
