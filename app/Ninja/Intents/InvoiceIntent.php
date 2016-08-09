<?php namespace App\Ninja\Intents;

use Auth;
use Exception;
use App\Models\Invoice;

class InvoiceIntent extends BaseIntent
{
    public function __construct($state, $data)
    {
        $this->invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');

        parent::__construct($state, $data);
    }

    protected function invoice()
    {
        $invoiceId = $this->entity(ENTITY_INVOICE);

        if ( ! $invoiceId) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        $invoice = Invoice::scope($invoiceId)->first();

        if ( ! $invoice) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        if ( ! Auth::user()->can('view', $invoice)) {
            throw new Exception(trans('texts.not_allowed'));
        }

        return $invoice;
    }

    protected function parseInvoiceItems()
    {
        $productRepo = app('App\Ninja\Repositories\ProductRepository');

        $invoiceItems = [];

        if ( ! isset($this->data->compositeEntities) || ! count($this->data->compositeEntities)) {
            return [];
        }

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
