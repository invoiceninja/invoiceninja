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

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Libraries\MultiDB;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\CardPresent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\PermissionException;

/**
 * Follows one in-person collection until it is final: records a charge nobody recorded
 * (the app closed, the webhook never came) and cancels an abandoned one. Dispatched when
 * its PaymentIntent opens (and again on a resumed create), it looks every ten minutes while
 * the collection is open. Two of them for one collection take turns on its payment hash's lock.
 */
class ReconcileTerminalPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const FIRST_LOOK = 300;

    public const INTERVAL = 600;

    /* An unexpected failure is retried at the same pace */
    public $backoff = self::INTERVAL;

    public function __construct(public int $payment_hash_id, public ?string $db)
    {
    }

    /* Long after any card was read: by then it was settled, or support was told */
    public function retryUntil(): \DateTime
    {
        return now()->addDays(30);
    }

    public function handle(): void
    {
        if ($this->db) {
            MultiDB::setDb($this->db);
        }

        $payment_hash = PaymentHash::query()->find($this->payment_hash_id);

        if (!isset($payment_hash?->data->terminal)) {
            return;
        }

        try {
            $collection = CardPresent::forCollection($payment_hash);
        } catch (ModelNotFoundException $e) {
            /* Its gateway is gone: nothing left to ask Stripe with */
            $payment_hash->withData('terminal', array_merge((array) $payment_hash->data->terminal, ['status_id' => CardPresent::STATUS_FAILED]));

            return;
        }

        if ($collection->isFinal()) {
            return;
        }

        /* The device is still working on it */
        if ($payment_hash->updated_at->gt(now()->subMinutes(5))) {
            $this->release(self::INTERVAL);

            return;
        }

        $invoice = $payment_hash->fee_invoice;

        try {
            $result = $collection->record($invoice);

            if (in_array($result['status'], ['recorded', 'unapplied'])) {
                $collection->log(SystemLog::EVENT_PAYMENT_RECONCILIATION_SUCCESS, 'recorded by reconciliation');
            } elseif (!$collection->isFinal() && $collection->isStale()) {
                $collection->cancel($invoice);
            }
        } catch (InvalidRequestException|AuthenticationException|PermissionException $e) {
            /* The PaymentIntent is gone, or the account is (other keys, a disconnected account): it can't be charged now */
            if (($e->getStripeCode() == 'resource_missing' || !$e instanceof InvalidRequestException) && $collection->isStale()) {
                $collection->updateState(['status_id' => CardPresent::STATUS_FAILED]);
                $collection->alert('payment intent no longer reachable at Stripe: ' . $e->getMessage());

                return;
            }

            nlog("terminal: reconcile {$collection->state()->payment_intent_id} - " . $e->getMessage());
        } catch (ApiErrorException $e) {
            /* Stripe is unreachable or refusing: the next look tries again */
            nlog("terminal: reconcile {$collection->state()->payment_intent_id} - " . $e->getMessage());
        } catch (\Throwable $e) {
            /* Not Stripe (a lock wait, a data problem): support hears of it once, and it is tried again */
            if (Cache::add("terminal_reconcile_failed:{$this->db}:{$this->payment_hash_id}", true, now()->addDays(31))) {
                $collection->alert('reconciliation could not record it: ' . $e->getMessage());
            }
        }

        if (!$collection->isFinal()) {
            $this->release(self::INTERVAL);
        }
    }
}
