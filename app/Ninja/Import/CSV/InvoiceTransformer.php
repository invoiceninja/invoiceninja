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
				//'invoice_status_id' => isset($data->invoice_status) ? $this->getInvoiceStatus($data->invoice_status) : null,
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
                'invoice_number' => isset($data->invoice_number) ? $this->getInvoiceNumber($data->invoice_number) : null,
				'discount' => $this->getFloat($data, 'discount'),
				'po_number' => $this->getString($data, 'po_number'),
				'invoice_date_sql' => $this->getDate($data, 'invoice_date'),
				'due_date_sql' => $this->getDate($data, 'due_date'),
				'terms' => $this->getString($data, 'terms'),
				'public_notes' => $this->getString($data, 'public_notes'),
				'is_deleted' => $clientId ? false : true,
				//'is_recurring' =>
				//'frequency_id => getNumber($data, 'frequency_id')
				'start_date' => isset($data->start_date) ? date('Y-m-d', strtotime($data->start_date)) : null,
				'end_date' => isset($data->end_date) ? date('Y-m-d', strtotime($data->end_date)) : null,
				'last_sent_date' => isset($data->last_sent_date) ? date('Y-m-d', strtotime($data->last_sent_date)) : null,
				//'recurring_invoice_id' => isset($data->recurring_invoice) ? $this->getRecurringExpenseId($data->recurring_invoice) : null,
				'tax_name1' => $this->getTaxName($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_name1', ''),
                'tax_rate1' => $this->getTaxRate($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_rate1', 0),
				'amount' => $this->getFloat($data, 'amount'),
				'balance' => $this->getFloat($data, 'balance'),
				//'public_id' =>
				//'invoice_design_id' =>
				//'invoice_type_id =>
				//'quote_id =>
				//'quote_invoice_id =>
				'custom_value1' => $this->getString($data, 'custom1'),
                'custom_value2' => $this->getString($data, 'custom2'),
				//'custom_taxes1' => $this->getString($data, 'custom1'),
                //'custom_taxes2' => $this->getString($data, 'custom2'),
				
				//'is_amount_discount' =>
				//'invoice_footer' =>
				//'partial' =>
				//'has_tasks' =>
				//'auto_bill' =>
				//'custom_text_values1' =>
				//'custom_text_values2' =>
				//'has_expenses' =>
				'tax_name2' => $this->getTaxName($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_name2', ''),
                'tax_rate2' => $this->getTaxRate($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_rate2', 0),
                //'client_enable_auto_bill' =>
				//'is_public' =>
				'private_notes' => $this->getString($data, 'private_notes'),
				'partial_due_date' => isset($data->partial_due_date) ? date('Y-m-d', strtotime($data->partial_due_date)) : null,				
                'invoice_items' => [
                    [
						//account_id
						//user_id
						invoice_id => isset($data->invoice) ? $this->getInvoiceId($data->vendor) : null,
						//product_id
						'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
						'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
						'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
                        'product_key' => $this->getString($data, 'item_product'),
                        'notes' => $this->getString($data, 'item_notes') ?: $this->getProduct($data, 'item_product', 'notes', ''),
                        'cost' => $this->getFloat($data, 'item_cost') ?: $this->getProduct($data, 'item_product', 'cost', 0),
                        'qty' => $this->getFloat($data, 'item_quantity') ?: 1,
                        'tax_name1' => $this->getTaxName($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_name1', ''),
                        'tax_rate1' => $this->getTaxRate($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_rate1', 0),
                        //'public_id' => isset($data->'public') ? $this->getInvoicePublicId($data->'public') : null,
						'custom_value1' => $this->getString($data, 'custom1'),
						'custom_value2' => $this->getString($data, 'custom2'),
						'tax_name2' => $this->getTaxName($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_name2', ''),
                        'tax_rate2' => $this->getTaxRate($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_rate2', 0),
						//invoice_item_type_id
						//'discount'
					],
                ],
            ];
        });
    }
}
