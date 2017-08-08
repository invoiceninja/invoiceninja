<?php

namespace App\Ninja\Intents;

use App\Models\Invoice;
use App\Models\InvoiceStatus;
use Auth;
use Exception;

class InvoiceIntent extends BaseIntent
{
    protected $fieldMap = [
        'deposit' => 'partial',
        'due' => 'due_date',
    ];

    public function __construct($state, $data)
    {
        $this->invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');

        parent::__construct($state, $data);
    }

    protected function stateInvoice()
    {
        $invoiceId = $this->stateEntity(ENTITY_INVOICE);

        if (! $invoiceId) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        $invoice = Invoice::scope($invoiceId)->first();

        if (! $invoice) {
            throw new Exception(trans('texts.intent_not_supported'));
        }

        if (! Auth::user()->can('view', $invoice)) {
            throw new Exception(trans('texts.not_allowed'));
        }

        return $invoice;
    }

    protected function requestInvoiceItems()
    {
        $productRepo = app('App\Ninja\Repositories\ProductRepository');

        $invoiceItems = [];
        $offset = 0;

        if (! isset($this->data->compositeEntities) || ! count($this->data->compositeEntities)) {
            return [];
        }

        foreach ($this->data->compositeEntities as $entity) {
            if ($entity->parentType == 'InvoiceItem') {
                $product = false;
                $qty = 1;
                foreach ($entity->children as $child) {
                    if ($child->type == 'Product') {
                        // check additional words in product name
                        // https://social.msdn.microsoft.com/Forums/azure/en-US/a508e039-0f76-4280-8156-4a017bcfc6dd/none-of-your-composite-entities-contain-all-of-the-highlighted-entities?forum=LUIS
                        $query = $this->data->query;
                        $startIndex = strpos($query, $child->value, $offset);
                        $endIndex = strlen($query);
                        $offset = $startIndex + 1;
                        foreach ($this->data->entities as $indexChild) {
                            if ($indexChild->startIndex > $startIndex) {
                                $endIndex = min($endIndex, $indexChild->startIndex);
                            }
                        }
                        $productName = substr($query, $startIndex, ($endIndex - $startIndex));
                        $product = $productRepo->findPhonetically($productName);
                    } else {
                        $qty = $child->value;
                    }
                }

                if ($product) {
                    $item['qty'] = $qty;
                    $item['product_key'] = $product->product_key;
                    $item['cost'] = $product->cost;
                    $item['notes'] = $product->notes;

                    /*
                    if ($taxRate = $product->default_tax_rate) {
                        $item['tax_name1'] = $taxRate->name;
                        $item['tax_rate1'] = $taxRate->rate;
                    }
                    */
                    
                    $invoiceItems[] = $item;
                }
            }
        }

        /*
        if ( ! count($invoiceItems)) {
            foreach ($this->data->entities as $param) {
                if ($param->type == 'Product') {
                    $product = $productRepo->findPhonetically($param->entity);
                }
            }
        }
        */

        return $invoiceItems;
    }

    protected function loadStatuses($entityType)
    {
        $statusIds = [];
        $statuses = $this->getFields('Filter');

        foreach ($statuses as $status) {
            if ($statusId = InvoiceStatus::getIdFromAlias($status)) {
                $statusIds[] = $statusId;
            }
        }

        if (count($statusIds) || $this->hasField('Filter', 'all')) {
            session(['entity_status_filter:' . $entityType => join(',', $statusIds)]);
        }
    }
}
