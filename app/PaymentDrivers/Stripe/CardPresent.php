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

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\TerminalException;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\TerminalPaymentIntent;
use App\Models\User;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Terminal\ConnectionToken;
use Stripe\Terminal\Location;

/**
 * In person card payments (Stripe Terminal). The device collects the card;
 * we create every PaymentIntent and record every payment.
 */
class CardPresent
{
    use MakesHash;

    /* Stripe's surcharge fields are preview only, so they are read with this version per request */
    public const SURCHARGE_PREVIEW_VERSION = '2026-03-25.preview';

    /* Terminal currencies and Stripe's minor unit exponent for each */
    public const CURRENCY_EXPONENTS = [
        'usd' => 2, 'cad' => 2, 'gbp' => 2, 'eur' => 2, 'aud' => 2, 'nzd' => 2, 'sgd' => 2, 'chf' => 2,
        'dkk' => 2, 'sek' => 2, 'nok' => 2, 'czk' => 2, 'pln' => 2, 'myr' => 2, 'jpy' => 0,
    ];

    /* Credit card surcharge caps (percent). Not offered anywhere else */
    public const SURCHARGE_CAPS = ['US' => 3.0, 'CA' => 2.4];

    public const SURCHARGE_REGION_CAPS = ['US' => ['CO' => 2.0]];

    public const SURCHARGE_BANNED_REGIONS = ['US' => ['CT', 'MA', 'ME', 'PR'], 'CA' => ['QC']];

    private const CANCELABLE = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    public function __construct(public StripePaymentDriver $stripe)
    {
    }

    public function connectionToken(?string $location_id): string
    {
        $this->stripe->init();

        return ConnectionToken::create($location_id ? ['location' => $location_id] : [], $this->stripe->stripe_connect_auth)->secret;
    }

    public function locations(): array
    {
        $this->stripe->init();

        return collect(Location::all(['limit' => 100], $this->stripe->stripe_connect_auth)->data)
            ->map(fn ($location) => ['id' => $location->id, 'display_name' => $location->display_name])
            ->values()
            ->toArray();
    }

    public function createLocation(): array
    {
        $company = $this->stripe->company_gateway->company;
        $settings = $company->settings;
        $country = $company->country()?->iso_3166_2;

        $address = array_filter([
            'line1' => trim($settings->address1 ?? ''),
            'line2' => trim($settings->address2 ?? ''),
            'city' => trim($settings->city ?? ''),
            'state' => trim($settings->state ?? ''),
            'postal_code' => trim($settings->postal_code ?? ''),
            'country' => $country,
        ]);

        $required = $country == 'US' ? ['line1', 'city', 'state', 'postal_code', 'country'] : ['line1', 'city', 'country'];

        if ($missing = array_values(array_diff($required, array_keys($address)))) {
            throw new TerminalException('location_address_required', 422, ['missing' => $missing]);
        }

        $this->stripe->init();

        $location = Location::create([
            'display_name' => Str::limit($company->present()->name(), 100, ''),
            'address' => $address,
        ], $this->stripe->stripe_connect_auth);

        return ['id' => $location->id, 'display_name' => $location->display_name];
    }

    /* Recorded on the gateway: which admin accepted the surcharge rules, and when */
    public function acknowledgeSurcharge(User $user): void
    {
        $settings = $this->stripe->company_gateway->settings ?? new \stdClass();
        $settings->terminal_surcharge_ack = (object) ['user_id' => $user->id, 'at' => now()->toIso8601String()];

        $this->stripe->company_gateway->settings = $settings;
        $this->stripe->company_gateway->save();
    }

    public function account(): array
    {
        return Cache::remember("terminal_account:{$this->stripe->company_gateway->id}", 600, function () {
            $this->stripe->init();

            $account = Account::retrieve(null, $this->stripe->stripe_connect_auth);

            return [
                'country' => strtoupper($account->country ?? ''),
                'default_currency' => strtolower($account->default_currency ?? ''),
                'card_payments_active' => (bool) ($account->charges_enabled ?? false) && (($account->capabilities->card_payments ?? 'active') == 'active'),
            ];
        });
    }

    public function livemode(): bool
    {
        $key = $this->stripe->stripe_connect ? config('ninja.ninja_stripe_key') : $this->stripe->company_gateway->getConfigField('apiKey');

        return str_contains($key ?? '', '_live_');
    }

    /**
     * Whether a credit card surcharge may be offered, and its cap.
     *
     * @return array{allowed: bool, reason: ?string, cap: ?float}
     */
    public function surchargePolicy(?Invoice $invoice = null, ?array $account = null): array
    {
        $deny = fn ($reason) => ['allowed' => false, 'reason' => $reason, 'cap' => null];

        $company_gateway = $this->stripe->company_gateway;
        $fees_and_limits = $company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);

        if (!$fees_and_limits || (floatval($fees_and_limits->fee_amount ?? 0) <= 0 && floatval($fees_and_limits->fee_percent ?? 0) <= 0)) {
            return $deny('not_configured');
        }

        if ($fees_and_limits->adjust_fee_percent ?? false) {
            return $deny('adjust_fee_percent');
        }

        $account ??= $this->account();
        $country = $account['country'];
        $region = strtoupper(trim($company_gateway->company->settings->state ?? ''));

        if (!isset(self::SURCHARGE_CAPS[$country]) || in_array($region, self::SURCHARGE_BANNED_REGIONS[$country])) {
            return $deny('region');
        }

        if (!isset($company_gateway->settings->terminal_surcharge_ack)) {
            return $deny('not_acknowledged');
        }

        if ($invoice) {
            if ($invoice->is_proforma) {
                return $deny('proforma');
            }

            if ($invoice->company->verifactuEnabled() || strlen($invoice->backup->guid ?? '') > 0) {
                return $deny('e_invoice');
            }

            if ($invoice->isLocked()) {
                return $deny('locked');
            }
        }

        $cap = self::SURCHARGE_REGION_CAPS[$country][$region] ?? self::SURCHARGE_CAPS[$country];

        return ['allowed' => true, 'reason' => null, 'cap' => $cap];
    }

    /* The figures the device shows before the card is read */
    public function quote(Invoice $invoice, float $amount, bool $apply_fee, bool $strict = false): array
    {
        $precision = $invoice->client->currency()->precision;
        $policy = $this->surchargePolicy($invoice);
        $reason = $policy['reason'];
        $fee = ['net' => 0, 'gross' => 0];

        if ($apply_fee && $policy['allowed']) {
            $quote = $invoice->service()->quoteGatewayFee($this->stripe->company_gateway, GatewayType::CARD_PRESENT, $amount);
            $cap = floor($amount * $policy['cap'] * pow(10, $precision) / 100) / pow(10, $precision);

            if ($quote['gross'] > $cap) {
                $reason = 'over_cap';
            } else {
                $fee = ['net' => round($quote['net'], $precision), 'gross' => round($quote['gross'], $precision)];
            }
        }

        if ($apply_fee && $fee['gross'] <= 0 && $strict) {
            throw new TerminalException('fee_not_allowed', 422, ['reason' => $reason]);
        }

        return [
            'amount' => round($amount, $precision),
            'fee_gross' => $fee['gross'],
            'fee_net' => $fee['net'],
            'total' => round($amount + $fee['gross'], $precision),
            'currency_id' => $invoice->client->getSetting('currency_id'),
            'surcharge_allowed' => $policy['allowed'] && $reason === null,
            'reason' => $reason,
        ];
    }

    /* One PaymentIntent per collection, at the base amount */
    public function createPaymentIntent(User $user, Invoice $invoice, array $data): array
    {
        $existing = TerminalPaymentIntent::query()
            ->where('company_id', $invoice->company_id)
            ->where('collection_id', $data['collection_id'])
            ->first();

        if ($existing) {
            return $this->resume($existing, $invoice, $data);
        }

        $account = $this->account();

        if (!$account['card_payments_active']) {
            throw new TerminalException('not_enabled', 422);
        }

        $currency = strtolower($invoice->client->currency()->code);

        if ((self::CURRENCY_EXPONENTS[$currency] ?? null) !== (int) $invoice->client->currency()->precision || $currency != $account['default_currency']) {
            throw new TerminalException('unsupported_currency', 422, [], ['currency' => strtoupper($account['default_currency'])]);
        }

        /* Earlier collections are settled outside the transaction, so a recorded charge commits before we refuse */
        $this->settleOpenCollections($invoice);

        $row = DB::transaction(function () use ($user, $invoice, $data, $currency) {
            /** @var Invoice $invoice */
            $invoice = Invoice::withTrashed()->lockForUpdate()->find($invoice->id);

            $open = TerminalPaymentIntent::query()
                ->where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', TerminalPaymentIntent::OPEN_STATUSES)
                ->first();

            if ($open) {
                throw $this->collectionOpen($open);
            }

            $this->validateAmount($invoice, $data['amount']);

            if ($invoice->status_id == Invoice::STATUS_DRAFT) {
                if ($invoice->company->verifactuEnabled()) {
                    throw new TerminalException('send_invoice_first', 422);
                }

                $invoice = $invoice->service()->markSent()->save();
            }

            $quote = $this->quote($invoice, $data['amount'], $data['apply_fee'], true);

            if (abs($data['expected_total'] - $quote['total']) > 0.000001) {
                throw new TerminalException('amount_mismatch', 409, ['quote' => $quote]);
            }

            $payment_hash = PaymentHash::create([
                'hash' => Str::random(32),
                'data' => [
                    'amount_with_fee' => $quote['amount'],
                    'fee_net' => 0,
                    'invoices' => [
                        [
                            'invoice_id' => $invoice->hashed_id,
                            'amount' => $quote['amount'],
                            'invoice_number' => $invoice->number,
                            'pre_payment' => false,
                        ],
                    ],
                ],
                'fee_total' => 0,
                'fee_invoice_id' => $invoice->id,
            ]);

            return TerminalPaymentIntent::create([
                'company_id' => $invoice->company_id,
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'company_gateway_id' => $this->stripe->company_gateway->id,
                'payment_hash_id' => $payment_hash->id,
                'collection_id' => $data['collection_id'],
                'currency' => $currency,
                'livemode' => $this->livemode(),
                'amount' => $quote['amount'],
                'fee_gross' => $quote['fee_gross'],
                'fee_net' => $quote['fee_net'],
                'apply_fee' => $data['apply_fee'] && $quote['fee_gross'] > 0,
                'status' => TerminalPaymentIntent::STATUS_CREATING,
                'touched_at' => now(),
            ]);
        });

        return $this->openPaymentIntent($row);
    }

    /**
     * After the card is read, before it is confirmed (Stripe allows 30 seconds): decide the
     * surcharge from the card Stripe saw. A recollect is finalized twice - once to raise the
     * amount, once more after the card is read again, since it may be a different card.
     */
    public function finalize(TerminalPaymentIntent $row): array
    {
        if (in_array($row->surcharge_path, [TerminalPaymentIntent::SURCHARGE_PATH_STRIPE, TerminalPaymentIntent::SURCHARGE_PATH_NONE])) {
            return $this->finalizeResponse($row, false);
        }

        if ($row->status != TerminalPaymentIntent::STATUS_OPEN || !$row->payment_intent_id || !$row->invoice) {
            throw new TerminalException('collection_closed');
        }

        $invoice = $row->invoice;
        $precision = $invoice->client->currency()->precision;

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $row->payment_intent_id, 'expand' => ['payment_method']], $this->readOptions($row));

        if ($pi->status != 'requires_confirmation') {
            throw new TerminalException('not_collected', 409, ['status' => $pi->status]);
        }

        $funding = ($pi->payment_method->type ?? '') == 'card_present' ? ($pi->payment_method->card_present->funding ?? null) : null;

        /* The second read of a recollect: keep the surcharge only for a credit card */
        if ($row->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT) {
            if ($funding == 'credit') {
                return $this->finalizeResponse($row, false, $funding);
            }

            $this->setSurcharge($row, TerminalPaymentIntent::SURCHARGE_PATH_NONE, 0);
            PaymentIntent::update($pi->id, ['amount' => $this->toStripe($invoice, $row->amount)], $this->stripe->stripe_connect_auth);

            return $this->finalizeResponse($row, true, $funding);
        }

        if ($invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])
            || round($invoice->balance, $precision) < round($row->amount, $precision)) {
            throw new TerminalException('balance_changed', 409, ['balance' => round($invoice->balance, $precision)]);
        }

        $surcharge_allowed = $row->apply_fee && $row->fee_gross > 0 && $this->surchargePolicy($invoice)['allowed'];

        if (!$surcharge_allowed || $funding != 'credit') {
            $this->setSurcharge($row, TerminalPaymentIntent::SURCHARGE_PATH_NONE, 0);

            return $this->finalizeResponse($row, false, $funding);
        }

        $stripe_surcharge = $pi->amount_details->surcharge ?? null;

        /* Stripe's own surcharge, unless it would cap ours below what the customer was shown */
        if (($stripe_surcharge->status ?? null) == 'available' && ($stripe_surcharge->maximum_amount ?? PHP_INT_MAX) >= $this->toStripe($invoice, $row->fee_gross)) {
            $this->setSurcharge($row, TerminalPaymentIntent::SURCHARGE_PATH_STRIPE, $row->fee_gross);

            return $this->finalizeResponse($row, false, $funding);
        }

        /* The decision is saved before Stripe is asked to raise the amount */
        $this->setSurcharge($row, TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT, $row->fee_gross);

        PaymentIntent::update($pi->id, [
            'amount' => $this->toStripe($invoice, $row->amount + $row->fee_gross),
            'description' => $this->description($invoice, $row->fee_gross),
        ], $this->stripe->stripe_connect_auth);

        return $this->finalizeResponse($row, true, $funding);
    }

    /**
     * Record a charged PaymentIntent. Shared by the device's complete, the webhook and
     * reconciliation, so it is idempotent, and never re-checks the plan, the gateway or
     * the invoice: once a card is charged the money is recorded.
     */
    public function record(TerminalPaymentIntent $row, ?PaymentIntent $pi = null): array
    {
        if ($row->status == TerminalPaymentIntent::STATUS_RECORDED) {
            return $this->recordedResponse($row);
        }

        if ($row->isFinal()) {
            return ['status' => $row->status];
        }

        if (!$row->payment_intent_id) {
            return ['status' => 'requires_payment_method'];
        }

        $payment_hash = $row->payment_hash;

        if (!$payment_hash) {
            return $this->reject($row, 'payment hash missing');
        }

        $this->stripe->init();

        $pi ??= PaymentIntent::retrieve(['id' => $row->payment_intent_id, 'expand' => ['latest_charge']], $this->readOptions($row));

        /* A connection token lets a device create its own PaymentIntents: only ours are recorded */
        if ($pi->id != $row->payment_intent_id
            || ($pi->metadata->payment_hash ?? null) != $payment_hash->hash
            || (bool) $pi->livemode != $row->livemode
            || strtolower($pi->currency) != $row->currency) {
            return $this->reject($row, 'payment intent does not match its collection');
        }

        if ($pi->status == 'canceled') {
            $row->update(['status' => TerminalPaymentIntent::STATUS_CANCELED]);

            return ['status' => 'canceled'];
        }

        if (!in_array($pi->status, ['succeeded', 'requires_capture'])) {
            return ['status' => $pi->status];
        }

        $claimed = TerminalPaymentIntent::query()
            ->where('id', $row->id)
            ->whereIn('status', TerminalPaymentIntent::OPEN_STATUSES)
            ->where(fn ($query) => $query->where('status', '!=', TerminalPaymentIntent::STATUS_RECORDING)->orWhere('claimed_at', '<', now()->subMinutes(2)))
            ->update(['status' => TerminalPaymentIntent::STATUS_RECORDING, 'claimed_at' => now()]);

        if (!$claimed) {
            $row->refresh();

            return $row->status == TerminalPaymentIntent::STATUS_RECORDED ? $this->recordedResponse($row) : ['status' => 'processing'];
        }

        try {
            if ($pi->status == 'requires_capture') {
                $pi->capture([], $this->stripe->stripe_connect_auth);
                $pi = PaymentIntent::retrieve(['id' => $pi->id, 'expand' => ['latest_charge']], $this->readOptions($row));
            }

            return $this->recordCharge($row->fresh(), $pi, $payment_hash->fresh());
        } catch (\Throwable $e) {
            TerminalPaymentIntent::query()
                ->where('id', $row->id)
                ->where('status', TerminalPaymentIntent::STATUS_RECORDING)
                ->update(['status' => TerminalPaymentIntent::STATUS_OPEN, 'claimed_at' => null]);

            throw $e;
        }
    }

    /* Cancels only what Stripe cancels: a PaymentIntent in flight is left to be recorded */
    public function cancel(TerminalPaymentIntent $row): array
    {
        if ($row->status == TerminalPaymentIntent::STATUS_RECORDED) {
            return $this->recordedResponse($row);
        }

        if ($row->isFinal()) {
            return ['status' => $row->status];
        }

        if (!$row->payment_intent_id) {
            $row->update(['status' => TerminalPaymentIntent::STATUS_FAILED]);

            return ['status' => 'canceled'];
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $row->payment_intent_id, 'expand' => ['latest_charge']], $this->readOptions($row));

        if (in_array($pi->status, ['succeeded', 'requires_capture'])) {
            return $this->record($row, $pi);
        }

        if (in_array($pi->status, self::CANCELABLE)) {
            $pi = $pi->cancel([], $this->stripe->stripe_connect_auth);
        }

        if ($pi->status != 'canceled') {
            return ['status' => $pi->status];
        }

        $row->update(['status' => TerminalPaymentIntent::STATUS_CANCELED]);

        return ['status' => 'canceled'];
    }

    /* Stripe's own receipt carries the card network fields */
    public function receipt(TerminalPaymentIntent $row, string $email): void
    {
        $payment = Payment::withTrashed()->find($row->payment_id);

        if ($row->status != TerminalPaymentIntent::STATUS_RECORDED || !$payment) {
            throw new TerminalException('collection_closed');
        }

        $this->stripe->init();

        Charge::update($payment->transaction_reference, ['receipt_email' => $email], $this->stripe->stripe_connect_auth);
    }

    public static function isCardPresent(array $object): bool
    {
        $types = array_merge((array) ($object['payment_method_types'] ?? []), [$object['payment_method_details']['type'] ?? '']);

        return in_array('card_present', $types) || in_array('interac_present', $types);
    }

    private function resume(TerminalPaymentIntent $row, Invoice $invoice, array $data): array
    {
        if ($row->invoice_id != $invoice->id || abs($row->amount - $data['amount']) > 0.000001 || $row->apply_fee != ($data['apply_fee'] && $row->fee_gross > 0)) {
            throw new TerminalException('amount_mismatch');
        }

        if (in_array($row->status, [TerminalPaymentIntent::STATUS_CANCELED, TerminalPaymentIntent::STATUS_FAILED, TerminalPaymentIntent::STATUS_REJECTED])) {
            throw new TerminalException('collection_closed');
        }

        if (in_array($row->status, [TerminalPaymentIntent::STATUS_RECORDED, TerminalPaymentIntent::STATUS_RECORDING, TerminalPaymentIntent::STATUS_ORPHANED])) {
            throw new TerminalException('already_paid');
        }

        if (!$row->payment_intent_id) {
            return $this->openPaymentIntent($row);
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve($row->payment_intent_id, $this->stripe->stripe_connect_auth);

        if ($pi->status == 'canceled') {
            $row->update(['status' => TerminalPaymentIntent::STATUS_CANCELED]);

            throw new TerminalException('collection_closed');
        }

        $row->update(['touched_at' => now()]);

        return $this->intentResponse($row, $pi);
    }

    private function openPaymentIntent(TerminalPaymentIntent $row): array
    {
        $invoice = $row->invoice;

        $this->stripe->init();

        try {
            $pi = PaymentIntent::create([
                'amount' => $this->toStripe($invoice, $row->amount),
                'currency' => $row->currency,
                'payment_method_types' => $row->currency == 'cad' ? ['card_present', 'interac_present'] : ['card_present'],
                'capture_method' => 'automatic',
                'description' => $this->description($invoice),
                'metadata' => [
                    'payment_hash' => $row->payment_hash->hash,
                    'gateway_type_id' => (string) GatewayType::CARD_PRESENT,
                    'invoice_id' => (string) $invoice->hashed_id,
                    'invoice_number' => (string) $invoice->number,
                    'collection_id' => $row->collection_id,
                    'collected_by' => (string) $row->user_id,
                ],
            ], array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => "in_terminal_{$row->company_id}_{$row->invoice_id}_{$row->collection_id}"]));
        } catch (\Throwable $e) {
            $row->update(['status' => TerminalPaymentIntent::STATUS_FAILED]);

            throw $e;
        }

        $row->update([
            'payment_intent_id' => $pi->id,
            'status' => TerminalPaymentIntent::STATUS_OPEN,
            'livemode' => (bool) $pi->livemode,
            'touched_at' => now(),
        ]);

        return $this->intentResponse($row, $pi);
    }

    /* One open collection per invoice: a charged one is recorded, a live one refuses, a stale one is cancelled */
    private function settleOpenCollections(Invoice $invoice): void
    {
        $rows = TerminalPaymentIntent::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', TerminalPaymentIntent::OPEN_STATUSES)
            ->get();

        foreach ($rows as $row) {
            if (!$row->payment_intent_id) {
                if (!$row->isStale()) {
                    throw $this->collectionOpen($row);
                }

                $row->update(['status' => TerminalPaymentIntent::STATUS_FAILED]);

                continue;
            }

            $card_present = $row->company_gateway->driver($invoice->client)->cardPresent();

            try {
                $result = $card_present->record($row);
            } catch (ApiErrorException $e) {
                throw $this->collectionOpen($row);
            }

            if (in_array($result['status'], ['recorded', 'unapplied', 'processing'])) {
                throw new TerminalException('already_paid');
            }

            if ($row->fresh()->isFinal()) {
                continue;
            }

            if (!$row->isStale() || $card_present->cancel($row->fresh())['status'] != 'canceled') {
                throw $this->collectionOpen($row);
            }
        }
    }

    private function collectionOpen(TerminalPaymentIntent $row): TerminalException
    {
        return new TerminalException('collection_open', 409, [
            'payment_intent_id' => $row->payment_intent_id,
            'owner_user' => $row->user?->present()->name(),
            'age' => (int) $row->created_at->diffInSeconds(now(), true),
        ]);
    }

    private function validateAmount(Invoice $invoice, float $amount): void
    {
        if ($invoice->is_deleted || $invoice->is_proforma || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_PAID])) {
            throw ValidationException::withMessages(['invoice' => ctrans('texts.terminal_not_payable')]);
        }

        $precision = $invoice->client->currency()->precision;
        $payable = $invoice->status_id == Invoice::STATUS_DRAFT ? $invoice->amount : $invoice->balance;
        $fees_and_limits = $this->stripe->company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);
        $min = floatval($fees_and_limits->min_limit ?? -1);
        $max = floatval($fees_and_limits->max_limit ?? -1);

        if (round($amount, $precision) <= 0 || round($amount, $precision) > round($payable, $precision)
            || ($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
            throw ValidationException::withMessages(['amount' => ctrans('texts.terminal_invalid_amount')]);
        }
    }

    private function recordCharge(TerminalPaymentIntent $row, PaymentIntent $pi, PaymentHash $payment_hash): array
    {
        $charge = $pi->latest_charge;
        $charge_id = is_string($charge) ? $charge : $charge->id;

        $existing = $payment_hash->payment_id
            ? Payment::withTrashed()->find($payment_hash->payment_id)
            : StripePaymentDriver::findPaymentByStripeReference($row->company_id, ['id' => $pi->id, 'latest_charge' => $charge_id], true);

        if ($existing) {
            $row->update(['status' => TerminalPaymentIntent::STATUS_RECORDED, 'payment_id' => $existing->id]);

            return $this->recordedResponse($row, $charge);
        }

        $invoice = $row->invoice;
        $client = $invoice?->client()->withTrashed()->first();

        if (!$invoice || !$client) {
            $row->update(['status' => TerminalPaymentIntent::STATUS_ORPHANED]);
            $this->alert($row, 'charged, but the invoice and client no longer exist');

            return ['status' => 'orphaned'];
        }

        $this->stripe->setClient($client);

        $currency = $client->currency();
        $precision = $currency->precision;
        $received = round($this->stripe->convertFromStripeAmount($pi->amount_received, $precision, $currency), $precision);
        $details = is_string($charge) ? null : ($charge->payment_method_details ?? null);
        $method = $details->type ?? 'card_present';
        $card = $details->{$method} ?? null;

        $fee = 0;

        if ($row->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_STRIPE) {
            $fee = round($this->stripe->convertFromStripeAmount($pi->amount_details->surcharge->amount ?? 0, $precision, $currency), $precision);
        } elseif ($row->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT) {
            $fee = round($payment_hash->fee_total, $precision);
        }

        if ($fee > 0 && abs($received - ($row->amount + $fee)) > 0.000001) {
            $fee = 0;
        }

        /* A surcharge is never kept on a card that is not a credit card: it is refunded */
        if ($fee > 0 && ($card->funding ?? null) != 'credit') {
            Refund::create(['payment_intent' => $pi->id, 'amount' => $this->toStripe($invoice, $fee)], $this->stripe->stripe_connect_auth);
            $this->alert($row, 'surcharge refunded: the card charged was not a credit card');
            $received = round($received - $fee, $precision);
            $fee = 0;
        }

        $invoice_amount = min($row->amount, $received);
        $closed = $invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_DRAFT]);

        /* Paid elsewhere meanwhile: no surcharge line on it, the rest is unapplied */
        if (!$closed && round($invoice->balance, $precision) < $invoice_amount) {
            $fee = 0;
        }

        $data = $payment_hash->data;
        $data->invoices = $closed ? [] : [(object) [
            'invoice_id' => $invoice->hashed_id,
            'amount' => $invoice_amount,
            'invoice_number' => $invoice->number,
            'pre_payment' => false,
        ]];
        $data->fee_net = $fee > 0 ? $row->fee_net : 0;
        $data->amount_with_fee = round($invoice_amount + $fee, $precision);

        $payment_hash->data = $data;
        $payment_hash->fee_total = $closed ? 0 : $fee;
        $payment_hash->save();

        $payment = $this->stripe->setPaymentHash($payment_hash)->createPayment([
            'payment_type' => $method == 'interac_present' ? PaymentType::DEBIT : PaymentType::parseCardType(strtolower($card->brand ?? '')),
            'amount' => $received,
            'transaction_reference' => $charge_id,
            'gateway_type_id' => GatewayType::CARD_PRESENT,
            'idempotency_key' => $pi->id,
        ], Payment::STATUS_COMPLETED);

        /* createPayment counts only the invoice and fee towards paid to date; what was received beyond that still is */
        $unapplied = $closed ? $received : round($received - ($invoice_amount + $fee), $precision);

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

        $payment->user_id = $row->user_id;
        $payment->meta = $payment_meta;

        if ($closed) {
            $payment->private_notes = trim($payment->private_notes . "\n" . ctrans('texts.invoice') . ' ' . $invoice->number);
        }

        $payment->save();

        $row->update(['status' => TerminalPaymentIntent::STATUS_RECORDED, 'payment_id' => $payment->id]);

        $this->log($row, SystemLog::EVENT_GATEWAY_SUCCESS, 'recorded');

        return $this->recordedResponse($row, $charge, $closed);
    }

    private function setSurcharge(TerminalPaymentIntent $row, string $path, float $fee): void
    {
        $payment_hash = $row->payment_hash;

        $data = $payment_hash->data;
        $data->fee_net = $fee > 0 ? $row->fee_net : 0;
        $data->amount_with_fee = round($row->amount + $fee, 6);

        $payment_hash->data = $data;
        $payment_hash->fee_total = $fee;
        $payment_hash->save();

        $row->update(['surcharge_path' => $path, 'touched_at' => now()]);
    }

    private function reject(TerminalPaymentIntent $row, string $reason): array
    {
        $row->update(['status' => TerminalPaymentIntent::STATUS_REJECTED]);
        $this->alert($row, $reason);

        return ['status' => 'rejected'];
    }

    /* Charged money that could not be recorded as expected: logged, and reported to Sentry */
    private function alert(TerminalPaymentIntent $row, string $message): void
    {
        $this->log($row, SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE, $message);

        \Sentry\captureMessage("Terminal collection {$row->collection_id} ({$row->payment_intent_id}): {$message}");
    }

    /* Never the PaymentIntent itself: it carries a client secret and cardholder data */
    public function log(TerminalPaymentIntent $row, int $event_id, string $message): void
    {
        nlog("terminal: {$message} {$row->payment_intent_id}");

        SystemLogger::dispatch(
            ['message' => $message, 'collection_id' => $row->collection_id, 'payment_intent' => $row->payment_intent_id, 'amount' => $row->amount],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $event_id,
            SystemLog::TYPE_STRIPE,
            $row->invoice?->client,
            $row->company,
        );
    }

    /* Preview fields (the surcharge) are only ever read, so only reads name the preview version */
    private function readOptions(TerminalPaymentIntent $row): array
    {
        return $row->apply_fee
            ? array_merge($this->stripe->stripe_connect_auth, ['stripe_version' => self::SURCHARGE_PREVIEW_VERSION])
            : $this->stripe->stripe_connect_auth;
    }

    private function toStripe(Invoice $invoice, float $amount): int
    {
        $currency = $invoice->client->currency();

        return (int) $this->stripe->convertToStripeAmount($amount, $currency->precision, $currency);
    }

    private function description(Invoice $invoice, ?float $surcharge = null): string
    {
        $description = ctrans('texts.invoice') . ' ' . $invoice->number;

        if ($surcharge) {
            $description .= ' (' . ctrans('texts.credit_card_surcharge') . ' ' . Number::formatMoney($surcharge, $invoice->client) . ')';
        }

        return $description;
    }

    private function intentResponse(TerminalPaymentIntent $row, PaymentIntent $pi): array
    {
        return [
            'collection_id' => $row->collection_id,
            'payment_intent_id' => $pi->id,
            'client_secret' => $pi->client_secret,
            'amount' => $row->amount,
            'fee_gross' => $row->fee_gross,
            'total' => round($row->amount + ($row->apply_fee ? $row->fee_gross : 0), 6),
            'currency' => $row->currency,
            'livemode' => $row->livemode,
        ];
    }

    private function finalizeResponse(TerminalPaymentIntent $row, bool $recollect, ?string $funding = null): array
    {
        $row->refresh();
        $surcharge = $row->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_NONE ? 0 : $row->payment_hash->fee_total;

        return [
            'path' => $row->surcharge_path,
            'recollect' => $recollect,
            'funding' => $funding,
            'amount' => round($row->amount + $surcharge, 6),
            'amount_minor' => $this->toStripe($row->invoice, $row->amount + $surcharge),
            'surcharge' => round($surcharge, 6),
            'surcharge_minor' => $this->toStripe($row->invoice, $surcharge),
        ];
    }

    private function recordedResponse(TerminalPaymentIntent $row, $charge = null, bool $closed = false): array
    {
        $payment = Payment::withTrashed()->find($row->payment_id);

        return [
            'status' => $closed ? 'unapplied' : 'recorded',
            'payment_id' => $payment?->hashed_id,
            'applied' => round($payment->applied ?? 0, 6),
            'unapplied' => round(($payment->amount ?? 0) - ($payment->applied ?? 0), 6),
            'invoice_balance' => $row->invoice?->fresh()->balance,
            'brand' => $payment->meta->brand ?? null,
            'last4' => $payment->meta->last4 ?? null,
            'receipt_url' => is_object($charge) ? ($charge->receipt_url ?? null) : null,
        ];
    }
}
