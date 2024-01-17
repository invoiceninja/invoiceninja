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

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Payment;
use App\Models\Webhook;

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
                            ->where('event_id', Webhook::EVENT_CREATE_PAYMENT)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_PAYMENT, $payment, $payment->company, 'invoices,client')->delay(20);
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
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $payment, $payment->company, 'invoices,client')->delay(25);
        }
    }

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
