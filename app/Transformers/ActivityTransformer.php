<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

class ActivityTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'history',
        'user',
        'client',
        'contact',
        'recurring_invoice',
        'invoice',
        'credit',
        'quote',
        'payment',
        'expense',
        'task',
        'purchase_order',
        'vendor',
        'vendor_contact',
    ];

    /**
     * @param Activity $activity
     *
     * @return array
     */
    public function transform(Activity $activity)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($activity->id),
            'activity_type_id' => (string) $activity->activity_type_id,
            'client_id' => $activity->client_id ? (string) $this->encodePrimaryKey($activity->client_id) : '',
            'recurring_invoice_id' => $activity->recurring_invoice_id ? (string) $this->encodePrimaryKey($activity->recurring_invoice_id) : '',
            'recurring_expense_id' => $activity->recurring_expense_id ? (string) $this->encodePrimaryKey($activity->recurring_expense_id) : '',
            'purchase_order_id' => $activity->purchase_order_id ? (string) $this->encodePrimaryKey($activity->purchase_order_id) : '',
            'vendor_id' => $activity->vendor_id ? (string) $this->encodePrimaryKey($activity->vendor_id) : '',
            'vendor_contact_id' => $activity->vendor_contact_id ? (string) $this->encodePrimaryKey($activity->vendor_contact_id) : '',
            'company_id' => $activity->company_id ? (string) $this->encodePrimaryKey($activity->company_id) : '',
            'user_id' => (string) $this->encodePrimaryKey($activity->user_id),
            'invoice_id' => $activity->invoice_id ? (string) $this->encodePrimaryKey($activity->invoice_id) : '',
            'quote_id' => $activity->quote_id ? (string) $this->encodePrimaryKey($activity->quote_id) : '',
            'payment_id' => $activity->payment_id ? (string) $this->encodePrimaryKey($activity->payment_id) : '',
            'credit_id' => $activity->credit_id ? (string) $this->encodePrimaryKey($activity->credit_id) : '',
            'updated_at' => (int) $activity->updated_at,
            'created_at' => (int) $activity->created_at,
            'expense_id' => $activity->expense_id ? (string) $this->encodePrimaryKey($activity->expense_id) : '',
            'is_system' => (bool) $activity->is_system,
            'contact_id' => $activity->client_contact_id ? (string) $this->encodePrimaryKey($activity->client_contact_id) : '',
            'task_id' => $activity->task_id ? (string) $this->encodePrimaryKey($activity->task_id) : '',
            'token_id' => $activity->token_id ? (string) $this->encodePrimaryKey($activity->token_id) : '',
            'notes' => $activity->notes ? (string) $activity->notes : '',
            'ip' => (string) $activity->ip,

        ];
    }

    public function includeHistory(Activity $activity)
    {
        $transformer = new InvoiceHistoryTransformer($this->serializer);

        return $this->includeItem($activity->backup, $transformer, Backup::class);
    }

    public function includeClient(Activity $activity)
    {

        if (!$activity->client) {
            return null;
        }

        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($activity->client, $transformer, Client::class);
    }

    public function includeVendor(Activity $activity)
    {
        if (!$activity->vendor) {
            return null;
        }

        $transformer = new VendorTransformer($this->serializer);

        return $this->includeItem($activity->vendor, $transformer, Vendor::class);
    }

    public function includeContact(Activity $activity)
    {

        if (!$activity->contact) {
            return null;
        }

        $transformer = new ClientContactTransformer($this->serializer);

        return $this->includeItem($activity->contact, $transformer, ClientContact::class);
    }

    public function includeVendorContact(Activity $activity)
    {

        if (!$activity->vendor_contact) {
            return null;
        }

        $transformer = new VendorContactTransformer($this->serializer);

        return $this->includeItem($activity->vendor_contact, $transformer, VendorContact::class);
    }

    public function includeRecurringInvoice(Activity $activity)
    {

        if (!$activity->recurring_invoice) {
            return null;
        }

        $transformer = new RecurringInvoiceTransformer($this->serializer);

        return $this->includeItem($activity->recurring_invoice, $transformer, RecurringInvoice::class);
    }

    public function includePurchaseOrder(Activity $activity)
    {

        if (!$activity->purchase_order) {
            return null;
        }

        $transformer = new PurchaseOrderTransformer($this->serializer);

        return $this->includeItem($activity->purchase_order, $transformer, PurchaseOrder::class);
    }


    public function includeQuote(Activity $activity)
    {

        if (!$activity->quote) {
            return null;
        }

        $transformer = new RecurringInvoiceTransformer($this->serializer);

        return $this->includeItem($activity->quote, $transformer, Quote::class);
    }

    public function includeInvoice(Activity $activity)
    {
        if (!$activity->invoice) {
            return null;
        }

        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeItem($activity->invoice, $transformer, Invoice::class);
    }

    public function includeCredit(Activity $activity)
    {
        if (!$activity->credit) {
            return null;
        }

        $transformer = new CreditTransformer($this->serializer);

        return $this->includeItem($activity->credit, $transformer, Credit::class);
    }

    public function includePayment(Activity $activity)
    {
        if (!$activity->payment) {
            return null;
        }

        $transformer = new PaymentTransformer($this->serializer);

        return $this->includeItem($activity->payment, $transformer, Payment::class);
    }

    public function includeUser(Activity $activity)
    {
        if (!$activity->user) {
            return null;
        }

        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem($activity->user, $transformer, User::class);
    }

    public function includeExpense(Activity $activity)
    {
        if (!$activity->expense) {
            return null;
        }

        $transformer = new ExpenseTransformer($this->serializer);

        return $this->includeItem($activity->expense, $transformer, Expense::class);
    }

    public function includeTask(Activity $activity)
    {
        if (!$activity->task) {
            return null;
        }

        $transformer = new TaskTransformer($this->serializer);

        return $this->includeItem($activity->task, $transformer, Task::class);
    }
}
