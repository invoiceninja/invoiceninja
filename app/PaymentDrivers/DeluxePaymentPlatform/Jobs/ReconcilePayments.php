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

namespace App\PaymentDrivers\DeluxePaymentPlatform\Jobs;

use App\Libraries\MultiDB;
use App\Models\GatewayType;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deluxe sends no webhook when an ACH debit is rejected or a payment is voided,
 * so recent Deluxe payments are re-checked against the Deluxe API.
 */
class ReconcilePayments implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public const GATEWAY_KEY = '26e7aed2062f96138650d6e0e133be8b';

    public function handle()
    {
        set_time_limit(0);

        if (! config('ninja.db.multi_db_enabled')) {
            $this->runAction();

            return;
        }

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            $this->runAction();
        }
    }

    private function runAction(): void
    {
        Payment::with(['client', 'company_gateway'])
            ->where('is_deleted', false)
            ->whereHas('company_gateway', fn ($q) => $q->where('gateway_key', self::GATEWAY_KEY))
            ->where(function ($q) {
                // Pending ACH debits can still clear or be rejected.
                $q->where(function ($q) {
                    $q->where('status_id', Payment::STATUS_PENDING)
                        ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
                        ->where('created_at', '>', now()->subDays(10));
                })
                // Completed payments can be voided until Deluxe settles the batch.
                ->orWhere(function ($q) {
                    $q->where('status_id', Payment::STATUS_COMPLETED)
                        ->where('created_at', '>', now()->subDays(3));
                });
            })
            ->cursor()
            ->each(function (Payment $payment) {
                try {
                    $payment->company_gateway->driver($payment->client)->init()->reconcilePayment($payment);
                } catch (\Throwable $e) {
                    nlog("Error reconciling Deluxe payment {$payment->id}: {$e->getMessage()}");
                }
            });
    }
}
