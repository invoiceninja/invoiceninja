<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers\Contact;

use App\Models\Invoice;
use App\Transformers\EntityTransformer;
use App\Utils\Traits\MakesHash;

class InvoiceTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
        //    'invoice_items',
    ];

    protected $availableIncludes = [
    ];

    public function transform(Invoice $invoice)
    {
        return [
            'id' => $this->encodePrimaryKey($invoice->id),
            'amount' => (float) $invoice->amount,
            'balance' => (float) $invoice->balance,
            'status_id' => (int) ($invoice->status_id ?: 1),
            'updated_at' => $invoice->updated_at,
            'archived_at' => $invoice->deleted_at,
            'number' => $invoice->number,
            'discount' => (float) $invoice->discount,
            'po_number' => $invoice->po_number,
            'date' => $invoice->date ?: '',
            'due_date' => $invoice->due_date ?: '',
            'terms' => $invoice->terms ?: '',
            'public_notes' => $invoice->public_notes ?: '',
            'is_deleted' => (bool) $invoice->is_deleted,
            'tax_name1' => $invoice->tax_name1 ? $invoice->tax_name1 : '',
            'tax_rate1' => (float) $invoice->tax_rate1,
            'tax_name2' => $invoice->tax_name2 ? $invoice->tax_name2 : '',
            'tax_rate2' => (float) $invoice->tax_rate2,
            'tax_name3' => $invoice->tax_name3 ? $invoice->tax_name3 : '',
            'tax_rate3' => (float) $invoice->tax_rate3,
            'is_amount_discount' => (bool) ($invoice->is_amount_discount ?: false),
            'invoice_footer' => $invoice->invoice_footer ?: '',
            'partial' => (float) ($invoice->partial ?: 0.0),
            'partial_due_date' => $invoice->partial_due_date ?: '',
            'custom_value1' => (float) $invoice->custom_value1,
            'custom_value2' => (float) $invoice->custom_value2,
            'custom_value3' => (bool) $invoice->custom_value3,
            'custom_value4' => (bool) $invoice->custom_value4,
            'has_tasks' => (bool) $invoice->has_tasks,
            'has_expenses' => (bool) $invoice->has_expenses,
            'custom_text_value1' => $invoice->custom_text_value1 ?: '',
            'custom_text_value2' => $invoice->custom_text_value2 ?: '',
            'line_items' => $invoice->line_items,
        ];
    }
}
