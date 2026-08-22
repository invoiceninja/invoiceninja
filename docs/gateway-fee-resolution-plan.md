# Gateway fee: root cause and resolution

Status: **implemented and verified.** Phases 1 and 2 applied; phase 4 (drain cleanup)
pending one release.

### Verification

Roughly 2,000 tests executed. Every affected area passes; every non-green result was proven
pre-existing by stashing the change set, re-running, and restoring.

| Suite | Result |
|---|---|
| Unit (full) | 299 tests, 22,778 assertions |
| Integration (full) | 536 tests, 1,707 assertions (1 pre-existing, see below) |
| Feature - EInvoice / EDocument | 428 tests (1 pre-existing) |
| Feature - PaymentDrivers (all six) | 54 (1 pre-existing, 1 needs a live Stripe key) |
| Feature - ClientPortal, Payments | 41 (1 pre-existing) |
| Feature - invoice/payment top level, 28 suites | all pass except 3 pre-existing |
| Feature - PayPal, PaymentLink, Cron, Ninja, Client, Shop, Jobs | 51 |
| **Gateway fee suites** | **60 tests, 217 assertions** |

### A real defect found by parallel testing

`GatewayFeeParallelConfirmTest` starts genuine OS processes that confirm against one invoice
row simultaneously. At five-way contention **a fee was silently lost**: `MAX_ATTEMPTS` was 3
with no backoff, so contenders retried in lockstep and one exhausted its attempts.

Fixed in `ConfirmGatewayFee`: `MAX_ATTEMPTS` raised to 12, jittered backoff
(`usleep(random_int(1000, 5000) * $attempt)`) so writers de-synchronise, and the exhaustion
path now logs at ALERT naming the invoice and hash. Verified at 2, 4 (same hash), 5 and 10
way contention.

This is the case the earlier draft of this plan dismissed as one that "should not occur in
practice". It occurred on the first honest test of it.

### Pre-existing defects surfaced (not caused here, worth separate tickets)

1. `app/Listeners/Invoice/InvoiceReversedActivity.php:49` reads `$event->invitation`, but
   `InvoiceWasReversed::__construct` never sets it. Breaks invoice reversal whenever
   `event_vars['user_id']` is null. Hits `EntityPaidToDateTest`, `InvoiceTest`,
   `ReverseInvoiceTest`.
2. `app/Mail/Admin/ClientPaymentFailureObject.php:120` - "Unable to find invitation for
   reference" on the PayFast failure-mail path.
3. `PaymentTest::testNegativePaymentPaidToDate` - a negative payment leaves `paid_to_date`
   at -500 rather than 0.
4. `VerifactuApiTest::test_staged_full_cancellation_generates_correct_status` - 500 on
   invoice creation.
5. `tests/Integration/UniqueEmailTest::tearDown()` deletes all users on both `db-ninja-01`
   and `db-ninja-02`. With `MULTI_DB_ENABLED=false` both resolve to the same database, so
   the second delete blocks on the first one's uncommitted locks and times out. The test
   deadlocks against itself. No data is lost, but it cannot pass on a single-database config.

---

## 1. Summary

A hosted customer paid via Stripe. The gateway fee was confirmed on the invoice and then
disappeared. The audit traced it to a lost update: a concurrent request saved a stale copy
of the invoice over the confirmed state.

That diagnosis is correct, but the fix is not to make the write safe. It is to stop making
the write at all.

**The pending gateway fee is a scratch value that gets persisted into a shared,
customer-visible document, and then has to be un-persisted when the payment does not
happen.** Remove the persistence and the entire class of bug goes with it - along with two
cache locks, a scheduled cleanup job, four cleanup call sites on GET paths, and the
add/remove churn in the company ledger.

The resolution is one sentence:

> **Calculate the fee, charge it, and only write it to the invoice when the payment is
> confirmed.**

---

## 2. Evidence

`tests/Feature/GatewayFeeConcurrencyTest.php` describes the behaviour the fee handling must
have. Run against the current codebase, six cases pass and two fail.

Run with (see §11 - the committed sqlite config cannot migrate):

```bash
DB_CONNECTION=db-ninja-01 DB_DATABASE=ninja vendor/bin/phpunit \
  --filter <name> tests/Feature/GatewayFeeConcurrencyTest.php
```

### 2.1 The incident reproduces deterministically

`testConfirmedGatewayFeeSurvivesStaleCleanup` - **FAILS**

```
the confirmed gateway fee was removed by a stale cleanup
Failed asserting that actual size 0 matches expected size 1.
```

One process, no sleeps, no threads. Load the invoice while a fee is pending, confirm the
fee, then run `removeUnpaidGatewayFees()` on the copy loaded earlier. The confirmed type `4`
line is gone and the invoice drops from 105.00 back to 100.00.

This is the acceptance criterion for any fix.

### 2.2 A second, independent bug

`testLedgerAccountsForAFeeAddedByTheReconstructPath` - **FAILS**

```
the invoice amount moved but the company ledger did not
Failed asserting that 0.0 matches expected 5.0.
```

`AddGatewayFee` posts a ledger adjustment when the fee goes on. The reconstruct branch of
`confirmGatewayFee()` (`app/PaymentDrivers/BaseDriver.php:437-478`) raises the invoice
amount but posts nothing. Every time cleanup won the race in production, the ledger silently
lost the fee.

Because the resolution makes reconstruct the *only* path, this must be fixed as part of it.

### 2.3 The resolution's premise is verified

`testUnsavedQuoteMatchesThePersistedFeeDelta` - **PASSES**, four shapes

Computes the fee both ways - the proposed unsaved `getTempEntity()` calculation, and
today's persist-then-diff-the-balance - and asserts they agree, for a flat fee untaxed, a
flat fee at 10%, a flat fee at 10% on an inclusive-tax invoice, and an odd 3.37 fee at
7.25%. All four match to the cent, and the invoice row is asserted untouched by the quote.

"Compute the fee without writing it to the invoice" is not a hypothesis.

### 2.4 Two behaviours that must be preserved

`testDoubleConfirmationDoesNotDuplicateTheFee` - PASSES
`testConfirmationReconstructsTheFeeWhenThePendingLineIsGone` - PASSES

Both describe async behaviour the resolution must not regress. See §6.

---

## 3. Root cause

Three facts, read from the code:

1. **The invoice is not what gets charged.** Every driver charges
   `$payment_hash->data->amount_with_fee` - Stripe, Mollie, GoCardless, Razorpay, BTCPay,
   Rotessa, Payware, roughly twenty call sites. The webhook path
   (`app/PaymentDrivers/Stripe/Jobs/PaymentIntentWebhook.php:319`) reads it off the hash and
   never consults the invoice.

2. **The pending line item exists only to derive a number.**
   `app/Services/ClientPortal/InstantPayment.php:239`:

   ```php
   $fee_totals = round(($first_invoice->balance - $starting_invoice_amount), $precision);
   ```

   The line is added so the tax engine can price it, and the answer is obtained by diffing
   the balance. `LivewireInstantPayment.php:215` and `AutoBillInvoice.php:139-146` do the
   same.

3. **Nothing downstream reads the invoice for the fee.**
   `InstantPayment.php:290-307` builds `$totals` and `$data` entirely from `$fee_totals`.
   The Livewire summary takes `gateway_fee` from a context array
   (`app/Livewire/Flow2/InvoiceSummary.php:73`).

So the persisted pending fee is a temporary variable that happens to live in a document the
customer can view, PDF and e-invoice, and that a dozen code paths feel entitled to clean up.
Every removal path - portal GET, invitation GET, payment initialisation, payment failure,
mark-paid, cancellation, the hourly job - exists to undo it.

The audit's seven invariants, the cache locks, and both earlier drafts are all machinery for
safely undoing a write that should never have happened.

---

## 4. Resolution

**At initialisation** - quote, do not mutate. Build the fee line on a scratch copy read from
the committed row, run `calc()->getTempEntity()` (which does not save), and diff `amount`.
Store the net and gross on the `PaymentHash`, which already carries `fee_total` and
`amount_with_fee`. The invoice row is not touched.

**At confirmation** - the single write. Append the confirmed type `4` line, recalculate,
persist, post the ledger adjustment.

**On failure or abandonment** - nothing. There is nothing to undo.

This is not new code so much as a promotion of existing code. `confirmGatewayFee()` already
contains a complete path that builds the confirmed line from `PaymentHash::fee_total` when
no pending line exists (`BaseDriver.php:437-478`). It runs in production today, every time
cleanup won the race. The resolution makes it the only path and fixes its ledger gap.

---

## 5. Changes

Five, in project style: `AbstractService` subclasses with `run()`, chained off
`InvoiceService`, `nlog()` for logging.

### 5.1 `AddGatewayFee` -> `CalculateGatewayFee`

`app/Services/Invoice/AddGatewayFee.php` (132 lines) is replaced by a service that computes
and returns, and writes nothing.

```php
class CalculateGatewayFee extends AbstractService
{
    public function __construct(
        private CompanyGateway $company_gateway,
        private int $gateway_type_id,
        private Invoice $invoice,
        private float $amount
    ) {}

    /** @return array{net: float, gross: float} */
    public function run(): array
    {
        $precision = $this->invoice->client->currency()->precision;

        $net = round(
            $this->company_gateway->calcGatewayFee($this->amount, $this->gateway_type_id, $this->invoice->uses_inclusive_taxes),
            $precision
        );

        if (! $net || $net == 0 || ($net > 0 && $net < 0.01)) {
            return ['net' => 0.0, 'gross' => 0.0];
        }

        /** A scratch copy of the committed row. Never saved. */
        $scratch  = Invoice::withTrashed()->find($this->invoice->id);
        $starting = (float) $scratch->amount;

        $line_items   = $scratch->line_items;
        $line_items[] = self::line($this->company_gateway, $this->gateway_type_id, $net, 'quote', $scratch);
        $scratch->line_items = $line_items;

        return [
            'net'   => $net,
            'gross' => round($scratch->calc()->getTempEntity()->amount - $starting, $precision),
        ];
    }

    /** Shared with ConfirmGatewayFee so the quote and the confirmed line are built identically. */
    public static function line(CompanyGateway $company_gateway, int $gateway_type_id, float $net, string $hash, Invoice $invoice): InvoiceItem
    {
        // ... ported from AddGatewayFee::processGatewayFee()/processGatewayDiscount()
        // type_id '4', unit_code = $hash, fee tax rates from fees_and_limits
    }
}
```

A fresh read rather than `replicate()`: replicated models have no `id`, and
`InvoiceSum::calculatePartial()` branches on `isset($invoice->id)`.

`CalculateGatewayFee::line()` is shared with confirmation, so the quoted number and the
eventually-persisted line are produced by the same code. The quote is exact by construction,
not by coincidence.

### 5.2 The three initialisation sites

`InstantPayment.php:220-239`, `LivewireInstantPayment.php:172-215`, `AutoBillInvoice.php:139-146`
all collapse to the same shape:

```php
$fee_totals = 0;
$fee_net    = 0;

if ($gateway) {
    $fee = (new CalculateGatewayFee($gateway, $payment_method_id, $first_invoice, $invoice_totals))->run();

    $fee_totals = $fee['gross'];
    $fee_net    = $fee['net'];
}
```

`$hash_data['fee_net'] = $fee_net;` is added alongside the existing `fee_total`. It is the
*input* to the calculation, not a new derivation, and it removes the lossy de-taxing at
`BaseDriver.php:437-441` (which divides `fee_total` by the invoice-level rates even though
the line carries its own `fee_tax_rate1..3` with `tax_id = OVERRIDE_TAX`).

`$starting_invoice_amount` becomes unused. `AutoBillInvoice` keeps its
`if ($fee > $amount) { $fee = 0; }` guard.

### 5.3 `ConfirmGatewayFee`

`app/Services/Invoice/ConfirmGatewayFee.php`. `BaseDriver::confirmGatewayFee()` (96 lines)
becomes a delegator, so all eight driver call sites are untouched:

```php
public function confirmGatewayFee($data = []): void
{
    if (! $this->payment_hash) {
        return;
    }

    (new ConfirmGatewayFee($this->payment_hash, $this->company_gateway, $data))->run();
}
```

The service:

```php
public function run(): ?Invoice
{
    if (! $this->payment_hash->fee_total || $this->payment_hash->fee_total == 0
        || ! $this->payment_hash->fee_invoice_id) {
        return null;
    }

    for ($i = 1; $i <= self::MAX_ATTEMPTS; $i++) {

        $invoice = Invoice::withTrashed()->find($this->payment_hash->fee_invoice_id);

        if (! $invoice) {
            return null;
        }

        /** Closed invoice: log for the operator, but proceed. See "Why confirmation does not
            adjudicate invoice state" below. */
        if ($invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED], true)) {
            nlog("gateway fee confirming onto a closed invoice {$invoice->id} status {$invoice->status_id}");
        }

        /** LOAD-BEARING. Several drivers confirm twice, and webhooks are redelivered. See §6. */
        if (collect($invoice->line_items)->contains('unit_code', $this->payment_hash->hash)) {
            return $invoice;
        }

        /** Invoice::$casts casts updated_at to 'timestamp' - integer seconds. Read it raw. */
        $observed_updated_at = $invoice->getRawOriginal('updated_at');
        $starting_amount     = (float) $invoice->amount;

        $line_items   = $invoice->line_items;
        $line_items[] = CalculateGatewayFee::line(
            $this->company_gateway,
            $this->gatewayTypeId(),
            $this->netFee($invoice),
            $this->payment_hash->hash,
            $invoice
        );

        $invoice->line_items = array_values($line_items);
        $projected           = $invoice->calc()->getTempEntity();

        $claimed = Invoice::withTrashed()
            ->where('id', $invoice->id)
            ->where('updated_at', $observed_updated_at)
            ->update([
                'line_items'  => json_encode($projected->line_items),
                'amount'      => $projected->amount,
                'balance'     => $projected->balance,
                'total_taxes' => $projected->total_taxes,
                'updated_at'  => now()->format('Y-m-d H:i:s.u'),
            ]);

        if ($claimed !== 1) {
            continue;   // someone else wrote; re-read, and the idempotency check runs again
        }

        $adjustment = round((float) $projected->amount - $starting_amount, $precision);

        if ($adjustment != 0) {
            $invoice->ledger()->updateInvoiceBalance($adjustment, 'Adjustment for adding gateway fee');
            $invoice->client->service()->updateBalance($adjustment);
        }

        $invoice->service()->deleteEInvoice();

        return Invoice::withTrashed()->find($invoice->id);
    }

    return $this->exhausted();
}

private function netFee(Invoice $invoice): float
{
    /** Hashes created before this change do not carry fee_net. */
    return (float) ($this->payment_hash->data->fee_net ?? $this->legacyDeTax($invoice));
}
```

**Why a lost claim retries rather than re-evaluating whether to proceed.** The guard and the
decision answer different questions. The guard asks *"is the state I computed from still
current?"*. The decision - add the fee or not - depends on two facts that do not live on the
invoice: whether a fee was charged (`fee_total != 0` on the hash) and whether it is already
recorded (a line carrying this `unit_code`). Neither can be changed by another writer
touching the invoice. So a concurrent write changes what the fee is added *to*, never
whether it is added, and `continue` re-reads and recomputes against the new state.

**Why confirmation does not adjudicate invoice state.** Confirmation exists *because* a
payment is being created - they are a matched pair. `createPayment()` confirms the fee and
then creates a payment for `amount_with_fee`. Recording the payment at 105 while leaving the
invoice at 100 manufactures a 5.00 discrepancy that must land somewhere (unapplied payment,
or a client credit). On a cancelled, reversed or deleted invoice that is a *second*
inconsistency stacked on whatever closed the invoice. Splitting the pair never improves
matters.

The same test disposes of the ledger objection: `afterCommit()` posting an adjustment
against a deleted invoice is no worse than the payment application that follows it. If it is
wrong to adjust for the fee, it is wrong to create the payment - and that is not
confirmation's decision.

**The correct level for that decision is upstream, and it already exists there.**
`Stripe/Jobs/PaymentIntentWebhook.php:194` does `if ($invoice->is_deleted) { return; }`,
aborting the *whole payment*, not just the fee. So confirmation's contract is unconditional:
if a fee was charged and is not recorded, record it. It logs a warning on a closed invoice
for visibility and proceeds.

**Separate finding, not folded in.** The "do not create a payment for a closed invoice"
guard is inconsistent across drivers: Stripe checks `is_deleted` per branch, most drivers
check nothing, and nothing anywhere checks `STATUS_CANCELLED` or `STATUS_REVERSED`. That is
pre-existing, sits entirely upstream of the fee, and belongs in its own change. See §12.

**Guard breadth.** `updated_at` is complete but noisy - `last_viewed` on a portal view, a
reminder send, or an `auto_bill_tries` increment all bump it without touching the write set,
costing a false retry. Narrowing to `(amount, balance, total_taxes)` would be quieter but
incomplete: a `line_items` edit that leaves the totals unchanged would slip past it. Keep
`updated_at`. A false retry costs one `SELECT` and reaches the same conclusion, and the
tunable if exhaustion is ever observed is `MAX_ATTEMPTS`, not the guard.

`$this->company_gateway` is populated on the driver in the webhook path -
`PaymentIntentWebhook.php:310` builds it via `$company_gateway->driver($client)` - so the
fee-tax lookup needs no extra plumbing.

**On exhaustion, log and alert; never throw.** `confirmGatewayFee()` runs *before* the
payment record is created (`BaseDriver.php:306-308`), so throwing would mean the customer
paid and no `Payment` exists. A missing fee line leaves a recoverable overpayment that §10
alerts on. Losing the payment record does not. Three attempts against a rare confirm/confirm
race means exhaustion should not occur in practice.

### 5.4 The ledger fix

The `updateInvoiceBalance()` call in 5.3 is the fix for §2.2. It is one adjustment where the
old flow posted two (`+fee` at initialisation, `-fee` at cleanup) or, on the reconstruct
path, none.

**Visible change:** a successful payment produces one ledger adjustment instead of two; an
abandoned payment produces none instead of a matched pair. Net position is unchanged in both
cases; the row count is not.

The gateway-*discount* sign asymmetry flagged during review - `AddGatewayFee:121-127` posts
`$adjustment * -1` to the ledger while posting `$adjustment` to the client - disappears with
the file rather than needing a decision.

### 5.5 The one guarded write

Confirmation is now the only writer of fee lines, but two confirmations can still race:
webhook redelivery, or two genuine payments against one invoice. The conditional `update()`
on `updated_at` in 5.3, with re-read and re-check, handles it. Ten lines, mirroring
`MarkSent::run():29-38`, which already uses this idiom in this codebase.

This is the only concurrency machinery that survives. It is *within* one operation, not
between two opposing ones.

---

## 6. Async and webhook correctness

This is where the resolution is most exposed, because confirmation stops being a promotion
of something already present and becomes the thing that creates the fee.

### 6.1 Confirmation already runs twice

`MolliePaymentDriver.php:239-241` calls `confirmGatewayFee($data)` and then
`createPayment(...)`, which calls it again at `BaseDriver.php:307`. Same pattern in
`BraintreePaymentDriver.php:257`, `GoCardlessPaymentDriver.php:184`,
`CheckoutComPaymentDriver.php:520`, `CheckoutCom/CreditCard.php:280`.

Today this is harmless: the second call finds the `unit_code` present and re-toggles.
Under the resolution, without an idempotency check it appends a second surcharge.

### 6.2 Webhook redelivery reaches it too

In `createPayment()`, `confirmGatewayFee()` is at `:306-308`; the duplicate
`transaction_reference` guard is at `:310-320`, *after* it. A redelivered Stripe webhook
confirms again before dedupe.

`PaymentIntentProcessingWebhook` also creates a `PENDING` payment that
`PaymentIntentWebhook` later completes - two confirmations for one payment hash.

**Therefore the `unit_code` check in 5.3 is load-bearing, not defensive.** It is the first
thing the loop does, it re-runs after a lost claim, and
`testDoubleConfirmationDoesNotDuplicateTheFee` guards it.

Moving `confirmGatewayFee()` below the dedupe guard would be strictly better, but it changes
shared driver ordering used by every gateway. Out of scope; noted in §12.

### 6.3 Ordering against payment application

The fee must be on the invoice before the payment amount is applied, or the payment exceeds
the balance. `createPayment()` confirms first and applies after. That ordering is unchanged.

### 6.4 Long-delayed callbacks

A customer initiates, abandons the browser, and the webhook lands days later. Today the
pending line has long since been cleaned up, so confirmation reconstructs. Under the
resolution the invoice was never touched, so confirmation constructs. Identical outcome,
one fewer state.

### 6.5 Pending payments that later fail

A `PENDING` payment confirms the fee. If it subsequently fails, the confirmed line stays -
`unWindGatewayFees()` only ever removed type `3`. Behaviour is unchanged, and it is not
addressed here.

### 6.6 Multiple payment hashes per invoice

Every attempt creates a `PaymentHash`, and nothing prunes them - there is no cleanup job for
`payment_hashes` anywhere in the codebase. Multiple hashes per invoice is the steady state
today, not an edge case.

**The invoice never has to choose one.** The link runs hash -> invoice via `fee_invoice_id`,
never the reverse, and the gateway identifies the attempt on the way back:

```php
// app/PaymentDrivers/Stripe/Jobs/PaymentIntentWebhook.php:159-165
$hash         = $pi['metadata']['payment_hash'] ?? $charge['metadata']['payment_hash'] ?? false;
$payment_hash = PaymentHash::where('hash', $hash)->first();
```

Under the old design the invoice *did* have to remember which attempt was current - it held
that in `unit_code` on the pending line, and `addGatewayFee()` deleted prior type `3` lines
to keep only the latest. That "which attempt is current" state is precisely what the
incident clobbered. The resolution removes the ambiguity rather than arbitrating it.

| Situation | Old | New |
|---|---|---|
| 3 page loads, 1 payment | 3 add/remove cycles, each racing cleanup | 2 inert hash rows, 1 confirmed line |
| 2 successful payments | the 2nd initialisation deletes the 1st's pending line | 2 fee lines, 2 ledger rows, distinct `unit_code`s - both payments incurred a fee |
| Card attempt, then bank transfer at a different fee | invoice holds whichever was added last | each hash carries its own `fee_total` |

Unconfirmed hashes are simply never confirmed. Nothing has to find them and nothing has to
clean up after them.

**The invariant this gives the reconciliation alert (§10):**

> For every `PaymentHash` with `fee_total != 0` and a payment in `COMPLETED`/`PENDING`, the
> invoice at `fee_invoice_id` contains exactly one line whose `unit_code` is that hash.
> Hashes without a payment have no line.

**One pre-existing heuristic this exposes.** `InstantPayment.php:270` and
`LivewireInstantPayment.php:243` inherit `billing_context` from
"the most recent hash for this invoice with `payment_id IS NULL`". That is the only
remaining invoice -> hash lookup, and it selects by recency rather than identity. Because
hashes are never pruned, the pool it chooses from grows without bound. Pre-existing, unrelated
to fees, and out of scope here - but it is the same category of mistake as the two-second
`$raced_payment_hash` lookup being deleted in §7, and should be fixed on its own.

### 6.7 Is the payment hash universally resolvable?

The resolution depends on confirmation knowing which attempt succeeded. Audited across all
27 drivers and roughly 70 `createPayment()` call sites: **yes**, by three mechanisms, all
resolved before `createPayment()` runs.

**1. Gateway pass-through metadata** - async webhooks. Each gateway has a native reference
field carrying the hash out and back:

| Gateway | Field | Resolution site |
|---|---|---|
| Stripe | `metadata.payment_hash` | `Stripe/Jobs/PaymentIntentWebhook.php:160-166` |
| Stripe (processing) | `metadata.payment_hash` | `Stripe/Jobs/PaymentIntentProcessingWebhook.php:155-161` |
| Mollie | `payment.metadata.hash` | `MolliePaymentDriver.php:351,384` |
| Checkout.com | `metadata.udf2` | `CheckoutCom/CheckoutWebhook.php:102` |
| PayPal | `purchase_units[0].custom_id` | `PayPal/PayPalWebhook.php:200` |
| PayFast | `m_payment_id` | `PayFast/PaymentCompletedWebhook.php:81` |
| Square | `getReferenceId()` | `Square/SquareWebhook.php:140-142` |
| BTCPay | `metadata.InvoiceNinjaPaymentHash` | `BTCPayPaymentDriver.php:143` |
| ChipInAsia | `$reference` | `ChipInAsiaPaymentDriver.php:260` |

**2. Browser round-trip** - `$request->payment_hash`, a required request field on redirect
return: Razorpay (`Razorpay/Hosted.php:85`), Blockonomics, Rotessa, CBAPowerBoard,
GoCardless SEPA. 27 sites.

**3. Backwards from `payment_id`** - `GoCardless/Jobs/GoCardlessWebhook.php:134`,
`Rotessa/Jobs/TransactionReport.php:121`, `BlockonomicsPaymentDriver.php:138`. These handle
*subsequent* events on a payment that already exists, never creation. They work because
`createPayment()` sets `PaymentHash::payment_id` at `BaseDriver.php:347`, so confirmation
has already run by then.

Hard check: **no file calls `createPayment()` without referencing `payment_hash`.**

**The resolution adds no new dependency.** `confirmGatewayFee()` already reads
`$this->payment_hash->fee_total`, so an unresolvable hash means no confirmation today
either - the pending line is then cleaned up and the fee vanishes. Same end state, minus the
ledger churn. Upstream of that, an unresolvable hash means no payment is created at all,
because the driver needs it to know which invoices to attach and what to apply. The fee is
never the first thing lost, and that failure mode is pre-existing.

There is therefore no gateway for which this design is worse than the current one.

**One dormant flow to note.** `GoCardlessPaymentDriver.php:299-330` contains a commented-out
`billing_request` webhook handler carrying the author's note
`"couldn't find a hash, need to abort"`. It never ran - GoCardless payments are created in
the redirect flow where `$request->payment_hash` is present. If it is ever enabled,
`data->billing_request` is the intended join key and must be written at initialisation.

---

## 7. Deletions

| Target | Note |
|---|---|
| `app/Services/Invoice/AddGatewayFee.php` | replaced by `CalculateGatewayFee` |
| `InvoiceService::removeUnpaidGatewayFees()` | after the drain (§8) |
| `InvoiceService::addGatewayFee()` | callers use `CalculateGatewayFee` |
| `BaseDriver::unWindGatewayFees()` body | 11 driver call sites become no-ops, then get removed |
| `Cache::lock` in `InstantPayment.php:222` and `LivewireInstantPayment.php:174` | deleted, not replaced |
| `block(0.75)` + `$raced_payment_hash` + the 2-second lookup | `LivewireInstantPayment.php:185-205, 250-257` |
| `removeUnpaidGatewayFees()` calls in `InvoiceService::markPaid():47`, `handleCancellation():229`, `reverseCancellation():257` | nothing pending can exist |
| GET-path cleanup: `InvoiceController.php:73,227`, `InvitationController.php:358`, `InvoicePay.php:315` | the reason for the incident |
| `toggleFeesPaid()` type-3 branch, `MarkInvoiceDeleted.php:238` filter | after the drain |
| `CleanStaleInvoiceOrder` fee branches | after the drain |

The duplicate-initialisation problem the cache lock was written for evaporates: with no
invoice mutation, two concurrent initialisations produce two harmless `PaymentHash` rows.
No `attempt_key`, no idempotency nonce, no replacement lock.

---

## 8. Draining in-flight invoices

Invoices in production right now carry pending type `3` lines. They need collecting once.

**No new code.** Keep `removeUnpaidGatewayFees()` alive for one release, called *only* from
`CleanStaleInvoiceOrder`, narrowed so it promotes-or-leaves and never deletes a line whose
hash carries a `COMPLETED`/`PENDING` payment - the rule that job already implements at
`:118-125`. Its `updated_at` window is already one to two hours old, so it cannot race a
live confirmation.

Once the backlog clears (query: invoices with a type `3` line), delete the method, the job's
fee branches, and the two dead filters from §7.

---

## 9. Tests

### Written and passing/failing as described (§2)

`tests/Feature/GatewayFeeConcurrencyTest.php`, 8 cases. The two failures become passes;
the six passes must stay passing. They describe behaviour, not structure, so they remain
valid regardless of implementation detail.

### To add with the implementation

1. Two concurrent confirmations for the same hash - one fee line, one ledger row.
2. Two concurrent confirmations for *different* hashes - two fee lines, two ledger rows.
3. Webhook redelivery through the full `createPayment()` path (driver double) - one payment,
   one fee line.
4. `fee_net` round-trip: quoted net equals the persisted line cost, across the four shapes
   in `feeShapes()`.
5. Legacy hash with no `fee_net` falls back to de-taxing and still confirms.
6. Gateway *discount* (negative fee) end to end.
7. The quote does not mutate the invoice, asserted on `updated_at` as well as `amount`.
8. Confirmation proceeds and warns on `is_deleted`, `STATUS_CANCELLED` and `STATUS_REVERSED`;
   the fee lands so the payment and invoice stay reconciled.
9. Confirmation proceeds on an archived invoice (`deleted_at` set, `is_deleted` false) - in
   this codebase `deleted_at` alone means archived, which is financially live.
10. BTCPay settlement creates a payment equal to `amount_with_fee`, not the pre-confirmation
    invoice amount.

### Existing tests that encode the old design and must change

- `tests/Feature/InvoiceAmountPaymentTest.php:288-291` asserts a pending type `3` appears on
  the invoice after `addGatewayFee()`. That assertion is the old design. The rest of the
  test - that the confirmed fee lands and the balances settle - stays.
- `tests/Feature/InvoiceAmountPaymentTest.php:124` `testMarkPaidRemovesUnpaidGatewayFees`
  injects a type `3` line by hand and asserts `markPaid()` strips it. Delete.
- `tests/Feature/ClientPortal/InvoicesTest.php` asserts viewing an invoice removes a stale
  type `3` fee. Delete after the drain.

---

## 10. Rollout

| Phase | Work | Reversible by |
|---|---|---|
| 1 | `CalculateGatewayFee` + `ConfirmGatewayFee`, not yet called | dead code |
| 2 | Repoint the three initialisation sites and `BaseDriver::confirmGatewayFee()`. Delete both cache locks and the GET-path cleanup. Ledger fix lands here. | straight revert |
| 3 | Observability (below). Watch for one release. | - |
| 4 | Drain complete: delete `removeUnpaidGatewayFees()`, `unWindGatewayFees()`, the job's fee branches, the dead filters, the three obsolete tests. | - |

Phase 2 is the fix. Nothing in it requires a migration, so rollback is a revert with no data
to undo.

**Observability from phase 2.** Log invoice id, payment hash id, quoted net/gross, and the
confirmation outcome. Alert on:

- a `COMPLETED`/`PENDING` payment with non-zero `fee_total` whose invoice has no line
  carrying that hash - the direct detector for the original incident, and for a confirmation
  that exhausted its attempts;
- `ConfirmGatewayFee` attempts exhausted;
- during the drain only: invoices still carrying a type `3` line, trending to zero.

`invoices.gateway_fee` stays **unused**. An earlier revision of this plan had confirmation
write the net confirmed fee there, but §6.6 rules it out: with two confirmed fees on one
invoice the second write overwrites the first, so the column would have to hold a sum that
merely duplicates what `line_items` already contains - and it still could not serve the
reconciliation alert, which matches on `unit_code`. The alert is expressed against
`payment_hashes` joined to `payments` instead. No column, no migration, nothing to keep in
step.

---

## 11. Running the tests

`phpunit.xml` declares `DB_CONNECTION=sqlite` / `:memory:`, but
`database/migrations/2020_05_13_035355_add_google_refresh_token_to_users_table.php:30`
issues a raw `alter table users modify column`, which SQLite rejects - so the committed
default cannot build a schema. CI overrides to MySQL (`.github/workflows/phpunit.yml:27-32`).

Locally:

```bash
DB_CONNECTION=db-ninja-01 DB_DATABASE=ninja vendor/bin/phpunit tests/Feature/GatewayFeeConcurrencyTest.php
```

`makeTestData()` only migrates when `countries` is empty, and `DatabaseTransactions` rolls
back, so a dev database is not modified. Verified: zero rows left in `payment_hashes`,
`company_gateways`, `invoices` or `company_ledgers` after the runs in §2.

Worth fixing the sqlite config separately so the committed default works.

---

## 12. Not in scope

- Moving `confirmGatewayFee()` below the duplicate-`transaction_reference` guard in
  `createPayment()` (§6.2). Correct, but it changes ordering for every gateway.
- `ClientService::updateBalance()` and siblings (`app/Services/Client/ClientService.php:100-135`)
  still use `lockForUpdate()` for a read-modify-write that `increment()` would express
  atomically.
- `InvoiceSum::getInvoice()` remains a getter that saves. This removes the fee paths'
  dependence on that, but does not fix the misnomer.
- A normalized `gateway_fees` table. Unnecessary once the invoice stops carrying pending
  payment state.
- A confirmed fee surviving a later payment failure (§6.5).

---

## 13. To verify before phase 2

- ~~`Blockonomics.php:221` and `BTCPayPaymentDriver.php:195` read `$invoice->amount`~~ -
  **resolved by the sweep in §13.1.** Blockonomics is unaffected; BTCPay needs the one-line
  fix now in §A.3.

### 13.1 Sweep: who reads the invoice row on a payment path?

The audit criterion is **does this code read the invoice row, or the data recorded on the
`PaymentHash`?** Hash-driven code is unaffected by this change, because the hash data is
built identically before and after. Row-driven code must be checked.

Exhaustive grep for `invoice->amount` / `invoice->balance` across `app/PaymentDrivers/`,
`app/Services/ClientPortal/` and `app/Livewire/Flow2/`:

| Site | Reads | Verdict |
|---|---|---|
| `BTCPayPaymentDriver.php:195` | the invoice **row**, to set the payment amount | **needs the fix** (§A.3) |
| `Blockonomics/Blockonomics.php:221` | a `stdClass` from `$payment_hash->data->invoices`, **not** an Invoice model - the method's own docblock says "Only modifies the amounts in the PaymentHash, never the actual invoices" | unaffected |
| `BaseDriver.php:439`, `:478` | inside `confirmGatewayFee()` | replaced by `ConfirmGatewayFee` |
| `InstantPayment.php:129`, `:134`, `:183`; `LivewireInstantPayment.php:133` | over/under-payment validation at `:129-198` | unaffected - see below |
| `InstantPayment.php:216`, `:241`; `LivewireInstantPayment.php:166`, `:215` | the `$starting_invoice_amount` diff | the lines being replaced |

**Why the Blockonomics allocations cannot contain the fee.**
`$payable_invoice_collection` is built at `InstantPayment.php:173-198`, *before* `$fee_totals`
is computed at `:241`. So `$hash_data['invoices'][].amount` has never included the fee, in
either design; the fee lives only in `fee_total` and `amount_with_fee`. Blockonomics compares
the received fiat against `amount_with_fee` (`:147`) and distributes it across those fee-free
allocations. Identical before and after.

**A small win in the validation reads.** Today `InstantPayment.php:129-134` reads
`$invoice->balance` *after* `removeUnpaidGatewayFees()` at `:88-91`, so correct over/under-payment
validation depends on that cleanup having actually run. If it raced, the validation would see a
balance inflated by a stale pending fee and could reject a correct payment amount. After this
change the balance at that point is fee-free by construction.
- ~~A draft invoice quotes a fee of 0 under today's balance diff but non-zero under an
  `amount` diff~~ - **resolved. Not reachable.** `markSent()` precedes every fee entry point:
  `InstantPayment.php:88-91`, `LivewireInstantPayment.php:102-105`, and
  `AutoBillInvoice.php:69` (seventy lines before its `addGatewayFee()` at `:139`).
  `MarkSent::run()` claims the transition with
  `where('status_id', STATUS_DRAFT)->update(...)`, so afterwards the invoice is `SENT` or the
  claim returned 0 because it already was.
- ~~Confirm no fourth gateway-fee entry point in the subscription or pre-payment flows~~ -
  **resolved.** `addGatewayFee()` has exactly three callers app-wide:
  `InstantPayment.php:230`, `LivewireInstantPayment.php:179`, `AutoBillInvoice.php:139`.
  Subscriptions and payment links route through `InstantPayment` rather than carrying their
  own fee path.

**§13 is now empty. All four items are closed.**

---

## Appendix A: Exact change manifest

Line references verified against the working tree on 2026-08-22. Phase numbers refer to §10.

### A.1 New files (2)

| File | Contents |
|---|---|
| `app/Services/Invoice/CalculateGatewayFee.php` | `AbstractService`. `run(): array{net, gross}` - quotes the fee on a scratch copy read from the committed row, via `calc()->getTempEntity()`. Writes nothing. Also holds `public static line(...)`, the single fee-line builder shared with confirmation. |
| `app/Services/Invoice/ConfirmGatewayFee.php` | `AbstractService`. `run(): ?Invoice` - idempotency check on `unit_code`, append confirmed type `4` line, conditional `update()` guarded on `updated_at`, ledger adjustment, `deleteEInvoice()`. Logs and returns on exhaustion; never throws. |

### A.2 Deleted files (1)

| File | Lines | Replacement |
|---|---|---|
| `app/Services/Invoice/AddGatewayFee.php` | 136 | `CalculateGatewayFee` (quote) + `ConfirmGatewayFee` (write) |

### A.3 Phase 2 - the fix

**`app/Services/Invoice/InvoiceService.php`**

| Line | Change |
|---|---|
| `47` | Delete `$this->removeUnpaidGatewayFees();` from `markPaid()` |
| `119-126` | Delete `addGatewayFee()`. Callers construct `CalculateGatewayFee` directly - it is no longer an invoice mutation, so it does not belong on the invoice service facade |
| `229` | Delete `$this->removeUnpaidGatewayFees();` from `handleCancellation()` |
| `257` | Delete `$this->removeUnpaidGatewayFees();` from `reverseCancellation()` |
| `449-483` | Narrow `removeUnpaidGatewayFees()` for the drain (§8): promote-or-leave, never delete a line whose hash carries a `COMPLETED`/`PENDING` payment. Deleted entirely in phase 4 |

**`app/PaymentDrivers/BaseDriver.php`**

| Line | Change |
|---|---|
| `394-489` | Replace the 96-line body of `confirmGatewayFee()` with an 8-line delegator to `ConfirmGatewayFee`. Removes `DB::transaction`, `PaymentHash::...->lockForUpdate()`, the `load('fee_invoice')`, both fee branches, and the in-transaction `deleteEInvoice()`. **All 7 call sites unchanged.** |
| `501-506` | `unWindGatewayFees()` body becomes a no-op. **All 22 call sites unchanged**, then removed in phase 4 |

**`app/Services/ClientPortal/InstantPayment.php`**

| Line | Change |
|---|---|
| `88-91` | Drop `->removeUnpaidGatewayFees()` from the invoice `map()`; keep `markSent()` and `save()` |
| `216` | Delete `$starting_invoice_amount` |
| `220-233` | Delete the entire `Cache::lock` block - lock acquisition, the 409 `PaymentFailed`, the `try/finally`, and the `addGatewayFee(...)->save()`. Replace with the `CalculateGatewayFee` quote |
| `241` | `$fee_totals` comes from `$fee['gross']` instead of the balance diff |
| `~265` | Add `'fee_net' => $fee_net` to `$hash_data` |

**`app/Services/ClientPortal/LivewireInstantPayment.php`**

| Line | Change |
|---|---|
| `102-105` | Drop `->removeUnpaidGatewayFees()` from the `map()` |
| `166` | Delete `$starting_invoice_amount` |
| `170` | Delete `$raced_payment_hash = null;` |
| `172-205` | Delete the whole lock block: `Cache::lock`, `block(0.75)`, the 2-second `PaymentHash` lookup, the 409, both `finally` clauses, `$first_invoice->refresh()`. Replace with the quote |
| `213-215` | `$fee_totals` from `$fee['gross']`; delete the commented line at `213` |
| `~237` | Add `'fee_net' => $fee_net` to `$hash_data` |
| `248-257` | Delete the `isset($raced_payment_hash)` branch; always create the `PaymentHash` |

**`app/Services/Invoice/AutoBillInvoice.php`**

| Line | Change |
|---|---|
| `139-152` | Replace `addGatewayFee(...)->save()` and the balance-diff with the quote. Keep the `if ($fee > $amount) { $fee = 0; }` guard at `150-152` |
| `~171` | Add `fee_net` alongside `'fee_total' => $fee` |

**`app/PaymentDrivers/BTCPayPaymentDriver.php` - APPLIED, independent of this plan**

`:195` changed from `'amount' => $_invoice->amount` to
`'amount' => $this->payment_hash->amount_with_fee()`.

That line is evaluated *before* `createPayment()`, hence before confirmation. Today the
pending fee line makes `$_invoice->amount` come out right; under this plan the invoice is
still un-feed at that point and the payment would be created short by the fee.

**It is a latent bug today, not a regression this plan introduces.** Crypto settlement waits
on block confirmations - minutes to hours - so any portal view or scheduled cleanup in that
window already causes a short payment, intermittently. `BTCPay/BTCPay.php:187` charges the
gateway `$data['total']['amount_with_fee']`, and `BTCPayPaymentDriver.php:216` already uses
`$this->payment_hash->amount_with_fee()` for its own notification; only `:195` disagreed with
both. Fixed separately, ahead of phase 2.

**Client portal GET paths** - remove cleanup from four sites:

| File | Line |
|---|---|
| `app/Http/Controllers/ClientPortal/InvoiceController.php` | `73` (whole statement), `227` (chain link only) |
| `app/Http/Controllers/ClientPortal/InvitationController.php` | `358` (whole statement) |
| `app/Livewire/Flow2/InvoicePay.php` | `315` (chain link only) |

**`app/Jobs/Subscription/CleanStaleInvoiceOrder.php`**

| Line | Change |
|---|---|
| `88-131` | Collapse the two loops into one drain pass calling the narrowed `removeUnpaidGatewayFees()`. Keeps the promotion rule already at `118-125`. Removed in phase 4 |

### A.4 Phase 4 - after the drain

| File | Change |
|---|---|
| `app/Services/Invoice/InvoiceService.php:449-483` | Delete `removeUnpaidGatewayFees()` |
| `app/Services/Invoice/InvoiceService.php:374-405` | Delete the type `3` handling in `toggleFeesPaid()`; the method's remaining use is the promotion path, which `ConfirmGatewayFee` owns |
| `app/PaymentDrivers/BaseDriver.php:501-506` | Delete `unWindGatewayFees()` and its 22 call sites across Checkout (11), Mollie (3), Stripe/Base (3), Braintree, GoCardless, Square, and the Checkout webhook |
| `app/Services/Invoice/MarkInvoiceDeleted.php:238` | Delete the `type_id != '3'` filter |
| `app/Jobs/Subscription/CleanStaleInvoiceOrder.php` | Delete the fee branches |

### A.5 Tests

| File | Change | Phase |
|---|---|---|
| `tests/Feature/GatewayFeeConcurrencyTest.php` | Exists, 8 cases. `testConfirmedGatewayFeeSurvivesStaleCleanup` and `testLedgerAccountsForAFeeAddedByTheReconstructPath` must flip from fail to pass; the other six must stay passing | 2 |
| same | Add the 7 cases in §9 | 2 |
| `tests/Feature/InvoiceAmountPaymentTest.php:288-291` | Delete the four assertions that a pending type `3` line appears after `addGatewayFee()`. The rest of the test - confirmed fee lands, balances settle - stays | 2 |
| `tests/Feature/InvoiceAmountPaymentTest.php:124` | Delete `testMarkPaidRemovesUnpaidGatewayFees` - it hand-injects a type `3` line to assert `markPaid()` strips it | 4 |
| `tests/Feature/ClientPortal/InvoicesTest.php` | Delete the assertion that viewing an invoice removes a stale type `3` fee | 4 |

### A.6 Not touched

No migration. No schema change. `invoices.gateway_fee` stays unused (§6.6). `payment_hashes`
unchanged - no `attempt_key`, no unique index, no pruning. No changes to any of the 27
payment drivers beyond `BaseDriver`; all 7 `confirmGatewayFee()` and 22
`unWindGatewayFees()` call sites keep their current signatures through phase 2.
