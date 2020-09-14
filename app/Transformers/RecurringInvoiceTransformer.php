<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;

class RecurringInvoiceTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
    //    'invoice_items',
    ];

    protected $availableIncludes = [
    //    'invitations',
    //    'payments',
    //    'client',
    //    'documents',
    ];

    /*
        public function includeInvoiceItems(Invoice $invoice)
        {
            $transformer = new InvoiceItemTransformer($this->serializer);

            return $this->includeCollection($invoice->invoice_items, $transformer, ENTITY_INVOICE_ITEM);
        }

        public function includeInvitations(Invoice $invoice)
        {
            $transformer = new InvitationTransformer($this->account, $this->serializer);

            return $this->includeCollection($invoice->invitations, $transformer, ENTITY_INVITATION);
        }

        public function includePayments(Invoice $invoice)
        {
            $transformer = new PaymentTransformer($this->account, $this->serializer, $invoice);

            return $this->includeCollection($invoice->payments, $transformer, ENTITY_PAYMENT);
        }

        public function includeClient(Invoice $invoice)
        {
            $transformer = new ClientTransformer($this->account, $this->serializer);

            return $this->includeItem($invoice->client, $transformer, ENTITY_CLIENT);
        }

        public function includeExpenses(Invoice $invoice)
        {
            $transformer = new ExpenseTransformer($this->account, $this->serializer);

            return $this->includeCollection($invoice->expenses, $transformer, ENTITY_EXPENSE);
        }

        public function includeDocuments(Invoice $invoice)
        {
            $transformer = new DocumentTransformer($this->account, $this->serializer);

            $invoice->documents->each(function ($document) use ($invoice) {
                $document->setRelation('invoice', $invoice);
            });

            return $this->includeCollection($invoice->documents, $transformer, ENTITY_DOCUMENT);
        }
    */
    public function transform(RecurringInvoice $invoice)
    {
        return [
            'id' => $this->encodePrimaryKey($invoice->id),
            'user_id' => $this->encodePrimaryKey($invoice->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($invoice->assigned_user_id),
            'amount' => (float) $invoice->amount,
            'balance' => (float) $invoice->balance,
            'client_id' => (string) $invoice->client_id,
            'vendor_id' => (string) $this->encodePrimaryKey($invoice->vendor_id),
            'status_id' => (string) ($invoice->status_id ?: 1),
            'design_id' => (string) $this->encodePrimaryKey($invoice->design_id),
            'created_at' => (int) $invoice->created_at,
            'updated_at' => (int) $invoice->updated_at,
            'archived_at' => (int) $invoice->deleted_at,
            'is_deleted' => (bool) $invoice->is_deleted,
            'number' => $invoice->number ?: '',
            'discount' => (float) $invoice->discount,
            'po_number' => $invoice->po_number ?: '',
            'date' => $invoice->date ?: '',
            'last_sent_date' => $invoice->last_sent_date ?: '',
            'next_send_date' => $invoice->next_send_date ?: '',
            'due_date' => $invoice->due_date ?: '',
            'terms' => $invoice->terms ?: '',
            'public_notes' => $invoice->public_notes ?: '',
            'private_notes' => $invoice->private_notes ?: '',
            'uses_inclusive_taxes' => (bool) $invoice->uses_inclusive_taxes,
            'tax_name1' => $invoice->tax_name1 ? $invoice->tax_name1 : '',
            'tax_rate1' => (float) $invoice->tax_rate1,
            'tax_name2' => $invoice->tax_name2 ? $invoice->tax_name2 : '',
            'tax_rate2' => (float) $invoice->tax_rate2,
            'tax_name3' => $invoice->tax_name3 ? $invoice->tax_name3 : '',
            'tax_rate3' => (float) $invoice->tax_rate3,
            'total_taxes' => (float) $invoice->total_taxes,
            'is_amount_discount' => (bool) ($invoice->is_amount_discount ?: false),
            'footer' => $invoice->footer ?: '',
            'partial' => (float) ($invoice->partial ?: 0.0),
            'partial_due_date' => $invoice->partial_due_date ?: '',
            'custom_value1' => (string) $invoice->custom_value1 ?: '',
            'custom_value2' => (string) $invoice->custom_value2 ?: '',
            'custom_value3' => (string) $invoice->custom_value3 ?: '',
            'custom_value4' => (string) $invoice->custom_value4 ?: '',
            'has_tasks' => (bool) $invoice->has_tasks,
            'has_expenses' => (bool) $invoice->has_expenses,
            'custom_surcharge1' => (float) $invoice->custom_surcharge1,
            'custom_surcharge2' => (float) $invoice->custom_surcharge2,
            'custom_surcharge3' => (float) $invoice->custom_surcharge3,
            'custom_surcharge4' => (float) $invoice->custom_surcharge4,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'custom_surcharge_tax1' => (bool) $invoice->custom_surcharge_tax1,
            'custom_surcharge_tax2' => (bool) $invoice->custom_surcharge_tax2,
            'custom_surcharge_tax3' => (bool) $invoice->custom_surcharge_tax3,
            'custom_surcharge_tax4' => (bool) $invoice->custom_surcharge_tax4,
            'line_items' => $invoice->line_items ?: (array) [],
            'entity_type' => 'recurringInvoice',
            'frequency_id' => (string) $invoice->frequency_id,
            'remaining_cycles' => (int) $invoice->remaining_cycles,
            'recurring_dates' => (array) $invoice->recurringDates(),
            'auto_bill' => (string) $invoice->auto_bill,
        ];
    }
}
