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
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\TerminalDeclinedMailer;
use App\Jobs\Util\SystemLogger;
use App\Mail\Admin\TerminalDeclineReceiptObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Currency;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Models\User;
use App\PaymentDrivers\Stripe\Jobs\ReconcileTerminalPayment;
use App\PaymentDrivers\Stripe\Terminal\RecordTerminalPayment;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\IdempotencyException;
use Stripe\PaymentIntent;
use Stripe\Terminal\ConnectionToken;
use Stripe\Terminal\Location;

/**
 * In-person card payments (Stripe Terminal): the device reads the card, we create and record every payment.
 *
 * A collection is the PaymentHash its PaymentIntent pays: the invoice is kept where the hash always
 * keeps it, and the collection's own state under data->terminal. The driver carries the collection's
 * gateway, client and payment hash; the invoice is passed through each operation.
 */
class CardPresent
{
    public const STATUS_CREATING = 1;

    public const STATUS_OPEN = 2;

    public const STATUS_RECORDED = 3;

    public const STATUS_CANCELLED = 4;

    public const STATUS_FAILED = 5;

    public const STATUS_ORPHANED = 6;

    public const STATUS_REJECTED = 7;

    /* No second collection on an invoice while one of these exists */
    public const OPEN_STATUSES = [self::STATUS_CREATING, self::STATUS_OPEN];

    /* Stripe's minor unit exponent for each Terminal currency */
    public const CURRENCY_EXPONENTS = [
        'usd' => 2, 'cad' => 2, 'gbp' => 2, 'eur' => 2, 'aud' => 2, 'nzd' => 2, 'sgd' => 2, 'chf' => 2,
        'dkk' => 2, 'sek' => 2, 'nok' => 2, 'czk' => 2, 'pln' => 2, 'myr' => 2, 'jpy' => 0,
    ];

    /* Address fields Stripe requires for a Terminal location */
    private const LOCATION_FIELDS = [
        'US' => ['line1', 'city', 'state', 'postal_code'], 'CA' => ['line1', 'city', 'state', 'postal_code'],
        'AU' => ['line1', 'city', 'state', 'postal_code'], 'IT' => ['line1', 'city', 'state', 'postal_code'],
        'ES' => ['line1', 'city', 'state', 'postal_code'], 'JP' => ['line1', 'city', 'state', 'postal_code'],
        'IE' => ['line1', 'postal_code'], 'SG' => ['line1', 'postal_code'],
    ];

    private const CANCELLABLE = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    /* The declined charge this instance last answered with, so the same look need not ask Stripe again */
    private ?Charge $declined_charge = null;

    public function __construct(public StripePaymentDriver $stripe)
    {
    }

    /* The driver for an existing collection: its own gateway, its invoice's client and its payment hash */
    public static function forCollection(PaymentHash $payment_hash, ?Client $client = null): self
    {
        return CompanyGateway::withTrashed()
            ->findOrFail($payment_hash->data->terminal->company_gateway_id)
            ->driver($client ?? $payment_hash->fee_invoice?->client)
            ->setPaymentHash($payment_hash)
            ->cardPresent();
    }

    /**
     * An invoice's collections, by the index on fee_invoice_id
     *
     * @return Builder<PaymentHash>
     */
    public static function collections(Invoice $invoice): Builder
    {
        return PaymentHash::query()
            ->where('fee_invoice_id', $invoice->id)
            ->whereNotNull('data->terminal');
    }

    /* The figures the device shows before the card is read: the amount, in the client's currency */
    public function quote(float $amount): array
    {
        $client = $this->stripe->client;
        $amount = round($amount, $client->currency()->precision);

        return [
            'amount' => $amount,
            'total' => $amount,
            'currency_id' => $client->getSetting('currency_id'),
        ];
    }

    public function record(?Invoice $invoice, ?PaymentIntent $pi = null): array
    {
        return (new RecordTerminalPayment($this))->run($invoice, $pi);
    }

    /* The app's own complete: a declined card it is answered with is shown on the device, so nobody is emailed about that charge */
    public function complete(Invoice $invoice): array
    {
        $result = $this->record($invoice);

        if (($result['declined'] ?? false) && $this->declined_charge) {
            $this->updateState(['decline_seen_charge' => $this->declined_charge->id]);
        }

        return $result;
    }

    /**
     * Settles an invoice's open collections now (a charge is recorded, an abandoned one cancelled)
     * before something else is charged for it: true while one is still being taken.
     */
    public static function settle(Invoice $invoice, string $db): bool
    {
        $open = fn () => self::collections($invoice)
            ->whereIn('data->terminal->status_id', self::OPEN_STATUSES)
            ->where('created_at', '>', now()->subDays(30));

        $open()->pluck('id')->each(fn ($id) => ReconcileTerminalPayment::dispatchSync($id, $db, false));

        return $open()->exists();
    }

    /* The collection's own state */
    public function state(): \stdClass
    {
        return $this->stripe->payment_hash->data->terminal;
    }

    /* One UPDATE, key by key, never the whole of data, and only while it is open: false once another request settled it */
    public function updateState(array $values, bool $while_open = true): bool
    {
        $payment_hash = $this->stripe->payment_hash;

        $changed = PaymentHash::query()
            ->whereKey($payment_hash->id)
            ->when($while_open, fn ($query) => $query->whereIn('data->terminal->status_id', self::OPEN_STATUSES))
            ->update(collect($values)->mapWithKeys(fn ($value, $key) => ["data->terminal->{$key}" => $value])->all());

        /* Settled by another request meanwhile: what it wrote stands, and this copy catches up */
        if (!$changed) {
            $payment_hash->refresh();

            return false;
        }

        $data = $payment_hash->data;

        foreach ($values as $key => $value) {
            $data->terminal->{$key} = $value;
        }

        $payment_hash->data = $data;
        $payment_hash->updated_at = now();
        $payment_hash->syncOriginal();

        return true;
    }

    /* The base amount: the invoice part of the payment hash, until recording settles it */
    public function amount(): float
    {
        return (float) collect($this->stripe->payment_hash->invoices())->sum('amount');
    }

    public function isFinal(): bool
    {
        return !in_array($this->state()->status_id, self::OPEN_STATUSES);
    }

    /* Untouched for 20 minutes: abandoned, unless Stripe says it was charged */
    public function isStale(): bool
    {
        return $this->stripe->payment_hash->updated_at->lt(now()->subMinutes(20));
    }

    /* The client's company where there is a client: the instance createPayment uses too */
    public function company(): Company
    {
        return $this->stripe->client->company ?? $this->stripe->company_gateway->company;
    }

    /* Scoped to one of the gateway's own Locations: an unscoped token reaches every reader on the account */
    public function connectionToken(string $location_id): string
    {
        if (!collect($this->locations())->contains('id', $location_id)) {
            throw new TerminalException('location_required', 422);
        }

        $this->stripe->init();

        return ConnectionToken::create(['location' => $location_id], $this->stripe->stripe_connect_auth)->secret;
    }

    public function locations(): array
    {
        return Cache::remember($this->cacheKey('locations'), 600, function () {
            $this->stripe->init();

            return collect(Location::all(['limit' => 100], $this->stripe->stripe_connect_auth)->data)
                ->map(fn ($location) => ['id' => $location->id, 'display_name' => $location->display_name])
                ->toArray();
        });
    }

    public function createLocation(): array
    {
        $company = $this->stripe->company_gateway->company;
        $country = $company->country()?->iso_3166_2;

        $address = array_filter([
            'line1' => trim($company->settings->address1 ?? ''),
            'line2' => trim($company->settings->address2 ?? ''),
            'city' => trim($company->settings->city ?? ''),
            'state' => trim($company->settings->state ?? ''),
            'postal_code' => trim($company->settings->postal_code ?? ''),
            'country' => $country,
        ]);

        $required = array_merge(self::LOCATION_FIELDS[$country] ?? ['line1', 'city', 'postal_code'], ['country']);

        if ($missing = array_values(array_diff($required, array_keys($address)))) {
            throw new TerminalException('location_address_required', 422, ['missing' => $missing]);
        }

        $this->stripe->init();

        $location = Location::create([
            'display_name' => Str::limit($company->present()->name(), 100, ''),
            'address' => $address,
        ], $this->stripe->stripe_connect_auth);

        Cache::forget($this->cacheKey('locations'));

        return ['id' => $location->id, 'display_name' => $location->display_name];
    }

    public function account(): array
    {
        return Cache::remember($this->cacheKey('account'), 600, function () {
            $this->stripe->init();

            $account = Account::retrieve(null, $this->stripe->stripe_connect_auth);

            return [
                'country' => strtoupper($account->country ?? ''),
                'default_currency' => strtolower($account->default_currency ?? ''),
                'card_payments_active' => ($account->charges_enabled ?? false) && ($account->capabilities->card_payments ?? 'active') == 'active',
            ];
        });
    }

    public function livemode(): bool
    {
        $key = $this->stripe->stripe_connect ? config('ninja.ninja_stripe_key') : $this->stripe->company_gateway->getConfigField('apiKey');

        return str_contains($key ?? '', '_live_');
    }

    /* One PaymentIntent per collection */
    public function createPaymentIntent(User $user, Invoice $invoice, array $data): array
    {
        $existing = self::collections($invoice)->where('data->terminal->collection_id', $data['collection_id'])->first();

        if ($existing) {
            $this->stripe->setPaymentHash($existing);

            return $this->resume($invoice, $data);
        }

        $account = $this->account();

        if (!$account['card_payments_active']) {
            throw new TerminalException('not_enabled', 422);
        }

        $currency = $this->stripe->client->currency();

        if ((self::CURRENCY_EXPONENTS[strtolower($currency->code)] ?? -1) != $currency->precision || strtolower($currency->code) != $account['default_currency']) {
            throw new TerminalException('unsupported_currency', 422, [], ['currency' => strtoupper($account['default_currency'])]);
        }

        $this->settleOpenCollections($invoice);

        $payment_hash = DB::transaction(function () use ($user, &$invoice, $data, $currency) {
            /* Read again under a lock: the balance may have moved since the request was bound */
            $invoice = Invoice::withTrashed()->lockForUpdate()->find($invoice->id)->setRelation('client', $this->stripe->client);

            if ($open = self::collections($invoice)->whereIn('data->terminal->status_id', self::OPEN_STATUSES)->first()) {
                throw self::collectionOpen($open);
            }

            $this->validateAmount($invoice, $data['amount']);

            $amount = $this->quote($data['amount'])['amount'];

            if ($invoice->status_id == Invoice::STATUS_DRAFT) {
                if ($this->company()->verifactuEnabled()) {
                    throw new TerminalException('send_invoice_first', 422);
                }

                $invoice = $invoice->service()->markSent()->save();
            }

            return PaymentHash::create([
                'hash' => Str::random(32),
                'data' => [
                    'amount_with_fee' => $amount,
                    'fee_net' => 0,
                    'invoices' => [
                        [
                            'invoice_id' => $invoice->hashed_id,
                            'amount' => $amount,
                            'invoice_number' => $invoice->number,
                            'pre_payment' => false,
                        ],
                    ],
                    'terminal' => [
                        'collection_id' => $data['collection_id'],
                        'company_gateway_id' => $this->stripe->company_gateway->id,
                        'user_id' => $user->id,
                        'status_id' => self::STATUS_CREATING,
                        'payment_intent_id' => null,
                        'currency' => strtolower($currency->code),
                        'livemode' => $this->livemode(),
                        'receipts_sent' => 0,
                        'decline_receipts_sent' => 0,
                    ],
                ],
                'fee_total' => 0,
                'fee_invoice_id' => $invoice->id,
            ]);
        });

        $this->stripe->setPaymentHash($payment_hash);

        return $this->openPaymentIntent($invoice);
    }

    /**
     * After the card is read and before the device confirms it: the invoice must still take the amount.
     * Answers the amount the device may confirm.
     */
    public function finalize(Invoice $invoice): array
    {
        $state = $this->state();

        if ($state->status_id != self::STATUS_OPEN || !$state->payment_intent_id) {
            throw new TerminalException('collection_closed');
        }

        /* The client's currency changed since: the PaymentIntent is in one the invoice no longer is */
        if (strtolower($this->stripe->client->currency()->code) != $state->currency) {
            throw new TerminalException('unsupported_currency', 422, [], ['currency' => strtoupper($state->currency)]);
        }

        if ($invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])
            || $this->toStripe($invoice->balance) < $this->toStripe($this->amount())) {
            throw new TerminalException('balance_changed', 409, ['balance' => $invoice->balance]);
        }

        return [
            /* The amount never changes after the card is read, so it is never read again */
            'recollect' => false,
            'amount' => $this->amount(),
            'amount_minor' => $this->toStripe($this->amount()),
        ];
    }

    /* Cancels only what Stripe cancels: a PaymentIntent in flight is left to be recorded */
    public function cancel(?Invoice $invoice): array
    {
        if ($this->isFinal()) {
            return $this->record($invoice);
        }

        if (!$this->state()->payment_intent_id) {
            $this->updateState(['status_id' => self::STATUS_FAILED]);

            return ['status' => 'canceled'];
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $this->state()->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        if ($pi->status == 'succeeded') {
            return $this->record($invoice, $pi);
        }

        if (in_array($pi->status, self::CANCELLABLE)) {
            $pi = $pi->cancel([], $this->stripe->stripe_connect_auth);
        }

        if ($pi->status != 'canceled') {
            return ['status' => $pi->status];
        }

        $this->updateState(['status_id' => self::STATUS_CANCELLED]);

        return ['status' => 'canceled'];
    }

    /* Stripe's own receipt carries the card network fields; a declined card gets ours */
    public function receipt(string $email): void
    {
        $payment = $this->stripe->payment_hash->payment;

        if ($this->state()->status_id != self::STATUS_RECORDED || !$payment) {
            $this->declineReceipt($email);

            return;
        }

        /* Stripe sends it, outside the account's email limits: a few per payment */
        if ($this->state()->receipts_sent >= 3) {
            throw new TerminalException('receipt_limit', 429);
        }

        $this->stripe->init();

        Charge::update($payment->transaction_reference, ['receipt_email' => $email], $this->stripe->stripe_connect_auth);

        $this->updateState(['receipts_sent' => $this->state()->receipts_sent + 1], false);
    }

    /**
     * The card the customer presented last was declined: Stripe left the PaymentIntent waiting for another
     * card (or it was cancelled since, when $or_cancelled) and its latest charge failed. A processing
     * PaymentIntent can still carry an earlier card's failed charge, so it never counts. Read from Stripe, never stored.
     */
    public function declinedCharge(PaymentIntent $pi, bool $or_cancelled = false): ?Charge
    {
        $latest = $pi->latest_charge ?? null;

        if (!$latest || !in_array($pi->status, $or_cancelled ? ['requires_payment_method', 'canceled'] : ['requires_payment_method'])) {
            return null;
        }

        $charge = is_string($latest) ? Charge::retrieve($latest, $this->stripe->stripe_connect_auth) : $latest;

        return ($charge->status ?? null) == 'failed' ? $charge : null;
    }

    /* What an answer adds for a declined card: the card, never why it was declined */
    public function declineFacts(PaymentIntent $pi): array
    {
        $charge = $this->declined_charge = $this->declinedCharge($pi);

        if (!$charge) {
            return [];
        }

        $card = $this->card($charge);

        return ['declined' => true, 'brand' => $card->brand ?? null, 'last4' => $card->last4 ?? null];
    }

    /**
     * From reconciliation, at a look while the collection is idle and before an abandoned one is cancelled:
     * a card declined while nobody was watching is told to the user who started it. Once per failed charge,
     * and never for one the app was answered with (decline_seen_charge).
     *
     * At most once: the charge is claimed before the mailer is queued, and the claim is given back only when
     * queueing it fails. Once queued, a mail that can't be sent is not tried again by a later look.
     */
    public function notifyDecline(?Invoice $invoice): void
    {
        $state = $this->state();

        if (!$invoice || !$this->stripe->client || !$state->payment_intent_id) {
            return;
        }

        $charge = $this->declined_charge;

        if (!$charge) {
            $this->stripe->init();

            $charge = $this->declinedCharge(PaymentIntent::retrieve(['id' => $state->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth));
        }

        if (!$charge || in_array($charge->id, [$state->decline_seen_charge ?? null, $state->decline_notified_charge ?? null])) {
            return;
        }

        $previous = $state->decline_notified_charge ?? null;

        if (!$this->claimDeclineNotice($charge->id)) {
            return;
        }

        try {
            TerminalDeclinedMailer::dispatch($invoice, $this->company(), (int) $state->user_id, $this->chargeAmount($charge));
        } catch (\Throwable $e) {
            $this->releaseDeclineNotice($charge->id, $previous);

            throw $e;
        }
    }

    public static function isCardPresent(array $object): bool
    {
        $types = array_merge((array) ($object['payment_method_types'] ?? []), [$object['payment_method_details']['type'] ?? '']);

        return in_array('card_present', $types) || in_array('interac_present', $types);
    }

    /* In the client's currency, unless another is given */
    public function toStripe(float $amount, ?Currency $currency = null): int
    {
        $currency ??= $this->stripe->client->currency();

        /* Rounded, not truncated: a zero-decimal currency comes back as the raw float */
        return (int) round($this->stripe->convertToStripeAmount($amount, $currency->precision, $currency));
    }

    /* Charged money that was not recorded as expected */
    public function alert(string $message): void
    {
        $this->log(SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE, $message);

        \Sentry\captureMessage("Terminal payment {$this->state()->payment_intent_id}: {$message}");
    }

    /* Never the PaymentIntent itself: it carries a client secret and cardholder data */
    public function log(int $event_id, string $message): void
    {
        $state = $this->state();

        nlog("terminal: {$message} {$state->payment_intent_id}");

        SystemLogger::dispatch(
            ['message' => $message, 'collection_id' => $state->collection_id, 'payment_intent' => $state->payment_intent_id, 'amount' => $this->amount()],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $event_id,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->company(),
        );
    }

    /* Our receipt for a declined card, open or cancelled since, with its own limit */
    private function declineReceipt(string $email): void
    {
        $state = $this->state();

        if (!in_array($state->status_id, [self::STATUS_OPEN, self::STATUS_CANCELLED]) || !$state->payment_intent_id) {
            throw new TerminalException('collection_closed');
        }

        /* Sent from the company's own email, so it counts against its limits: a few per collection */
        if (($state->decline_receipts_sent ?? 0) >= 3) {
            throw new TerminalException('receipt_limit', 429);
        }

        $invoice = $this->stripe->payment_hash->fee_invoice;

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $state->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        $charge = $this->declinedCharge($pi, true);

        if (!$charge || !$invoice || !$this->stripe->client) {
            throw new TerminalException('collection_closed');
        }

        /* Counted before it is sent, and given back should it not be queued */
        if (!$this->countDeclineReceipt(1)) {
            throw new TerminalException('receipt_limit', 429);
        }

        $card = $this->card($charge);
        $client = $this->stripe->client;
        $company = $this->company();

        $recipient = new ClientContact();
        $recipient->email = $email;
        $recipient->client_id = $client->id;
        $recipient->company_id = $company->id;

        try {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new NinjaMailer((new TerminalDeclineReceiptObject(
                $company,
                $client,
                (string) $invoice->number,
                $this->chargeAmount($charge),
                (string) ($card->brand ?? ''),
                (string) ($card->last4 ?? ''),
                (int) ($charge->created ?? now()->timestamp),
            ))->build());
            $nmo->company = $company;
            $nmo->to_user = $recipient;
            $nmo->settings = $client->getMergedSettings();

            NinjaMailerJob::dispatch($nmo);
        } catch (\Throwable $e) {
            $this->countDeclineReceipt(-1);

            throw $e;
        }
    }

    /* One more (or one fewer) decline receipt, only while fewer than three were sent: one UPDATE, so two requests can't both take the last */
    private function countDeclineReceipt(int $by): bool
    {
        $path = "'$.terminal.decline_receipts_sent'";
        $sent = "CAST(COALESCE(JSON_EXTRACT(data, {$path}), 0) AS SIGNED)";

        $counted = PaymentHash::query()
            ->whereKey($this->stripe->payment_hash->id)
            ->whereRaw($by > 0 ? "{$sent} < 3" : "{$sent} > 0")
            ->toBase()
            ->update(['data' => DB::raw("JSON_SET(data, {$path}, {$sent} + ({$by}))")]);

        if (!$counted) {
            return false;
        }

        $this->mirrorState(['decline_receipts_sent' => ($this->state()->decline_receipts_sent ?? 0) + $by]);

        return true;
    }

    /* Only the first look to find this charge declined: the user is told once */
    private function claimDeclineNotice(string $charge_id): bool
    {
        $payment_hash = $this->stripe->payment_hash;
        $at = now()->toIso8601String();

        /* Without touching updated_at: the collection stays idle, so the same look still cancels it once abandoned */
        $claimed = PaymentHash::query()
            ->whereKey($payment_hash->id)
            ->whereIn('data->terminal->status_id', self::OPEN_STATUSES)
            ->where(fn ($query) => $query->whereNull('data->terminal->decline_notified_charge')->orWhere('data->terminal->decline_notified_charge', '!=', $charge_id))
            /* The app's complete may have shown it since this copy was read */
            ->where(fn ($query) => $query->whereNull('data->terminal->decline_seen_charge')->orWhere('data->terminal->decline_seen_charge', '!=', $charge_id))
            ->toBase()
            ->update(['data->terminal->decline_notified_charge' => $charge_id, 'data->terminal->decline_notified_at' => $at]);

        if (!$claimed) {
            return false;
        }

        $this->mirrorState(['decline_notified_charge' => $charge_id, 'decline_notified_at' => $at]);

        return true;
    }

    /* The mailer could not be queued: a later look tries again */
    private function releaseDeclineNotice(string $charge_id, ?string $previous): void
    {
        PaymentHash::query()
            ->whereKey($this->stripe->payment_hash->id)
            ->where('data->terminal->decline_notified_charge', $charge_id)
            ->toBase()
            ->update(['data->terminal->decline_notified_charge' => $previous, 'data->terminal->decline_notified_at' => null]);

        $this->mirrorState(['decline_notified_charge' => $previous, 'decline_notified_at' => null]);
    }

    /* A write made without updated_at, mirrored on the instance this operation holds */
    private function mirrorState(array $values): void
    {
        $payment_hash = $this->stripe->payment_hash;
        $data = $payment_hash->data;

        foreach ($values as $key => $value) {
            $data->terminal->{$key} = $value;
        }

        $payment_hash->data = $data;
        $payment_hash->syncOriginal();
    }

    /* The card a charge was read from: only its brand and last four digits are ever used */
    private function card(Charge $charge): ?\Stripe\StripeObject
    {
        $card = $charge->payment_method_details->{$charge->payment_method_details->type ?? 'card_present'} ?? null;

        return $card instanceof \Stripe\StripeObject ? $card : null;
    }

    /* What the declined charge asked for, in the currency the collection was taken in */
    private function chargeAmount(Charge $charge): string
    {
        $state = $this->state();

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> $currencies */
        $currencies = app('currencies');
        $currency = $currencies->first(fn ($currency) => strtolower($currency->code) == $state->currency);

        $amount = round($this->stripe->convertFromStripeAmount((int) ($charge->amount ?? 0), $currency->precision, $currency), $currency->precision);

        /* The client's currency changed since: the amount is shown in the one it was asked in */
        if (strtolower($this->stripe->client->currency()->code) != $state->currency) {
            return Number::formatValue($amount, $currency) . ' ' . strtoupper($state->currency);
        }

        return Number::formatMoney($amount, $this->stripe->client);
    }

    private function resume(Invoice $invoice, array $data): array
    {
        $state = $this->state();

        if ($state->company_gateway_id != $this->stripe->company_gateway->id) {
            throw new TerminalException('amount_mismatch');
        }

        if (in_array($state->status_id, [self::STATUS_CANCELLED, self::STATUS_FAILED, self::STATUS_REJECTED])) {
            throw new TerminalException('collection_closed');
        }

        if ($this->isFinal()) {
            throw new TerminalException('already_paid');
        }

        /* Recording rewrites the invoice part: compared only while it is open */
        if ($this->toStripe($this->amount()) != $this->toStripe($data['amount'])) {
            throw new TerminalException('amount_mismatch');
        }

        if (!$state->payment_intent_id) {
            return $this->openPaymentIntent($invoice);
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $state->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        $this->refuseIfSettled($invoice, $pi);

        $this->stripe->payment_hash->touch();

        /* Followed until it is final, should the first dispatch have been lost */
        $this->follow();

        return $this->intentResponse($pi);
    }

    /* Charged or cancelled at Stripe (by reconciliation, or on another device): a charge is recorded, and neither takes another step */
    private function refuseIfSettled(Invoice $invoice, PaymentIntent $pi): void
    {
        if ($pi->status == 'succeeded') {
            $this->record($invoice, $pi);

            throw new TerminalException('already_paid');
        }

        if ($pi->status == 'canceled') {
            $this->updateState(['status_id' => self::STATUS_CANCELLED]);

            throw new TerminalException('collection_closed');
        }
    }

    private function openPaymentIntent(Invoice $invoice): array
    {
        $state = $this->state();

        $this->stripe->init();

        try {
            $pi = PaymentIntent::create([
                'amount' => $this->toStripe($this->amount()),
                'currency' => $state->currency,
                'payment_method_types' => $state->currency == 'cad' ? ['card_present', 'interac_present'] : ['card_present'],
                'capture_method' => 'automatic',
                'description' => $this->description($invoice),
                'metadata' => [
                    'payment_hash' => $this->stripe->payment_hash->hash,
                    'gateway_type_id' => (string) GatewayType::CARD_PRESENT,
                    'invoice_id' => (string) $invoice->hashed_id,
                    'invoice_number' => (string) $invoice->number,
                    'collection_id' => $state->collection_id,
                ],
            ], array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => "in_terminal_{$invoice->company_id}_{$invoice->id}_{$state->collection_id}"]));
        } catch (ApiConnectionException|IdempotencyException $e) {
            throw $e; // Stripe may have created it, or is creating it for the same key: resuming finds out
        } catch (ApiErrorException $e) {
            $this->updateState(['status_id' => self::STATUS_FAILED]);

            throw $e;
        }

        $opened = $this->updateState([
            'payment_intent_id' => $pi->id,
            'status_id' => self::STATUS_OPEN,
            'livemode' => $pi->livemode,
        ]);

        /* Given up on while Stripe created it: nothing may ever charge it */
        if (!$opened) {
            $pi->cancel([], $this->stripe->stripe_connect_auth);

            throw new TerminalException('collection_closed');
        }

        /* Records the charge should nobody report it, and cancels the collection should it be abandoned */
        $this->follow();

        return $this->intentResponse($pi);
    }

    /* One collection per invoice: a charged one is recorded, a live one refuses, a stale one is cancelled */
    private function settleOpenCollections(Invoice $invoice): void
    {
        $open = self::collections($invoice)->whereIn('data->terminal->status_id', self::OPEN_STATUSES)->get();

        foreach ($open as $payment_hash) {
            /* Another collection: this request's client and invoice, its own gateway */
            $company_gateway = $payment_hash->data->terminal->company_gateway_id == $this->stripe->company_gateway->id
                ? $this->stripe->company_gateway
                : CompanyGateway::withTrashed()->findOrFail($payment_hash->data->terminal->company_gateway_id);

            $collection = $company_gateway->driver($this->stripe->client)->setPaymentHash($payment_hash)->cardPresent();

            /* Stripe never answered its create: after two minutes it isn't coming */
            if (!$collection->state()->payment_intent_id && $payment_hash->created_at->lt(now()->subMinutes(2))) {
                $collection->updateState(['status_id' => self::STATUS_FAILED]);

                continue;
            }

            try {
                $result = $collection->state()->payment_intent_id ? $collection->record($invoice) : ['status' => 'creating'];
            } catch (ApiErrorException $e) {
                throw self::collectionOpen($payment_hash);
            }

            if (in_array($result['status'], ['recorded', 'unapplied', 'recording', 'processing'])) {
                throw new TerminalException('already_paid');
            }

            if ($collection->isFinal()) {
                continue;
            }

            if (!$collection->isStale()) {
                throw self::collectionOpen($payment_hash);
            }

            try {
                $cancelled = $collection->cancel($invoice)['status'] == 'canceled';
            } catch (ApiErrorException $e) {
                $cancelled = false;
            }

            if (!$cancelled) {
                throw self::collectionOpen($payment_hash);
            }
        }
    }

    private static function collectionOpen(PaymentHash $payment_hash): TerminalException
    {
        $state = $payment_hash->data->terminal;

        return new TerminalException('collection_open', 409, [
            'payment_intent_id' => $state->payment_intent_id,
            'user' => User::withTrashed()->find($state->user_id)?->present()->name(),
            'age' => (int) $payment_hash->created_at->diffInSeconds(now(), true),
        ]);
    }

    private function validateAmount(Invoice $invoice, float $amount): void
    {
        if ($invoice->is_deleted || $invoice->is_proforma || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_PAID])) {
            throw ValidationException::withMessages(['invoice' => ctrans('texts.terminal_not_payable')]);
        }

        $payable = $invoice->status_id == Invoice::STATUS_DRAFT ? $invoice->amount : $invoice->balance;
        $fees_and_limits = $this->stripe->company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);
        $min = floatval($fees_and_limits->min_limit ?? -1);
        $max = floatval($fees_and_limits->max_limit ?? -1);

        if ($this->toStripe($amount) <= 0 || $this->toStripe($amount) > $this->toStripe($payable)
            || ($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
            throw ValidationException::withMessages(['amount' => ctrans('texts.terminal_invalid_amount')]);
        }
    }

    /* The reconciliation job; on the sync driver a delayed job runs now and never again, so auto-bill and the next collection settle it there */
    private function follow(): void
    {
        if (config('queue.default') != 'sync') {
            ReconcileTerminalPayment::dispatch($this->stripe->payment_hash->id, $this->company()->db)->delay(now()->addSeconds(ReconcileTerminalPayment::FIRST_LOOK));
        }
    }

    private function description(Invoice $invoice): string
    {
        return ctrans('texts.invoice') . ' ' . $invoice->number;
    }

    private function cacheKey(string $name): string
    {
        $gateway = $this->stripe->company_gateway;

        return 'terminal_' . $name . ':' . config('database.default') . ':' . $gateway->id . ':' . md5($gateway->getConfigField('account_id') . $gateway->getConfigField('apiKey'));
    }

    private function intentResponse(PaymentIntent $pi): array
    {
        $state = $this->state();

        return [
            'collection_id' => $state->collection_id,
            'payment_intent_id' => $pi->id,
            'client_secret' => $pi->client_secret,
            'amount' => $this->amount(),
            'total' => $this->amount(),
            'currency' => $state->currency,
            'livemode' => $state->livemode,
        ];
    }
}
