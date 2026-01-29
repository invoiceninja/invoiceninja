<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Invoice;
use App\Models\Webhook;

class InvoiceObserver
{
    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function created(Invoice $invoice)
    {
        $subscriptions = Webhook::where('company_id', $invoice->company_id)
                            ->where('event_id', Webhook::EVENT_CREATE_INVOICE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_INVOICE, $invoice, $invoice->company, 'client')->delay(0);
        }

        // QuickBooks push - check if invoice status matches push_invoice_statuses
        // Map invoice status to string for status-based push check
        $invoiceStatus = $this->mapInvoiceStatusToString($invoice->status_id, $invoice->is_deleted);
        
        if ($invoice->company->shouldPushToQuickbooks('invoice', 'status', $invoiceStatus)) {
            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch(
                'invoice',
                $invoice->id,
                $invoice->company->id,
                $invoice->company->db,
                'create',
                $invoiceStatus
            );
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function updated(Invoice $invoice)
    {
        $event = Webhook::EVENT_UPDATE_INVOICE;

        if ($invoice->getOriginal('deleted_at') && !$invoice->deleted_at) {
            $event = Webhook::EVENT_RESTORE_INVOICE;
        }

        if ($invoice->is_deleted) {
            $event = Webhook::EVENT_DELETE_INVOICE;
        }


        $subscriptions = Webhook::where('company_id', $invoice->company->id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $invoice, $invoice->company, 'client')->delay(0);
        }

        // QuickBooks push - check if invoice status matches push_invoice_statuses
        // Map invoice status to string for status-based push check
        $invoiceStatus = $this->mapInvoiceStatusToString($invoice->status_id, $invoice->is_deleted);
        
        if ($invoice->company->shouldPushToQuickbooks('invoice', 'status', $invoiceStatus)) {
            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch(
                'invoice',
                $invoice->id,
                $invoice->company->id,
                $invoice->company->db,
                'update',
                $invoiceStatus
            );
        }
    }

    /**
     * Map invoice status_id and is_deleted to status string for QuickBooks push.
     * 
     * @param int $statusId
     * @param bool $isDeleted
     * @return string
     */
    private function mapInvoiceStatusToString(int $statusId, bool $isDeleted): string
    {
        if ($isDeleted) {
            return 'deleted';
        }

        return match($statusId) {
            \App\Models\Invoice::STATUS_DRAFT => 'draft',
            \App\Models\Invoice::STATUS_SENT => 'sent',
            \App\Models\Invoice::STATUS_PAID => 'paid',
            default => 'unknown',
        };
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function deleted(Invoice $invoice)
    {
        if ($invoice->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $invoice->company_id)
                            ->where('event_id', Webhook::EVENT_ARCHIVE_INVOICE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_INVOICE, $invoice, $invoice->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the client "restored" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function restored(Invoice $invoice)
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function forceDeleted(Invoice $invoice)
    {
        //
    }
}
