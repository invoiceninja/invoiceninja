<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Payment;
use App\Models\Webhook;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\Quickbooks\QuickbooksBatchCollector;
use App\Services\Quickbooks\QuickbooksService;

class PaymentObserver
{
    public $afterCommit = true;

    /**
     * Handle the payment "created" event.
     *
     * @param Payment $payment
     * @return void
     */
    public function created(Payment $payment)
    {
        $subscriptions = Webhook::where('company_id', $payment->company_id)
                            ->where('is_deleted', false)
                            ->whereNull('deleted_at')
                            ->where('event_id', Webhook::EVENT_CREATE_PAYMENT)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_PAYMENT, $payment, $payment->company, 'invoices,client')->delay(20);
        }

        if ($payment->company->shouldPushToQuickbooks('payment')
           && empty(QuickbooksService::$importing[$payment->company_id])
           && $payment->status_id === Payment::STATUS_COMPLETED) {
            // LOW priority (30s window) so the invoice's NORMAL batch (10s) flushes first —
            // the payment's LinkedTxn lookup needs invoice->sync->qb_id to be populated.
            QuickbooksBatchCollector::collect(
                'payment',
                $payment->id,
                $payment->company->db,
                $payment->company_id,
                QuickbooksBatchCollector::PRIORITY_LOW,
            );
        }
    }

    /**
     * Handle the payment "updated" event.
     *
     * @param Payment $payment
     * @return void
     */
    public function updated(Payment $payment)
    {
        $event = Webhook::EVENT_UPDATE_PAYMENT;

        if ($payment->getOriginal('deleted_at') && !$payment->deleted_at) {
            $event = Webhook::EVENT_RESTORE_PAYMENT;
        }

        if ($payment->is_deleted) {
            $event = Webhook::EVENT_DELETE_PAYMENT;
        }


        $subscriptions = Webhook::where('company_id', $payment->company_id)
                                    ->where('is_deleted', false)
                                    ->whereNull('deleted_at')
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $payment, $payment->company, 'invoices,client')->delay(25);
        }

        if ($payment->company->shouldPushToQuickbooks('payment')
           && empty(QuickbooksService::$importing[$payment->company_id])) {
            QuickbooksBatchCollector::collect(
                'payment',
                $payment->id,
                $payment->company->db,
                $payment->company_id,
                QuickbooksBatchCollector::PRIORITY_LOW,
            );
        }

        // $this->recordFrancePaymentStatusTransition($payment);
    }

    // private function recordFrancePaymentStatusTransition(Payment $payment): void
    // {
    //     if (! (bool) $payment->company->getSetting('france_reporting_enabled')) {
    //         return;
    //     }

    //     try {
    //         app(FrancePaymentApplicationRecorder::class)->recordStatusTransition(
    //             $payment,
    //             (int) $payment->getOriginal('status_id'),
    //         );
    //     } catch (\Throwable $exception) {
    //         report($exception);
    //     }
    // }

    /**
     * Handle the payment "deleted" event.
     *
     * @param Payment $payment
     * @return void
     */
    public function deleted(Payment $payment)
    {
        if ($payment->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $payment->company_id)
                        ->where('is_deleted', false)
                        ->whereNull('deleted_at')
                        ->where('event_id', Webhook::EVENT_ARCHIVE_PAYMENT)
                        ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_PAYMENT, $payment, $payment->company, 'invoices,client')->delay(20);
        }
    }

    /**
     * Handle the payment "restored" event.
     *
     * @param Payment $payment
     * @return void
     */
    public function restored(Payment $payment)
    {
        //
    }

    /**
     * Handle the payment "force deleted" event.
     *
     * @param Payment $payment
     * @return void
     */
    public function forceDeleted(Payment $payment)
    {
        //
    }
}
