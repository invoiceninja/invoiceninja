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
            'amount' => (float) $invoice->amount ?: '',
            'balance' => (float) $invoice->balance ?: '',
            'client_id' => (string) $invoice->client_id,
            'status_id' => (string) ($invoice->status_id ?: 1),
            'created_at' => (int) $invoice->created_at,
            'updated_at' => (int) $invoice->updated_at,
            'archived_at' => (int) $invoice->deleted_at,
            'discount' => (float) $invoice->discount ?: '',
            'po_number' => $invoice->po_number ?: '',
            'date' => $invoice->date ?: '',
            'due_date' => $invoice->due_date ?: '',
            'terms' => $invoice->terms ?: '',
            'public_notes' => $invoice->public_notes ?: '',
            'private_notes' => $invoice->private_notes ?: '',
            'is_deleted' => (bool) $invoice->is_deleted,
            'tax_name1' => $invoice->tax_name1 ? $invoice->tax_name1 : '',
            'tax_rate1' => (float) $invoice->tax_rate1 ?: '',
            'tax_name2' => $invoice->tax_name2 ? $invoice->tax_name2 : '',
            'tax_rate2' => (float) $invoice->tax_rate2 ?: '',
            'tax_name3' => $invoice->tax_name3 ? $invoice->tax_name3 : '',
            'tax_rate3' => (float) $invoice->tax_rate3 ?: '',
            'is_amount_discount' => (bool) ($invoice->is_amount_discount ?: false),
            'invoice_footer' => $invoice->invoice_footer ?: '',
            'partial' => (float) ($invoice->partial ?: 0.0),
            'partial_due_date' => $invoice->partial_due_date ?: '',
            'custom_value1' => (float) $invoice->custom_value1 ?: '',
            'custom_value2' => (float) $invoice->custom_value2 ?: '',
            'custom_taxes1' => (bool) $invoice->custom_taxes1 ?: '',
            'custom_taxes2' => (bool) $invoice->custom_taxes2 ?: '',
            'has_tasks' => (bool) $invoice->has_tasks,
            'has_expenses' => (bool) $invoice->has_expenses,
            'custom_text_value1' => $invoice->custom_text_value1 ?: '',
            'custom_text_value2' => $invoice->custom_text_value2 ?: '',
            'settings' => $invoice->settings ?: '',
            'frequency_id' => (string) $invoice->frequency_id,
            'start_date' => $invoice->start_date ?: '',
            'last_sent_date' => $invoice->last_sent_date ?: '',
            'next_send_date' => $invoice->next_send_date ?: '',
            'remaining_cycles' => (int) $invoice->remaining_cycles,
        ];
    }
}
