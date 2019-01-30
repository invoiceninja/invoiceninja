<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        $clientId = $this->getClientId($data->email) ?: $this->getClientId($data->name);

        if (! $clientId) {
            return false;
        }

        if (isset($data->invoice_number) && $this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) use ($clientId) {
            return [
                'client_id' => $clientId,
                'invoice_number' => isset($data->invoice_number) ? $this->getInvoiceNumber($data->invoice_number) : null,
                'paid' => $this->getFloat($data, 'paid'),
                'po_number' => $this->getString($data, 'po_number'),
                'terms' => $this->getString($data, 'terms'),
                'public_notes' => $this->getString($data, 'public_notes'),
                'private_notes' => $this->getString($data, 'private_notes'),
                'invoice_date_sql' => $this->getDate($data, 'invoice_date'),
                'due_date_sql' => $this->getDate($data, 'due_date'),
                'invoice_items' => [
                    [
                        'product_key' => $this->getString($data, 'item_product'),
                        'notes' => $this->getString($data, 'item_notes') ?: $this->getProduct($data, 'item_product', 'notes', ''),
                        'cost' => $this->getFloat($data, 'item_cost') ?: $this->getProduct($data, 'item_product', 'cost', 0),
                        'qty' => $this->getFloat($data, 'item_quantity') ?: 1,
                        'tax_name1' => $this->getTaxName($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_name1', ''),
                        'tax_rate1' => $this->getTaxRate($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_rate1', 0),
                        'tax_name2' => $this->getTaxName($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_name2', ''),
                        'tax_rate2' => $this->getTaxRate($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_rate2', 0),
                    ],
                ],
            ];
        });
    }
}
