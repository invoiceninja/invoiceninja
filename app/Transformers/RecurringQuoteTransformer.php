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

use App\Models\RecurringQuote;
use App\Utils\Traits\MakesHash;

class RecurringQuoteTransformer extends EntityTransformer
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
        public function includeInvoiceItems(Invoice $quote)
        {
            $transformer = new InvoiceItemTransformer($this->serializer);

            return $this->includeCollection($quote->invoice_items, $transformer, ENTITY_INVOICE_ITEM);
        }

        public function includeInvitations(Invoice $quote)
        {
            $transformer = new InvitationTransformer($this->account, $this->serializer);

            return $this->includeCollection($quote->invitations, $transformer, ENTITY_INVITATION);
        }

        public function includePayments(Invoice $quote)
        {
            $transformer = new PaymentTransformer($this->account, $this->serializer, $quote);

            return $this->includeCollection($quote->payments, $transformer, ENTITY_PAYMENT);
        }

        public function includeClient(Invoice $quote)
        {
            $transformer = new ClientTransformer($this->account, $this->serializer);

            return $this->includeItem($quote->client, $transformer, ENTITY_CLIENT);
        }

        public function includeExpenses(Invoice $quote)
        {
            $transformer = new ExpenseTransformer($this->account, $this->serializer);

            return $this->includeCollection($quote->expenses, $transformer, ENTITY_EXPENSE);
        }

        public function includeDocuments(Invoice $quote)
        {
            $transformer = new DocumentTransformer($this->account, $this->serializer);

            $quote->documents->each(function ($document) use ($quote) {
                $document->setRelation('invoice', $quote);
            });

            return $this->includeCollection($quote->documents, $transformer, ENTITY_DOCUMENT);
        }
    */
    public function transform(RecurringQuote $quote)
    {
        return [
            'id' => $this->encodePrimaryKey($quote->id),
            'user_id' => $this->encodePrimaryKey($quote->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($quote->assigned_user_id),
            'amount' => (float) $quote->amount ?: '',
            'balance' => (float) $quote->balance ?: '',
            'client_id' => (string) $quote->client_id,
            'status_id' => (string) ($quote->status_id ?: 1),
            'created_at' => (int) $quote->created_at,
            'updated_at' => (int) $quote->updated_at,
            'archived_at' => (int) $quote->deleted_at,
            'discount' => (float) $quote->discount ?: '',
            'po_number' => $quote->po_number ?: '',
            'quote_date' => $quote->quote_date ?: '',
            'valid_until' => $quote->valid_until ?: '',
            'terms' => $quote->terms ?: '',
            'public_notes' => $quote->public_notes ?: '',
            'private_notes' => $quote->private_notes ?: '',
            'is_deleted' => (bool) $quote->is_deleted,
            'tax_name1' => $quote->tax_name1 ? $quote->tax_name1 : '',
            'tax_rate1' => (float) $quote->tax_rate1 ?: '',
            'tax_name2' => $quote->tax_name2 ? $quote->tax_name2 : '',
            'tax_rate2' => (float) $quote->tax_rate2 ?: '',
            'tax_name3' => $quote->tax_name3 ? $quote->tax_name3 : '',
            'tax_rate3' => (float) $quote->tax_rate3 ?: '',
            'is_amount_discount' => (bool) ($quote->is_amount_discount ?: false),
            'quote_footer' => $quote->quote_footer ?: '',
            'partial' => (float) ($quote->partial ?: 0.0),
            'partial_due_date' => $quote->partial_due_date ?: '',
            'custom_value1' => (float) $quote->custom_value1 ?: '',
            'custom_value2' => (float) $quote->custom_value2 ?: '',
            'custom_taxes1' => (bool) $quote->custom_taxes1 ?: '',
            'custom_taxes2' => (bool) $quote->custom_taxes2 ?: '',
            'has_tasks' => (bool) $quote->has_tasks,
            'has_expenses' => (bool) $quote->has_expenses,
            'custom_text_value1' => $quote->custom_text_value1 ?: '',
            'custom_text_value2' => $quote->custom_text_value2 ?: '',
            'settings' => $quote->settings ?: '',
            'frequency_id' => (int) $quote->frequency_id,
            'last_sent_date' => $quote->last_sent_date ?: '',
            'next_send_date' => $quote->next_send_date ?: '',
            'remaining_cycles' => (int) $quote->remaining_cycles,
        ];
    }
}
