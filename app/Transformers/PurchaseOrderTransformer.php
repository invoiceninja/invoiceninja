<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\Document;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;

class PurchaseOrderTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'invitations',
        'documents'
    ];

    protected array $availableIncludes = [
        'expense',
        'vendor',
        'activities',
    ];

    public function includeActivities(PurchaseOrder $purchase_order)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($purchase_order->activities, $transformer, Activity::class);
    }

    public function includeInvitations(PurchaseOrder $purchase_order)
    {
        $transformer = new PurchaseOrderInvitationTransformer($this->serializer);

        return $this->includeCollection($purchase_order->invitations, $transformer, PurchaseOrderInvitation::class);
    }

    public function includeHistory(PurchaseOrder $purchase_order)
    {
        $transformer = new PurchaseOrderHistoryTransformer($this->serializer);

        return $this->includeCollection($purchase_order->history, $transformer, Backup::class);
    }

    public function includeDocuments(PurchaseOrder $purchase_order)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($purchase_order->documents, $transformer, Document::class);
    }

    public function includeExpense(PurchaseOrder $purchase_order)
    {
        $transformer = new ExpenseTransformer($this->serializer);

        if (!$purchase_order->expense) {
            return null;
        }

        return $this->includeItem($purchase_order->expense, $transformer, Expense::class);
    }

    public function includeVendor(PurchaseOrder $purchase_order)
    {
        $transformer = new VendorTransformer($this->serializer);

        if (!$purchase_order->vendor) {//@phpstan-ignore-line
            return null;
        }

        return $this->includeItem($purchase_order->vendor, $transformer, Vendor::class);
    }

    public function transform(PurchaseOrder $purchase_order)
    {
        return [
            'id' => $this->encodePrimaryKey($purchase_order->id),
            'user_id' => $this->encodePrimaryKey($purchase_order->user_id),
            'project_id' => $this->encodePrimaryKey($purchase_order->project_id),
            'assigned_user_id' => $this->encodePrimaryKey($purchase_order->assigned_user_id),
            'vendor_id' => (string)$this->encodePrimaryKey($purchase_order->vendor_id),
            'amount' => (float)$purchase_order->amount,
            'balance' => (float)$purchase_order->balance,
            'client_id' => (string)$this->encodePrimaryKey($purchase_order->client_id),
            'status_id' => (string)($purchase_order->status_id ?: 1),
            'design_id' => (string)$this->encodePrimaryKey($purchase_order->design_id),
            'created_at' => (int)$purchase_order->created_at,
            'updated_at' => (int)$purchase_order->updated_at,
            'archived_at' => (int)$purchase_order->deleted_at,
            'is_deleted' => (bool)$purchase_order->is_deleted,
            'number' => $purchase_order->number ?: '',
            'discount' => (float)$purchase_order->discount,
            'po_number' => $purchase_order->po_number ?: '',
            'date' => $purchase_order->date ?: '',
            'last_sent_date' => $purchase_order->last_sent_date ?: '',
            'next_send_date' => $purchase_order->next_send_date ?: '',
            'reminder1_sent' => $purchase_order->reminder1_sent ?: '',
            'reminder2_sent' => $purchase_order->reminder2_sent ?: '',
            'reminder3_sent' => $purchase_order->reminder3_sent ?: '',
            'reminder_last_sent' => $purchase_order->reminder_last_sent ?: '',
            'due_date' => $purchase_order->due_date ?: '',
            'terms' => $purchase_order->terms ?: '',
            'public_notes' => $purchase_order->public_notes ?: '',
            'private_notes' => $purchase_order->private_notes ?: '',
            'uses_inclusive_taxes' => (bool)$purchase_order->uses_inclusive_taxes,
            'tax_name1' => $purchase_order->tax_name1 ? $purchase_order->tax_name1 : '',
            'tax_rate1' => (float)$purchase_order->tax_rate1,
            'tax_name2' => $purchase_order->tax_name2 ? $purchase_order->tax_name2 : '',
            'tax_rate2' => (float)$purchase_order->tax_rate2,
            'tax_name3' => $purchase_order->tax_name3 ? $purchase_order->tax_name3 : '',
            'tax_rate3' => (float)$purchase_order->tax_rate3,
            'total_taxes' => (float)$purchase_order->total_taxes,
            'is_amount_discount' => (bool)($purchase_order->is_amount_discount ?: false),
            'footer' => $purchase_order->footer ?: '',
            'partial' => (float)($purchase_order->partial ?: 0.0),
            'partial_due_date' => $purchase_order->partial_due_date ?: '',
            'custom_value1' => (string)$purchase_order->custom_value1 ?: '',
            'custom_value2' => (string)$purchase_order->custom_value2 ?: '',
            'custom_value3' => (string)$purchase_order->custom_value3 ?: '',
            'custom_value4' => (string)$purchase_order->custom_value4 ?: '',
            'has_tasks' => (bool)$purchase_order->has_tasks,
            'has_expenses' => (bool)$purchase_order->has_expenses,
            'custom_surcharge1' => (float)$purchase_order->custom_surcharge1,
            'custom_surcharge2' => (float)$purchase_order->custom_surcharge2,
            'custom_surcharge3' => (float)$purchase_order->custom_surcharge3,
            'custom_surcharge4' => (float)$purchase_order->custom_surcharge4,
            'custom_surcharge_tax1' => (bool)$purchase_order->custom_surcharge_tax1,
            'custom_surcharge_tax2' => (bool)$purchase_order->custom_surcharge_tax2,
            'custom_surcharge_tax3' => (bool)$purchase_order->custom_surcharge_tax3,
            'custom_surcharge_tax4' => (bool)$purchase_order->custom_surcharge_tax4,
            'line_items' => $purchase_order->line_items ?: (array)[],
            'entity_type' => 'purchaseOrder',
            'exchange_rate' => (float)$purchase_order->exchange_rate,
            'paid_to_date' => (float)$purchase_order->paid_to_date,
            'subscription_id' => $this->encodePrimaryKey($purchase_order->subscription_id),
            'expense_id' => $this->encodePrimaryKey($purchase_order->expense_id),
            'currency_id' => $purchase_order->currency_id ? (string) $purchase_order->currency_id : '',
            'tax_info' => $purchase_order->tax_data ?: new \stdClass(),
            'e_invoice' => $purchase_order->e_invoice ?: new \stdClass(),

        ];
    }
}
