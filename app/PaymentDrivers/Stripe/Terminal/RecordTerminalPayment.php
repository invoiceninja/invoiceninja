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
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\TerminalPaymentIntent;
use App\PaymentDrivers\Stripe\CardPresent;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Support\Facades\DB;
use Stripe\Charge;
use Stripe\PaymentIntent;
use Stripe\Refund;

/**
 * Records a charged in-person PaymentIntent. The app's complete, the webhook and
 * reconciliation all land here, so it is idempotent, and it never re-checks the plan,
 * the gateway or the invoice: once a card is charged, the money is recorded.
 */
class RecordTerminalPayment
{
    public function __construct(private CardPresent $card_present)
    {
    }

    public function run(TerminalPaymentIntent $collection, ?PaymentIntent $pi = null): array
    {
        if ($collection->isFinal()) {
            return $this->response($collection);
        }

        if (!$collection->payment_intent_id) {
            return ['status' => 'requires_payment_method'];
        }

        if (!$collection->payment_hash) {
            return $this->reject($collection, 'payment hash missing');
        }

        $stripe = $this->card_present->stripe;
        $stripe->init();

        $pi ??= PaymentIntent::retrieve(['id' => $collection->payment_intent_id, 'expand' => ['latest_charge']], $stripe->stripe_connect_auth);

        /* A connection token lets a device create its own PaymentIntents: only ours are recorded */
        if (($pi->metadata->payment_hash ?? null) != $collection->payment_hash->hash
            || (bool) $pi->livemode != $collection->livemode
            || strtolower($pi->currency) != $collection->currency) {
            return $this->reject($collection, 'payment intent does not match its collection');
        }

        if ($pi->status == 'canceled') {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_CANCELLED]);

            return ['status' => 'canceled'];
        }

        if ($pi->status != 'succeeded') {
            return ['status' => $pi->status];
        }

        $claimed_at = now();

        $claimed = TerminalPaymentIntent::query()
            ->where('id', $collection->id)
            ->whereIn('status_id', TerminalPaymentIntent::OPEN_STATUSES)
            ->where(fn ($query) => $query->where('status_id', '!=', TerminalPaymentIntent::STATUS_RECORDING)->orWhere('claimed_at', '<', now()->subMinutes(2)))
            ->update(['status_id' => TerminalPaymentIntent::STATUS_RECORDING, 'claimed_at' => $claimed_at]);

        if (!$claimed) {
            $collection->refresh();

            return $collection->status_id == TerminalPaymentIntent::STATUS_RECORDED ? $this->response($collection) : ['status' => 'processing'];
        }

        try {
            return $this->record($collection->fresh(), $pi);
        } catch (\Throwable $e) {
            TerminalPaymentIntent::query()
                ->where('id', $collection->id)
                ->where('status_id', TerminalPaymentIntent::STATUS_RECORDING)
                ->where('claimed_at', $claimed_at)
                ->update(['status_id' => TerminalPaymentIntent::STATUS_OPEN, 'claimed_at' => null]);

            throw $e;
        }
    }

    public function response(TerminalPaymentIntent $collection, ?Charge $charge = null, bool $unapplied = false): array
    {
        $payment = $collection->payment;

        if ($collection->status_id != TerminalPaymentIntent::STATUS_RECORDED || !$payment) {
            return ['status' => match ($collection->status_id) {
                TerminalPaymentIntent::STATUS_CANCELLED => 'canceled',
                TerminalPaymentIntent::STATUS_FAILED => 'failed',
                TerminalPaymentIntent::STATUS_ORPHANED => 'orphaned',
                TerminalPaymentIntent::STATUS_REJECTED => 'rejected',
                default => 'processing',
            }];
        }

        return [
            'status' => $unapplied ? 'unapplied' : 'recorded',
            'payment_id' => $payment->hashed_id,
            'applied' => $payment->applied,
            'unapplied' => round($payment->amount - $payment->applied, 6),
            'invoice_balance' => $collection->invoice?->balance,
            'brand' => $payment->meta->brand ?? null,
            'last4' => $payment->meta->last4 ?? null,
            'receipt_url' => $charge->receipt_url ?? null,
        ];
    }

    private function record(TerminalPaymentIntent $collection, PaymentIntent $pi): array
    {
        $stripe = $this->card_present->stripe;
        $charge = is_string($pi->latest_charge) ? Charge::retrieve($pi->latest_charge, $stripe->stripe_connect_auth) : $pi->latest_charge;
        $payment_hash = $collection->payment_hash;

        $existing = $payment_hash->payment_id
            ? Payment::withTrashed()->find($payment_hash->payment_id)
            : StripePaymentDriver::findPaymentByStripeReference($collection->company_id, ['id' => $pi->id, 'latest_charge' => $charge->id], true);

        if ($existing) {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_RECORDED, 'payment_id' => $existing->id]);

            return $this->response($collection->fresh(), $charge);
        }

        $invoice = $collection->invoice;
        $client = $invoice?->client()->withTrashed()->first();

        if (!$invoice || !$client) {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_ORPHANED]);
            $this->card_present->alert($collection, 'charged, but the invoice and client no longer exist');

            return ['status' => 'orphaned'];
        }

        $stripe->setClient($client);

        $method = $charge->payment_method_details->type ?? 'card_present';
        $card = $charge->payment_method_details->{$method} ?? null;
        $received = $pi->amount_received - ($charge->amount_refunded ?? 0);
        $base = $this->card_present->toStripe($invoice, $collection->amount);
        $fee = $collection->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT ? $this->card_present->toStripe($invoice, $collection->fee_gross) : 0;
        $closed = $invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_DRAFT]);
        $paid_elsewhere = !$closed && $this->card_present->toStripe($invoice, $invoice->balance) < $base;

        /* A surcharge is only kept on a credit card, itemised on the invoice it paid */
        if ($fee > 0 && $received >= $base + $fee && (($card->funding ?? null) != 'credit' || $closed || $paid_elsewhere)) {
            $received -= $this->refundSurcharge($collection, $pi, $method, $fee);
        }

        $fee = $fee > 0 && $received == $base + $fee ? $collection->fee_gross : 0;

        $currency = $client->currency();
        $received = round($stripe->convertFromStripeAmount($received, $currency->precision, $currency), $currency->precision);
        $invoice_amount = min($collection->amount, round($received - $fee, $currency->precision));

        return DB::transaction(function () use ($collection, $pi, $charge, $payment_hash, $invoice, $client, $method, $card, $received, $fee, $invoice_amount, $closed) {
            $data = $payment_hash->data;
            $data->invoices = $closed ? [] : [(object) [
                'invoice_id' => $invoice->hashed_id,
                'amount' => $invoice_amount,
                'invoice_number' => $invoice->number,
                'pre_payment' => false,
            ]];
            $data->fee_net = $fee > 0 ? $collection->fee_net : 0;
            $data->amount_with_fee = $invoice_amount + $fee;

            $payment_hash->data = $data;
            $payment_hash->fee_total = $closed ? 0 : $fee;
            $payment_hash->save();

            $payment = $this->card_present->stripe->setPaymentHash($payment_hash)->createPayment([
                'payment_type' => $method == 'interac_present' ? PaymentType::DEBIT : PaymentType::parseCardType(strtolower($card->brand ?? '')),
                'amount' => $received,
                'transaction_reference' => $charge->id,
                'gateway_type_id' => GatewayType::CARD_PRESENT,
                'idempotency_key' => $pi->id,
            ], Payment::STATUS_COMPLETED);

            /* createPayment counts the invoice and fee towards paid to date: the rest was received too */
            $unapplied = $closed ? $received : round($received - $invoice_amount - $fee, $client->currency()->precision);

            if ($unapplied > 0) {
                $client->service()->updatePaidToDate($unapplied)->save();
            }

            $payment_meta = new \stdClass();
            $payment_meta->brand = $card->brand ?? '';
            $payment_meta->last4 = $card->last4 ?? '';
            $payment_meta->exp_month = $card->exp_month ?? '';
            $payment_meta->exp_year = $card->exp_year ?? '';
            $payment_meta->type = GatewayType::CARD_PRESENT;
            $payment_meta->method = $method;

            $payment->user_id = $collection->user_id;
            $payment->meta = $payment_meta;

            if ($closed) {
                $payment->private_notes = trim($payment->private_notes . "\n" . ctrans('texts.invoice') . ' ' . $invoice->number);
            }

            $payment->save();

            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_RECORDED, 'payment_id' => $payment->id]);

            $this->card_present->log($collection, SystemLog::EVENT_GATEWAY_SUCCESS, 'recorded');

            return $this->response($collection->fresh(), $charge, $closed);
        });
    }

    /* Idempotent, and remembered so ChargeRefunded does not take it off the payment again */
    private function refundSurcharge(TerminalPaymentIntent $collection, PaymentIntent $pi, string $method, int $fee): int
    {
        if ($method == 'interac_present') {
            $this->card_present->alert($collection, 'a surcharge was charged to an Interac card: refund it on the reader');

            return 0;
        }

        Refund::create([
            'payment_intent' => $pi->id,
            'amount' => $fee,
            'metadata' => ['in_terminal_surcharge_refund' => '1'],
        ], array_merge($this->card_present->stripe->stripe_connect_auth, ['idempotency_key' => "in_terminal_surcharge_{$pi->id}"]));

        $collection->update(['surcharge_refunded' => $fee]);

        $this->card_present->alert($collection, 'surcharge refunded: it was charged to a card it does not apply to');

        return $fee;
    }

    private function reject(TerminalPaymentIntent $collection, string $reason): array
    {
        $collection->update(['status_id' => TerminalPaymentIntent::STATUS_REJECTED]);
        $this->card_present->alert($collection, $reason);

        return ['status' => 'rejected'];
    }
}
