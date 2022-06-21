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

namespace App\Transformers;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\Document;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Transformers\ActivityTransformer;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Item;

class QuoteTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
        'invitations',
        'documents',
    ];

    protected $availableIncludes = [
        'activities',
        'client',
    ];

    public function includeActivities(Quote $quote)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($quote->activities, $transformer, Activity::class);
    }

    public function includeHistory(Quote $quote)
    {
        $transformer = new InvoiceHistoryTransformer($this->serializer);

        return $this->includeCollection($quote->history, $transformer, Backup::class);
    }

    public function includeInvitations(Quote $quote)
    {
        $transformer = new QuoteInvitationTransformer($this->serializer);

        return $this->includeCollection($quote->invitations, $transformer, QuoteInvitation::class);
    }

    /*
        public function includePayments(quote $quote)
        {
            $transformer = new PaymentTransformer($this->account, $this->serializer, $quote);

            return $this->includeCollection($quote->payments, $transformer, ENTITY_PAYMENT);
        }

        public function includeExpenses(quote $quote)
        {
            $transformer = new ExpenseTransformer($this->account, $this->serializer);

            return $this->includeCollection($quote->expenses, $transformer, ENTITY_EXPENSE);
        }
    */

    public function includeDocuments(Quote $quote)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($quote->documents, $transformer, Document::class);
    }

    public function includeClient(Quote $quote): Item
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($quote->client, $transformer, Client::class);
    }

    public function transform(Quote $quote)
    {
        return [
            'id' => $this->encodePrimaryKey($quote->id),
            'user_id' => $this->encodePrimaryKey($quote->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($quote->assigned_user_id),
            'amount' => (float) $quote->amount,
            'balance' => (float) $quote->balance,
            'client_id' => (string) $this->encodePrimaryKey($quote->client_id),
            'status_id' => (string) $quote->status_id,
            'design_id' => (string) $this->encodePrimaryKey($quote->design_id),
            'invoice_id' => (string) $this->encodePrimaryKey($quote->invoice_id),
            'vendor_id' => (string) $this->encodePrimaryKey($quote->vendor_id),
            'updated_at' => (int) $quote->updated_at,
            'archived_at' => (int) $quote->deleted_at,
            'created_at' => (int) $quote->created_at,
            'number' => $quote->number ?: '',
            'discount' => (float) $quote->discount,
            'po_number' => $quote->po_number ?: '',
            'date' => $quote->date ?: '',
            'last_sent_date' => $quote->last_sent_date ?: '',
            'next_send_date' => $quote->next_send_date ?: '',
            'reminder1_sent' => $quote->reminder1_sent ?: '',
            'reminder2_sent' => $quote->reminder2_sent ?: '',
            'reminder3_sent' => $quote->reminder3_sent ?: '',
            'reminder_last_sent' => $quote->reminder_last_sent ?: '',
            'due_date' => $quote->due_date ?: '',
            'terms' => $quote->terms ?: '',
            'public_notes' => $quote->public_notes ?: '',
            'private_notes' => $quote->private_notes ?: '',
            'is_deleted' => (bool) $quote->is_deleted,
            'uses_inclusive_taxes' => (bool) $quote->uses_inclusive_taxes,
            'tax_name1' => $quote->tax_name1 ? $quote->tax_name1 : '',
            'tax_rate1' => (float) $quote->tax_rate1,
            'tax_name2' => $quote->tax_name2 ? $quote->tax_name2 : '',
            'tax_rate2' => (float) $quote->tax_rate2,
            'tax_name3' => $quote->tax_name3 ? $quote->tax_name3 : '',
            'tax_rate3' => (float) $quote->tax_rate3,
            'total_taxes' => (float) $quote->total_taxes,
            'is_amount_discount' => (bool) ($quote->is_amount_discount ?: false),
            'footer' => $quote->footer ?: '',
            'partial' => (float) ($quote->partial ?: 0.0),
            'partial_due_date' => $quote->partial_due_date ?: '',
            'custom_value1' => (string) $quote->custom_value1 ?: '',
            'custom_value2' => (string) $quote->custom_value2 ?: '',
            'custom_value3' => (string) $quote->custom_value3 ?: '',
            'custom_value4' => (string) $quote->custom_value4 ?: '',
            'has_tasks' => (bool) $quote->has_tasks,
            'has_expenses' => (bool) $quote->has_expenses,
            'custom_surcharge1' => (float) $quote->custom_surcharge1,
            'custom_surcharge2' => (float) $quote->custom_surcharge2,
            'custom_surcharge3' => (float) $quote->custom_surcharge3,
            'custom_surcharge4' => (float) $quote->custom_surcharge4,
            'custom_surcharge_tax1' => (bool) $quote->custom_surcharge_tax1,
            'custom_surcharge_tax2' => (bool) $quote->custom_surcharge_tax2,
            'custom_surcharge_tax3' => (bool) $quote->custom_surcharge_tax3,
            'custom_surcharge_tax4' => (bool) $quote->custom_surcharge_tax4,
            'line_items' => $quote->line_items ?: (array) [],
            'entity_type' => 'quote',
            'exchange_rate' => (float) $quote->exchange_rate,
            'paid_to_date' => (float) $quote->paid_to_date,
            'project_id' => $this->encodePrimaryKey($quote->project_id),
            'subscription_id' => $this->encodePrimaryKey($quote->subscription_id),

        ];
    }
}
