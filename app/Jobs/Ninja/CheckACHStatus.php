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

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use App\Models\Payment;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckACHStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private array $authnet_success_statuses = [
        'settledSuccessfully',
        'refundSettledSuccessfully',
    ];

    private array $authnet_failure_statuses = [
        'declined',
        'voided',
        'returnedItem',
        'chargeback',
        'settlementError',
        'generalError',
        'communicationError',
        'FDSDeclined',
    ];


    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            nlog("Checking ACH status");

            /** Stripe ACH Paymnets that are pending */
            Payment::where('status_id', 1)
            ->where('is_deleted', false)
            ->whereHas('company_gateway', function ($q) {
                $q->whereIn('gateway_key', ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23']);
            })
            ->where('created_at', '>', now()->subDays(10))
            ->cursor()
            ->each(function ($p) {

                try {
                    $stripe = $p->company_gateway->driver($p->client)->init();
                } catch (\Exception $e) {
                    return;
                }

                $pi = false;

                try {
                    if (str_starts_with($p->transaction_reference, 'pi_')) {
                        $pi = $stripe->getPaymentIntent($p->transaction_reference);
                    }
                } catch (\Exception $e) {

                }

                if (!$pi) {

                    try {
                        $charge = \Stripe\Charge::retrieve($p->transaction_reference, $stripe->stripe_connect_auth);

                        if ($charge && $charge->status == 'failed') {
                            $p->service()->deletePayment();
                            $p->status_id = \App\Models\Payment::STATUS_FAILED;
                            $p->save();
                            return;
                        } elseif ($charge && $charge->status == 'succeeded') {
                            $this->completePayment($p);
                            return;
                        }

                    } catch (\Exception $e) {

                    }

                }


                if ($pi && $pi->status == 'succeeded') {
                    $this->completePayment($p);
                    return;
                }

                if ($pi && $pi->latest_charge) {

                    $charge = \Stripe\Charge::retrieve($pi->latest_charge, $stripe->stripe_connect_auth);

                    if ($charge && $charge->status == 'failed') {
                        $p->service()->deletePayment();
                        $p->status_id = \App\Models\Payment::STATUS_FAILED;
                        $p->save();
                        return;
                    } elseif ($charge && $charge->status == 'succeeded') {
                        $this->completePayment($p);
                        return;
                    }
                }

                if ($pi) {
                    nlog("{$p->id} did not complete {$p->transaction_reference}");
                } else {
                    nlog("did not find a payment intent {$p->transaction_reference}");
                }

            });

            /**
             * Helcim ACH transactions remain pending until clearing completes.
             * Completed payments are terminal; later returns are handled manually.
             */
            Payment::with('client', 'company_gateway', 'currency')
                ->where('is_deleted', false)
                ->where('gateway_type_id', 2)
                ->where('status_id', Payment::STATUS_PENDING)
                ->where('created_at', '>', now()->subDays(10))
                ->whereHas('company_gateway', function ($q) {
                    $q->where('gateway_key', 'ca3b3f7e4be811c96a8a1f4cafe2a97f');
                })
                ->cursor()
                ->each(function (Payment $payment) {
                    try {
                        $driver = $payment->company_gateway->driver($payment->client)->init();
                        $driver->reconcileAchPayment($payment);
                    } catch (\Throwable $e) {
                        nlog("Error checking Helcim ACH payment {$payment->id}: {$e->getMessage()}");
                    }
                });

            /**
             * Blockonomics payments that have been pending for over 3 days are deleted
             */
            Payment::where('status_id', 1)
                ->where('is_deleted', false)
                ->where('created_at', '<', now()->startOfDay()->subDays(3))
                ->whereHas('company_gateway', function ($q) {
                    $q->where('gateway_key', 'wbhf02us6owgo7p4nfjd0ymssdshks4d');
                })
                ->where('created_at', '>', now()->subDays(10))
                ->cursor()
                ->each(function ($p) {
                    $p->service()->deletePayment();
                    $p->status_id = \App\Models\Payment::STATUS_FAILED;
                    $p->save();
                });

            /**
             * Authorize ACH Payments that are pending for over 2 days
             */
            Payment::with('client', 'company_gateway')
               ->where('status_id', 1)
               ->where('is_deleted', false)
               ->where('created_at', '<', now()->startOfDay()->subDays(3))
               ->where('gateway_type_id', 2)
                   ->whereHas('company_gateway', function ($q) {
                       $q->where('gateway_key', '3b6621f970ab18887c4f6dca78d3f8bb');
                   })
                   ->where('created_at', '>', now()->subDays(10))
                   ->cursor()
                   ->each(function ($p) {

                       try {
                           $driver = $p->company_gateway->driver($p->client)->init();
                           $authorize_transaction = new \App\PaymentDrivers\Authorize\AuthorizeTransactions($driver);
                           $transaction_details = $authorize_transaction->getTransactionDetails($p->transaction_reference);

                           $transaction = $transaction_details->getTransaction();
                           $transaction_status = $transaction->getTransactionStatus();

                           if (in_array($transaction_status, $this->authnet_success_statuses)) {
                               $this->completePayment($p);
                               return;
                           } elseif (in_array($transaction_status, $this->authnet_failure_statuses)) {
                               $p->service()->deletePayment();
                               $p->status_id = \App\Models\Payment::STATUS_FAILED;
                               $p->save();
                           }
                       } catch (\Throwable $e) {
                           nlog("Error checking ACH status for payment {$p->id}: {$e->getMessage()}");
                       }

                   });
        }
    }

    private function completePayment(Payment $payment): void
    {
        $originalStatus = (int) $payment->status_id;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->saveQuietly();

        // if (! (bool) $payment->company->getSetting('france_reporting_enabled')) {
        //     return;
        // }

        // app(FrancePaymentApplicationRecorder::class)->recordStatusTransition($payment, $originalStatus);
    }
}
