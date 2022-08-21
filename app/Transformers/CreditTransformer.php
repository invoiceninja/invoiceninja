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
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Document;
use App\Transformers\ActivityTransformer;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Item;

class CreditTransformer extends EntityTransformer
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

    public function includeActivities(Credit $credit)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($credit->activities, $transformer, Activity::class);
    }

    public function includeHistory(Credit $credit)
    {
        $transformer = new InvoiceHistoryTransformer($this->serializer);

        return $this->includeCollection($credit->history, $transformer, Backup::class);
    }

    public function includeInvitations(Credit $credit)
    {
        $transformer = new CreditInvitationTransformer($this->serializer);

        return $this->includeCollection($credit->invitations, $transformer, CreditInvitation::class);
    }

    public function includeClient(Credit $credit): Item
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($credit->client, $transformer, Client::class);
    }

    public function includeDocuments(Credit $credit)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($credit->documents, $transformer, Document::class);
    }

    public function transform(Credit $credit)
    {
        return [
            'id' => $this->encodePrimaryKey($credit->id),
            'user_id' => $this->encodePrimaryKey($credit->user_id),
            'project_id' => $this->encodePrimaryKey($credit->project_id),
            'assigned_user_id' => $this->encodePrimaryKey($credit->assigned_user_id),
            'vendor_id' => (string) $this->encodePrimaryKey($credit->vendor_id),
            'amount' => (float) $credit->amount,
            'balance' => (float) $credit->balance,
            'client_id' => (string) $this->encodePrimaryKey($credit->client_id),
            'status_id' => (string) ($credit->status_id ?: 1),
            'design_id' => (string) $this->encodePrimaryKey($credit->design_id),
            'created_at' => (int) $credit->created_at,
            'updated_at' => (int) $credit->updated_at,
            'archived_at' => (int) $credit->deleted_at,
            'is_deleted' => (bool) $credit->is_deleted,
            'number' => $credit->number ?: '',
            'discount' => (float) $credit->discount,
            'po_number' => $credit->po_number ?: '',
            'date' => $credit->date ?: '',
            'last_sent_date' => $credit->last_sent_date ?: '',
            'next_send_date' => $credit->next_send_date ?: '',
            'reminder1_sent' => $credit->reminder1_sent ?: '',
            'reminder2_sent' => $credit->reminder2_sent ?: '',
            'reminder3_sent' => $credit->reminder3_sent ?: '',
            'reminder_last_sent' => $credit->reminder_last_sent ?: '',
            'due_date' => $credit->due_date ?: '',
            'terms' => $credit->terms ?: '',
            'public_notes' => $credit->public_notes ?: '',
            'private_notes' => $credit->private_notes ?: '',
            'uses_inclusive_taxes' => (bool) $credit->uses_inclusive_taxes,
            'tax_name1' => $credit->tax_name1 ? $credit->tax_name1 : '',
            'tax_rate1' => (float) $credit->tax_rate1,
            'tax_name2' => $credit->tax_name2 ? $credit->tax_name2 : '',
            'tax_rate2' => (float) $credit->tax_rate2,
            'tax_name3' => $credit->tax_name3 ? $credit->tax_name3 : '',
            'tax_rate3' => (float) $credit->tax_rate3,
            'total_taxes' => (float) $credit->total_taxes,
            'is_amount_discount' => (bool) ($credit->is_amount_discount ?: false),
            'footer' => $credit->footer ?: '',
            'partial' => (float) ($credit->partial ?: 0.0),
            'partial_due_date' => $credit->partial_due_date ?: '',
            'custom_value1' => (string) $credit->custom_value1 ?: '',
            'custom_value2' => (string) $credit->custom_value2 ?: '',
            'custom_value3' => (string) $credit->custom_value3 ?: '',
            'custom_value4' => (string) $credit->custom_value4 ?: '',
            'has_tasks' => (bool) $credit->has_tasks,
            'has_expenses' => (bool) $credit->has_expenses,
            'custom_surcharge1' => (float) $credit->custom_surcharge1,
            'custom_surcharge2' => (float) $credit->custom_surcharge2,
            'custom_surcharge3' => (float) $credit->custom_surcharge3,
            'custom_surcharge4' => (float) $credit->custom_surcharge4,
            'custom_surcharge_tax1' => (bool) $credit->custom_surcharge_tax1,
            'custom_surcharge_tax2' => (bool) $credit->custom_surcharge_tax2,
            'custom_surcharge_tax3' => (bool) $credit->custom_surcharge_tax3,
            'custom_surcharge_tax4' => (bool) $credit->custom_surcharge_tax4,
            'line_items' => $credit->line_items ?: (array) [],
            'entity_type' => 'credit',
            'exchange_rate' => (float) $credit->exchange_rate,
            'paid_to_date' => (float) $credit->paid_to_date,
            'subscription_id' => $this->encodePrimaryKey($credit->subscription_id),
        ];
    }
}
