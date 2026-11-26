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

namespace App\PaymentDrivers\Stripe\Terminal;

use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\CardPresent;
use App\PaymentDrivers\Stripe\Jobs\ChargeRefunded;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

/**
 * Records a charged in-person PaymentIntent. The app's complete, the webhook and
 * reconciliation all land here, so it is idempotent, and it never re-checks the plan,
 * the gateway or the invoice: once a card is charged, the money is recorded.
 *
 * It records what Stripe received. Refunds are ChargeRefunded's.
 */
class RecordTerminalPayment
{
    private StripePaymentDriver $stripe;

    /* This call created the payment, rather than finding it recorded */
    private bool $created = false;

    public function __construct(private CardPresent $card_present)
    {
        $this->stripe = $card_present->stripe;
    }

    public function run(?Invoice $invoice, ?PaymentIntent $pi = null): array
    {
        if ($this->card_present->isFinal()) {
            return $this->response($invoice);
        }

        $state = $this->card_present->state();

        if (!$state->payment_intent_id) {
            return ['status' => 'requires_payment_method'];
        }

        $this->stripe->init();

        $pi ??= PaymentIntent::retrieve(['id' => $state->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        /* A connection token lets a device create its own PaymentIntents: only ours are recorded */
        if (($pi->metadata->payment_hash ?? null) != $this->stripe->payment_hash->hash
            || (bool) $pi->livemode != $state->livemode
            || strtolower($pi->currency) != $state->currency) {
            return $this->reject('payment intent does not match its collection');
        }

        if ($pi->status == 'canceled') {
            $this->card_present->updateState(['status_id' => CardPresent::STATUS_CANCELLED]);

            return ['status' => 'canceled'];
        }

        /* Stripe's status as it is; a declined card adds that it was declined, and which card */
        if ($pi->status != 'succeeded') {
            return array_merge(['status' => $pi->status], $this->card_present->declineFacts($pi));
        }

        $charge = is_string($pi->latest_charge) ? Charge::retrieve($pi->latest_charge, $this->stripe->stripe_connect_auth) : $pi->latest_charge;

        /* Recorded only once its charge has succeeded too: until then the next look asks again */
        if (($charge->status ?? null) != 'succeeded') {
            return ['status' => 'processing'];
        }

        /* Whoever holds the payment hash records, once: the webhook, complete and reconciliation wait their turn */
        try {
            $response = DB::transaction(function () use ($invoice, $pi, $charge) {
                $this->stripe->setPaymentHash(PaymentHash::query()->whereKey($this->stripe->payment_hash->id)->lockForUpdate()->first());

                if ($this->card_present->isFinal()) {
                    return $this->response($invoice, $charge);
                }

                /* Its balance now, locked before anything else is read, so createPayment works from it too */
                $invoice = $invoice ? Invoice::withTrashed()->lockForUpdate()->find($invoice->id)?->setRelation('client', $this->stripe->client) : null;

                return $this->record($invoice, $pi, $charge);
            });
        } catch (QueryException|DeadlockException $e) {
            /* Another recorder held it too long: the card was charged, and it is being recorded */
            if ($e instanceof DeadlockException || in_array($e->errorInfo[1] ?? null, [1205, 1213])) {
                return ['status' => 'recording'];
            }

            throw $e;
        }

        /* A refund made before the payment existed found nothing to refund: apply it as the webhook would */
        if ($this->created && ($charge->amount_refunded ?? 0) > 0) {
            try {
                $charge = Charge::retrieve($charge->id, $this->stripe->stripe_connect_auth);

                /* After the webhook's own copy: ChargeRefunded drops one that overlaps another */
                ChargeRefunded::dispatch($this->stripe->webhookData(['object' => $charge->toArray()]), $this->card_present->company()->company_key)->delay(now()->addSeconds(15));
            } catch (ApiErrorException $e) {
                /* Recorded either way: the refund's own webhook still applies it */
                nlog("terminal: refund catch-up {$charge->id} - " . $e->getMessage());
            }
        }

        return $response;
    }

    public function response(?Invoice $invoice, ?Charge $charge = null, bool $unapplied = false): array
    {
        $state = $this->card_present->state();
        $payment = $this->stripe->payment_hash->payment;

        if ($state->status_id != CardPresent::STATUS_RECORDED || !$payment) {
            return ['status' => match ($state->status_id) {
                CardPresent::STATUS_CANCELLED => 'canceled',
                CardPresent::STATUS_FAILED => 'failed',
                CardPresent::STATUS_ORPHANED => 'orphaned',
                CardPresent::STATUS_REJECTED => 'rejected',
                /* Stripe's own statuses pass through as they are */
                default => 'processing',
            }];
        }

        /* Money the invoice didn't take, whichever path recorded it */
        $unapplied = $unapplied || round($payment->amount - $payment->applied, 6) > 0;

        return [
            'status' => $unapplied ? 'unapplied' : 'recorded',
            'payment_id' => $payment->hashed_id,
            'applied' => $payment->applied,
            'unapplied' => round($payment->amount - $payment->applied, 6),
            /* What is still owed now that the payment counts */
            'invoice_balance' => $invoice ? (float) Invoice::withTrashed()->whereKey($invoice->id)->value('balance') : null,
            'brand' => $payment->meta->brand ?? null,
            'last4' => $payment->meta->last4 ?? null,
            'receipt_url' => ($charge ?? $this->charge($payment))?->receipt_url,
        ];
    }

    /* Under the payment hash's lock */
    private function record(?Invoice $invoice, PaymentIntent $pi, Charge $charge): array
    {
        $payment_hash = $this->stripe->payment_hash;

        $existing = $payment_hash->payment ?? StripePaymentDriver::findPaymentByStripeReference($this->stripe->company_gateway->company_id, ['id' => $pi->id, 'latest_charge' => $charge->id], true);

        if ($existing) {
            $payment_hash->payment_id = $existing->id;
            $payment_hash->setRelation('payment', $existing)->save();
            $this->card_present->updateState(['status_id' => CardPresent::STATUS_RECORDED]);

            return $this->response($invoice, $charge);
        }

        if (!$invoice || !$this->stripe->client) {
            $this->card_present->updateState(['status_id' => CardPresent::STATUS_ORPHANED]);
            $this->card_present->alert('charged, but the invoice and client no longer exist');

            return ['status' => 'orphaned'];
        }

        $state = $this->card_present->state();
        $method = $charge->payment_method_details->type ?? 'card_present';
        $card = $charge->payment_method_details->{$method} ?? null;
        $received = $pi->amount_received;

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> $currencies */
        $currencies = app('currencies');

        /* The currency it was taken in, should the client's have changed since */
        $currency = $currencies->first(fn ($currency) => strtolower($currency->code) == $state->currency);

        $amount = $this->card_present->amount();
        $base = $this->card_present->toStripe($amount, $currency);
        $closed = $invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_DRAFT]);
        $paid_elsewhere = !$closed && $this->card_present->toStripe($invoice->balance, $currency) < $base;

        /* Paid in full meanwhile: nothing is left to apply it to */
        $closed = $closed || ($paid_elsewhere && $this->card_present->toStripe($invoice->balance, $currency) <= 0);

        $received = round($this->stripe->convertFromStripeAmount($received, $currency->precision, $currency), $currency->precision);
        $invoice_amount = min($amount, $received);

        /* Partly paid meanwhile: the invoice takes its balance, the rest is the client's */
        if ($paid_elsewhere && !$closed) {
            $invoice_amount = min($invoice_amount, round($invoice->balance, $currency->precision));
        }

        $data = $payment_hash->data;
        $data->invoices = $closed ? [] : [(object) [
            'invoice_id' => $invoice->hashed_id,
            'amount' => $invoice_amount,
            'invoice_number' => $invoice->number,
            'pre_payment' => false,
        ]];
        $data->amount_with_fee = $invoice_amount;

        $payment_hash->data = $data;
        $payment_hash->save();

        $this->created = true;

        $payment = $this->stripe->createPayment([
            'payment_type' => $method == 'interac_present' ? PaymentType::DEBIT : PaymentType::parseCardType(strtolower($card->brand ?? '')),
            'amount' => $received,
            'transaction_reference' => $charge->id,
            'gateway_type_id' => GatewayType::CARD_PRESENT,
            'idempotency_key' => $pi->id,
        ], Payment::STATUS_COMPLETED);

        /* createPayment counts the invoice towards paid to date: the rest was received too */
        $unapplied = $closed ? $received : round($received - $invoice_amount, $currency->precision);

        if ($unapplied > 0) {
            $this->stripe->client->service()->updatePaidToDate($unapplied)->save();
        }

        $payment_meta = new \stdClass();
        $payment_meta->brand = $card->brand ?? '';
        $payment_meta->last4 = $card->last4 ?? '';
        $payment_meta->exp_month = $card->exp_month ?? '';
        $payment_meta->exp_year = $card->exp_year ?? '';
        $payment_meta->type = GatewayType::CARD_PRESENT;
        $payment_meta->method = $method;

        $payment->user_id = $state->user_id;
        $payment->meta = $payment_meta;

        if ($closed) {
            $payment->private_notes = trim($payment->private_notes . "\n" . ctrans('texts.invoice') . ' ' . $invoice->number);
        }

        $payment->save();

        $payment_hash->setRelation('payment', $payment);
        $this->card_present->updateState(['status_id' => CardPresent::STATUS_RECORDED]);
        $this->card_present->log(SystemLog::EVENT_GATEWAY_SUCCESS, 'recorded');

        return $this->response($invoice, $charge, $closed);
    }

    /* Answering again, however it was recorded: the app shows Stripe's receipt */
    private function charge(Payment $payment): ?Charge
    {
        try {
            $this->stripe->init();

            return Charge::retrieve($payment->transaction_reference, $this->stripe->stripe_connect_auth);
        } catch (ApiErrorException $e) {
            return null;
        }
    }

    private function reject(string $reason): array
    {
        $this->card_present->updateState(['status_id' => CardPresent::STATUS_REJECTED]);
        $this->card_present->alert($reason);

        return ['status' => 'rejected'];
    }
}
