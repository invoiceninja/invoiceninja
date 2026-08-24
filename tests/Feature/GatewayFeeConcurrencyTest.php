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

namespace Tests\Feature;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\CompanyGateway;
use App\Jobs\Util\SystemLogger;
use App\Models\CompanyLedger;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Bus;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\BaseDriver;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook;
use App\Models\ClientGatewayToken;
use App\Services\Invoice\AutoBillInvoice;
use App\Services\Invoice\ConfirmGatewayFee;
use App\Services\Invoice\ReverseGatewayFee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Gateway fees are quoted at payment initiation and written to the invoice only when the
 * payment is confirmed. These tests cover that contract.
 *
 * @see \App\Services\Invoice\CalculateGatewayFee
 * @see \App\Services\Invoice\ConfirmGatewayFee
 * @see docs/gateway-fee-resolution-plan.md
 */
class GatewayFeeConcurrencyTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /* ------------------------------------------------------------------ helpers */

    private function gateway(float $fee_amount = 5, float $fee_tax_rate1 = 0): CompanyGateway
    {
        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->accepted_credit_cards = 1;
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(json_encode(new \stdClass()));
        $cg->fees_and_limits = [
            GatewayType::CREDIT_CARD => [
                'min_limit' => -1,
                'max_limit' => -1,
                'fee_amount' => $fee_amount,
                'fee_percent' => 0,
                'fee_tax_name1' => $fee_tax_rate1 > 0 ? 'GST' : '',
                'fee_tax_rate1' => $fee_tax_rate1,
                'fee_tax_name2' => '',
                'fee_tax_rate2' => 0,
                'fee_tax_name3' => '',
                'fee_tax_rate3' => 0,
                'fee_cap' => 0,
                'adjust_fee_percent' => false,
                'is_enabled' => true,
            ],
        ];
        $cg->save();

        return $cg;
    }

    private function sentInvoice(float $cost = 100, bool $inclusive = false): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = $inclusive;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        return $invoice->service()->markSent()->save();
    }

    /**
     * Mirrors what the payment initialisation services now do: quote the fee, record it on
     * the hash, leave the invoice alone.
     */
    private function initiate(Invoice $invoice, CompanyGateway $cg, float $amount = 100, ?string $hash = null): PaymentHash
    {
        $quote = $invoice->service()->quoteGatewayFee($cg, GatewayType::CREDIT_CARD, $amount);

        return PaymentHash::create([
            'hash' => $hash ?? Str::random(32),
            'fee_total' => $quote['gross'],
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [[
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $amount,
                    'due_date' => '',
                    'invoice_number' => $invoice->number,
                    'additional_info' => '',
                ]],
                'credits' => 0,
                'fee_net' => $quote['net'],
                'amount_with_fee' => round($amount + $quote['gross'], 2),
            ],
        ]);
    }

    private function confirm(CompanyGateway $cg, PaymentHash $hash): void
    {
        (new BaseDriver($cg, $this->client))
            ->setPaymentHash($hash)
            ->confirmGatewayFee(['gateway_type_id' => GatewayType::CREDIT_CARD]);
    }

    /** The processing webhook: a PENDING payment, which confirms the fee. */
    private function pendingPayment(CompanyGateway $cg, PaymentHash $payment_hash): Payment
    {
        return (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::VISA,
                'transaction_reference' => 'txn_' . Str::random(12),
            ], Payment::STATUS_PENDING);
    }

    /** What every driver failure path does: unwind the payment, then mark it failed. */
    private function failPendingPayment(Payment $payment): void
    {
        $payment->service()->deletePayment();
        $payment->status_id = Payment::STATUS_FAILED;
        $payment->save();
    }

    private function feeLines(Invoice $invoice, string $hash): array
    {
        return collect($invoice->line_items)
                ->filter(fn ($item) => ($item->unit_code ?? '') === $hash)
                ->values()
                ->all();
    }

    /** @return \Illuminate\Support\Collection<int, CompanyLedger> */
    private function gatewayFeeLedgerRows(): \Illuminate\Support\Collection
    {
        return CompanyLedger::query()
                    ->where('client_id', $this->client->id)
                    ->where('notes', 'like', '%gateway fee%')
                    ->get();
    }

    private function ledgerTotal(Invoice $invoice): float
    {
        return (float) $invoice->company_ledger()->sum('adjustment');
    }

    /* ------------------------------------------------- the invoice is not touched */

    /** Quoting must not write to the invoice in any way. */
    public function testQuotingDoesNotTouchTheInvoice(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $before = Invoice::withTrashed()->find($invoice->id);
        $before_updated_at = $before->getRawOriginal('updated_at');
        $before_ledger = $this->ledgerTotal($invoice);

        $quote = $invoice->service()->quoteGatewayFee($cg, GatewayType::CREDIT_CARD, 100);

        $after = Invoice::withTrashed()->find($invoice->id);

        $this->assertEquals(5, $quote['gross']);
        $this->assertEquals(5, $quote['net']);
        $this->assertEquals(100, round($after->amount, 2));
        $this->assertEquals(100, round($after->balance, 2));
        $this->assertCount(1, (array) $after->line_items);
        $this->assertSame($before_updated_at, $after->getRawOriginal('updated_at'));
        $this->assertEquals($before_ledger, $this->ledgerTotal($after));
    }

    /** An abandoned attempt leaves nothing behind - there is nothing to clean up. */
    public function testAnAbandonedAttemptLeavesNoTraceOnTheInvoice(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $before_ledger = $this->ledgerTotal($invoice);

        $this->initiate($invoice, $cg);
        $this->initiate($invoice, $cg);
        $this->initiate($invoice, $cg);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, (array) $final->line_items);
        $this->assertEquals(100, round($final->amount, 2));
        $this->assertEquals(100, round($final->balance, 2));
        $this->assertEquals($before_ledger, $this->ledgerTotal($final));
    }

    /* --------------------------------------------------------- quote exactness */

    /**
     * The quoted gross is what the customer is charged. Confirmation must move the invoice
     * by exactly that amount, or the payment and the invoice disagree.
     *
     * @dataProvider feeShapes
     */
    public function testConfirmationMovesTheInvoiceByExactlyTheQuotedAmount(float $fee_amount, float $fee_tax_rate, bool $inclusive): void
    {
        $cg = $this->gateway($fee_amount, $fee_tax_rate);
        $invoice = $this->sentInvoice(100, $inclusive);

        $starting = (float) $invoice->amount;
        $payment_hash = $this->initiate($invoice, $cg);
        $quoted = (float) $payment_hash->fee_total;

        $this->assertGreaterThan(0, $quoted);

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertEquals(
            round($starting + $quoted, 2),
            round($final->amount, 2),
            'the invoice did not move by the amount the customer was charged'
        );
        $this->assertSame('4', (string) $this->feeLines($final, $payment_hash->hash)[0]->type_id);
    }

    public static function feeShapes(): array
    {
        return [
            'flat fee, no tax, exclusive' => [5.0, 0.0, false],
            'flat fee, taxed 10%, exclusive' => [5.0, 10.0, false],
            'flat fee, taxed 10%, inclusive' => [5.0, 10.0, true],
            'odd fee, taxed 7.25%, exclusive' => [3.37, 7.25, false],
        ];
    }

    /* --------------------------------------------------------------- idempotency */

    /**
     * Mollie, Braintree, GoCardless and CheckoutCom all confirm directly and again through
     * createPayment(). Stripe redelivers webhooks.
     */
    public function testDoubleConfirmationDoesNotDuplicateTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $this->confirm($cg, $payment_hash);
        $this->confirm($cg, $payment_hash);
        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'confirming repeatedly created duplicate surcharges');
        $this->assertEquals(105, round($final->amount, 2));
        $this->assertEquals(105, round($this->ledgerTotal($final), 2), 'the ledger was adjusted more than once');
    }

    /** A stale writer must lose its claim rather than overwrite the confirmed fee. */
    public function testAStaleWriterCannotOverwriteAConfirmedFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        /** A second confirmation holds a snapshot taken before the first one commits. */
        $stale = Invoice::withTrashed()->find($invoice->id);
        $stale_updated_at = $stale->getRawOriginal('updated_at');

        $this->confirm($cg, $payment_hash);

        $claimed = Invoice::withTrashed()
                    ->where('id', $invoice->id)
                    ->where('updated_at', $stale_updated_at)
                    ->update(['total_taxes' => 999]);

        $this->assertSame(0, $claimed, 'the updated_at guard did not reject a stale write');

        $final = Invoice::withTrashed()->find($invoice->id);
        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash));
        $this->assertEquals(105, round($final->amount, 2));
    }

    /**
     * Invoice::$casts casts updated_at to a unix timestamp, discarding the microseconds the
     * guard relies on. Reading it any way other than getRawOriginal() silently degrades the
     * guard to one second resolution.
     */
    public function testTheClaimGuardHasMicrosecondResolution(): void
    {
        $invoice = $this->sentInvoice(100);
        $observed = $invoice->getRawOriginal('updated_at');

        Invoice::withTrashed()->where('id', $invoice->id)->update([
            'updated_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        $claimed = Invoice::withTrashed()
                    ->where('id', $invoice->id)
                    ->where('updated_at', $observed)
                    ->update(['total_taxes' => 999]);

        $this->assertSame(0, $claimed, 'updated_at guard collapsed to second resolution');
    }

    /* ------------------------------------------------------- multiple attempts */

    /** Each attempt carries its own fee. Two successful payments produce two fees. */
    public function testTwoConfirmedAttemptsEachRecordTheirOwnFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $first = $this->initiate($invoice, $cg);
        $second = $this->initiate($invoice, $cg);

        $this->assertNotSame($first->hash, $second->hash);

        $this->confirm($cg, $first);
        $this->confirm($cg, $second);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $first->hash));
        $this->assertCount(1, $this->feeLines($final, $second->hash));
        $this->assertEquals(110, round($final->amount, 2));
        $this->assertEquals(110, round($this->ledgerTotal($final), 2));
    }

    /** An unconfirmed attempt alongside a confirmed one puts nothing on the invoice. */
    public function testAnUnconfirmedAttemptAlongsideAConfirmedOneIsIgnored(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $abandoned = $this->initiate($invoice, $cg);
        $paid = $this->initiate($invoice, $cg);

        $this->confirm($cg, $paid);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(0, $this->feeLines($final, $abandoned->hash));
        $this->assertCount(1, $this->feeLines($final, $paid->hash));
        $this->assertEquals(105, round($final->amount, 2));
    }

    /* ------------------------------------------------------------------ ledger */

    /** The ledger must always account for a change in the invoice amount. */
    public function testTheLedgerAccountsForTheConfirmedFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $before_amount = (float) Invoice::withTrashed()->find($invoice->id)->amount;
        $before_ledger = $this->ledgerTotal($invoice);
        $before_client_balance = (float) $this->client->fresh()->balance;

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertEquals(
            round((float) $final->amount - $before_amount, 2),
            round($this->ledgerTotal($final) - $before_ledger, 2),
            'the invoice amount moved but the company ledger did not'
        );

        $this->assertEquals(
            round($before_client_balance + 5, 2),
            round((float) $this->client->fresh()->balance, 2),
            'the client balance did not follow the fee'
        );
    }

    /** An abandoned attempt must not churn the ledger with a matched add/remove pair. */
    public function testAnAbandonedAttemptPostsNoLedgerRows(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $before = $this->ledgerTotal($invoice);

        $this->initiate($invoice, $cg);

        $this->assertEquals($before, $this->ledgerTotal($invoice->fresh()));
        $this->assertCount(0, CompanyLedger::query()
            ->where('client_id', $this->client->id)
            ->where('notes', 'like', '%gateway fee%')
            ->get());
    }

    /* -------------------------------------------------------------- edge cases */

    /** Hashes created before the fee became a quote carry no fee_net. */
    public function testConfirmationFallsBackWhenTheHashPredatesFeeNet(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $payment_hash = PaymentHash::create([
            'hash' => Str::random(32),
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 100, 'invoice_number' => $invoice->number]],
                'credits' => 0,
                'amount_with_fee' => 105,
            ],
        ]);

        $this->assertFalse(isset($payment_hash->data->fee_net));

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = $this->feeLines($final, $payment_hash->hash);

        $this->assertCount(1, $lines);
        $this->assertSame('4', (string) $lines[0]->type_id);
        $this->assertEquals(105, round($final->amount, 2));
    }

    /** A gateway discount is a negative fee and must survive the same path. */
    public function testANegativeGatewayFeeIsRecorded(): void
    {
        $cg = $this->gateway(-2.50);
        $invoice = $this->sentInvoice(100);

        $payment_hash = $this->initiate($invoice, $cg);

        $this->assertEquals(-2.50, round((float) $payment_hash->fee_total, 2));

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = $this->feeLines($final, $payment_hash->hash);

        $this->assertCount(1, $lines);
        $this->assertEquals(-2.50, round((float) $lines[0]->cost, 2));
        $this->assertEquals(97.50, round($final->amount, 2));
    }

    /**
     * A closed invoice still receives the fee. The payment about to be created is for the
     * fee inclusive amount; recording one without the other manufactures a discrepancy.
     */
    public function testAClosedInvoiceStillReceivesTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $invoice->service()->handleCancellation();

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'the fee was dropped, leaving the payment and invoice out of step');
        $this->assertSame(Invoice::STATUS_CANCELLED, $final->status_id);
    }

    /** A zero fee gateway must not create a line item. */
    public function testAZeroFeeCreatesNothing(): void
    {
        $cg = $this->gateway(0);
        $invoice = $this->sentInvoice(100);

        $quote = $invoice->service()->quoteGatewayFee($cg, GatewayType::CREDIT_CARD, 100);

        $this->assertEquals(0, $quote['net']);
        $this->assertEquals(0, $quote['gross']);

        $payment_hash = $this->initiate($invoice, $cg);
        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, (array) $final->line_items);
        $this->assertEquals(100, round($final->amount, 2));
    }

    /* --------------------------------------------------------------- end to end */

    /** Quote, charge, confirm, apply - the invoice settles and the books balance. */
    public function testAPaymentWithAFeeSettlesTheInvoice(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $this->assertEquals(105, round((float) $payment_hash->data->amount_with_fee, 2));

        $payment = (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::VISA,
                'transaction_reference' => 'txn_' . Str::random(12),
            ]);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertSame('4', (string) $this->feeLines($final, $payment_hash->hash)[0]->type_id);
        $this->assertEquals(105, round($final->amount, 2));
        $this->assertEquals(0, round($final->balance, 2));
        $this->assertEquals(105, round($final->paid_to_date, 2));
        $this->assertSame(Invoice::STATUS_PAID, $final->status_id);
        $this->assertEquals(105, round((float) $payment->fresh()->amount, 2));
    }

/* ------------------------------------------------------------------ autobill */

    /**
     * Auto billing quotes the fee the same way the portal does. The invoice must be
     * untouched, and the hash must carry the amount the customer will be charged.
     *
     * run() throws when the driver cannot reach the gateway - by then the fee has been
     * quoted and the hash written, which is the part this covers.
     */
    public function testAutoBillQuotesTheFeeWithoutTouchingTheInvoice(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $cg->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->token = 'pm_autobill_fee_test';
        $token->gateway_customer_reference = 'cus_autobill_fee_test';
        $token->meta = (object) ['state' => 'authorized', 'last4' => '4242'];
        $token->is_default = true;
        $token->save();

        $before = Invoice::withTrashed()->find($invoice->id);
        $before_line_items = json_encode($before->line_items);

        try {
            (new AutoBillInvoice($invoice, $this->company->db))->run();
        } catch (\Throwable $e) {
            // the charge cannot complete against a fake gateway
        }

        $after = Invoice::withTrashed()->find($invoice->id);

        $this->assertSame($before_line_items, json_encode($after->line_items), 'auto bill wrote a fee to the invoice');
        $this->assertEquals(100, round($after->amount, 2));

        $hash = PaymentHash::query()->where('fee_invoice_id', $invoice->id)->orderBy('id', 'desc')->first();

        $this->assertNotNull($hash, 'auto bill did not create a payment hash');
        $this->assertEquals(5, round((float) $hash->fee_total, 2), 'auto bill quoted the wrong fee');
        $this->assertEquals(5, round((float) $hash->data->fee_net, 2));

        /** Credits are applied before the fee is quoted, so assert the relationship, not a literal. */
        $billed = (float) $hash->data->invoices[0]->amount;

        $this->assertEquals(
            round($billed + 5, 2),
            round((float) $hash->data->amount_with_fee, 2),
            'the charged amount must be the billed amount plus the quoted fee'
        );
    }

    /** Auto billing keeps its guard: a fee larger than the amount being billed is dropped. */
    public function testAutoBillDropsAFeeLargerThanTheAmountBilled(): void
    {
        $cg = $this->gateway(500);
        $invoice = $this->sentInvoice(100);

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $cg->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->token = 'pm_autobill_big_fee';
        $token->gateway_customer_reference = 'cus_autobill_big_fee';
        $token->meta = (object) ['state' => 'authorized', 'last4' => '4242'];
        $token->is_default = true;
        $token->save();

        try {
            (new AutoBillInvoice($invoice, $this->company->db))->run();
        } catch (\Throwable $e) {
            // the charge cannot complete against a fake gateway
        }

        $hash = PaymentHash::query()->where('fee_invoice_id', $invoice->id)->orderBy('id', 'desc')->first();

        if ($hash) {
            $this->assertEquals(0, round((float) $hash->fee_total, 2), 'a fee larger than the billed amount must be dropped');
            $this->assertEquals(0, round((float) $hash->data->fee_net, 2));
        }

        $after = Invoice::withTrashed()->find($invoice->id);
        $this->assertEquals(100, round($after->amount, 2));
    }

    /* ---------------------------------------------------- driver call sequences */

    /**
     * Mollie, Braintree, GoCardless and CheckoutCom call confirmGatewayFee() directly and
     * then createPayment(), which calls it again. One fee, one ledger row.
     */
    public function testTheDriverConfirmThenCreatePaymentSequenceProducesOneFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $driver = (new BaseDriver($cg, $this->client))->setPaymentHash($payment_hash);

        /** The Mollie sequence, verbatim. */
        $driver->confirmGatewayFee(['gateway_type_id' => GatewayType::CREDIT_CARD]);

        $payment = $driver->createPayment([
            'amount' => $payment_hash->data->amount_with_fee,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payment_type' => PaymentType::VISA,
            'transaction_reference' => 'txn_' . Str::random(12),
        ]);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'the confirm-then-createPayment sequence produced two surcharges');
        $this->assertEquals(105, round($final->amount, 2));
        $this->assertEquals(0, round($final->balance, 2));
        $this->assertEquals(105, round((float) $payment->fresh()->amount, 2));
        $this->assertEquals(105, round($this->ledgerTotal($final), 2));
    }

    /** A pending payment that later completes confirms the fee exactly once. */
    public function testAPendingPaymentThatLaterCompletesConfirmsTheFeeOnce(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $reference = 'txn_' . Str::random(12);

        $data = [
            'amount' => $payment_hash->data->amount_with_fee,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payment_type' => PaymentType::VISA,
            'transaction_reference' => $reference,
        ];

        (new BaseDriver($cg, $this->client))->setPaymentHash($payment_hash)->createPayment($data, Payment::STATUS_PENDING);
        (new BaseDriver($cg, $this->client))->setPaymentHash($payment_hash->fresh())->createPayment($data, Payment::STATUS_COMPLETED);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash));
        $this->assertEquals(105, round($final->amount, 2));
        $this->assertEquals(1, Payment::query()->where('transaction_reference', $reference)->count());
    }

    /**
     * Transitional: a payment initiated before gateway fees became a quote left a pending
     * line on the invoice. Confirming it must promote that line, not add a second fee.
     */
    public function testConfirmationPromotesAPreQuotePendingLine(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $hash = Str::random(32);

        /** The invoice as the previous design would have left it. */
        $invoice = $this->withLegacyPendingFee($invoice, $hash, 5);
        $invoice->save();

        $this->assertEquals(105, round((float) $invoice->fresh()->amount, 2));

        $payment_hash = PaymentHash::create([
            'hash' => $hash,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => ['invoices' => [], 'credits' => 0, 'amount_with_fee' => 105],
        ]);

        $before_ledger = $this->ledgerTotal($invoice);

        $this->confirm($cg, $payment_hash);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = $this->feeLines($final, $hash);

        $this->assertCount(1, $lines, 'promotion duplicated the fee');
        $this->assertSame('4', (string) $lines[0]->type_id);

        /** The amount does not move - the line was already there - so no ledger adjustment. */
        $this->assertEquals(105, round((float) $final->amount, 2));
        $this->assertEquals($before_ledger, $this->ledgerTotal($final));
    }

    /* ----------------------------------------------------------- async lifecycle */

    /**
     * ACH and other async methods create a PENDING payment first. createPayment() confirms
     * the fee for PENDING as well as COMPLETED, so the fee lands at the pending step and
     * the later completion must not add a second one.
     */
    public function testAnAsyncPaymentConfirmsTheFeeOnceAcrossPendingThenCompleted(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $reference = 'txn_' . Str::random(12);

        $data = [
            'amount' => $payment_hash->data->amount_with_fee,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payment_type' => PaymentType::VISA,
            'transaction_reference' => $reference,
        ];

        /** processing webhook */
        (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment($data, Payment::STATUS_PENDING);

        $pending = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($pending, $payment_hash->hash), 'the fee did not land at the pending step');
        $this->assertEquals(105, round((float) $pending->amount, 2));
        $this->assertEquals(105, round($this->ledgerTotal($pending), 2));

        /** succeeded webhook */
        (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash->fresh())
            ->createPayment($data, Payment::STATUS_COMPLETED);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'completion added a second fee');
        $this->assertEquals(105, round((float) $final->amount, 2));
        $this->assertEquals(105, round($this->ledgerTotal($final), 2), 'completion posted a second ledger adjustment');
    }

    /**
     * An async payment that fails after going pending must give the fee back.
     *
     * The fee was confirmed at the pending step. The failure path unwinds the payment, and
     * the surcharge has to come off with it - the client was never debited.
     *
     * @see \App\Services\Invoice\ReverseGatewayFee
     */
    public function testAnAsyncPaymentFailingAfterPendingReversesTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $before_ledger = $this->ledgerTotal($invoice);
        $before_client_balance = (float) $this->client->fresh()->balance;

        $payment_hash = $this->initiate($invoice, $cg);
        $payment = $this->pendingPayment($cg, $payment_hash);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        $this->failPendingPayment($payment);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(0, $this->feeLines($final, $payment_hash->hash), 'the fee survived a failed payment');
        $this->assertEquals(100, round((float) $final->amount, 2));
        $this->assertEquals(100, round((float) $final->balance, 2));
        $this->assertEquals(0, round((float) $final->paid_to_date, 2));

        /**
         * Scoped to the fee rows: deleting a pending payment posts its own adjustment,
         * which is a separate pre-existing question.
         *
         * @see docs/payment-delete-double-adjustment.md
         */
        $fee_rows = $this->gatewayFeeLedgerRows();

        $this->assertCount(2, $fee_rows, 'the fee should have been added once and reversed once');
        $this->assertEquals(
            0,
            round((float) $fee_rows->sum('adjustment'), 2),
            'the confirmation and the reversal did not net to zero on the ledger'
        );

        $this->assertEquals(
            round($before_client_balance, 2),
            round((float) $this->client->fresh()->balance, 2),
            'the client balance did not return to where it started'
        );
    }

    /** A redelivered failure webhook must not reverse twice. */
    public function testARedeliveredFailureReversesTheFeeOnlyOnce(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);

        $payment_hash = $this->initiate($invoice, $cg);
        $payment = $this->pendingPayment($cg, $payment_hash);

        $this->failPendingPayment($payment);

        /** The webhook arrives again: the payment is already failed and already unwound. */
        (new ReverseGatewayFee($payment_hash->fresh()))->run();

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertEquals(100, round((float) $final->amount, 2));
        $this->assertCount(
            2,
            $this->gatewayFeeLedgerRows(),
            'the redelivered failure posted a second ledger adjustment'
        );
    }

    /**
     * A success arriving after the reversal is not a hazard: the confirmation finds no
     * line for the hash and rebuilds the fee from the hash.
     */
    public function testALateCompletionAfterAReversalRestoresTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);
        $payment = $this->pendingPayment($cg, $payment_hash);

        $this->failPendingPayment($payment);

        $this->assertEquals(100, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        $this->confirm($cg, $payment_hash->fresh());

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'the fee was not restored');
        $this->assertEquals(105, round((float) $final->amount, 2));
    }

    /**
     * The reversal is gated on the payment being off the invoice. A driver that marks a
     * payment failed without unwinding it would otherwise leave the invoice short by the
     * fee, since the payment still covers the fee inclusive amount.
     */
    public function testAFailedPaymentThatIsStillAppliedKeepsTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);
        $payment = $this->pendingPayment($cg, $payment_hash);

        $payment->status_id = Payment::STATUS_FAILED;
        $payment->save();

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'the fee was reversed while the payment was still applied');
        $this->assertEquals(105, round((float) $final->amount, 2));
    }

    /**
     * Failing a completed payment is a different event - the money moved, so the fee was
     * charged and stays. Only an unwound payment gives its fee back.
     */
    public function testACompletedPaymentMarkedFailedDoesNotReverseTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $payment = (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::VISA,
                'transaction_reference' => 'txn_' . Str::random(12),
            ]);

        $payment->status_id = Payment::STATUS_FAILED;
        $payment->save();

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(1, $this->feeLines($final, $payment_hash->hash), 'a settled payment gave its fee back');
        $this->assertEquals(105, round((float) $final->amount, 2));
    }

    /**
     * The Stripe failure webhook, end to end through the job that handles it.
     *
     * Proves the observer hook fires on the real failure path, where the payment is
     * unwound before it is marked failed.
     *
     * @see \App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook
     */
    public function testTheStripeFailureWebhookReversesTheFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        /** The failure mailer reads the invoice invitation for its reference. */
        $invoice->service()->createInvitations()->save();

        $reference = 'pi_' . Str::random(24);

        $payment = (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::VISA,
                'transaction_reference' => $reference,
            ], Payment::STATUS_PENDING);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        (new PaymentIntentFailureWebhook(
            ['object' => ['id' => $reference, 'failure_message' => 'account_closed']],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status_id);
        $this->assertCount(0, $this->feeLines($final, $payment_hash->hash), 'the failure webhook left the fee on the invoice');
        $this->assertEquals(100, round((float) $final->amount, 2));
        $this->assertEquals(100, round((float) $final->balance, 2));
    }

    /**
     * Deploy straddle: an attempt initiated before the change went pending under the old
     * design, which wrote the fee onto the invoice at initiation and promoted the line
     * when the payment landed. If that debit fails after the change, the reversal has to
     * take the old line off.
     */
    public function testAFailedPaymentReversesAFeeWrittenByThePreviousDesign(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $hash = Str::random(32);

        /** The invoice as the previous design left it once the payment went pending. */
        $invoice = $this->withLegacyPendingFee($invoice, $hash, 5, '4');
        $invoice->save();

        $payment_hash = PaymentHash::create([
            'hash' => $hash,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [[
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 100,
                    'due_date' => '',
                    'invoice_number' => $invoice->number,
                    'additional_info' => '',
                ]],
                'credits' => 0,
                'amount_with_fee' => 105,
            ],
        ]);

        $payment = $this->pendingPayment($cg, $payment_hash);

        /** Confirmation is a no-op here - the line is already on the invoice. */
        $this->assertCount(1, $this->feeLines(Invoice::withTrashed()->find($invoice->id), $hash));

        $this->failPendingPayment($payment);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(0, $this->feeLines($final, $hash), 'the pre-change fee survived a failed payment');
        $this->assertEquals(100, round((float) $final->amount, 2));
        $this->assertEquals(100, round((float) $final->balance, 2));
    }

    /** The same straddle, for a line the previous design had not promoted yet. */
    public function testAFailedPaymentReversesAnUnpromotedPreQuoteLine(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $hash = Str::random(32);

        $invoice = $this->withLegacyPendingFee($invoice, $hash, 5);
        $invoice->save();

        $payment_hash = PaymentHash::create([
            'hash' => $hash,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [[
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 100,
                    'due_date' => '',
                    'invoice_number' => $invoice->number,
                    'additional_info' => '',
                ]],
                'credits' => 0,
                'amount_with_fee' => 105,
            ],
        ]);

        $payment = $this->pendingPayment($cg, $payment_hash);

        $this->failPendingPayment($payment);

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(0, $this->feeLines($final, $hash), 'the pre-change pending fee survived a failed payment');
        $this->assertEquals(100, round((float) $final->amount, 2));

        /** The drain then finds nothing left to collect. */
        $final->service()->removeUnpaidGatewayFees()->save();

        $drained = Invoice::withTrashed()->find($invoice->id);

        $this->assertEquals(100, round((float) $drained->amount, 2), 'the drain removed the fee a second time');
    }

    /**
     * Contending out is the only way a confirmed fee can be lost, so it raises an alert
     * where it happens rather than leaving it for log scraping.
     */
    public function testContendingOutRaisesAnAlert(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        Bus::fake([SystemLogger::class]);

        /**
         * Every claim loses: the row is touched immediately before each claim runs, so the
         * updated_at the service observed never matches.
         */
        $bumping = false;

        \DB::beforeExecuting(function ($query, $bindings, $connection) use ($invoice, &$bumping) {
            if ($bumping || ! str_contains($query, 'update `invoices`') || ! str_contains($query, '`line_items`')) {
                return;
            }

            $bumping = true;

            $connection->table('invoices')
                       ->where('id', $invoice->id)
                       ->update(['updated_at' => now()->addSeconds(random_int(1, 1000))->format('Y-m-d H:i:s.u')]);

            $bumping = false;
        });

        (new ConfirmGatewayFee($payment_hash, $cg, ['gateway_type_id' => GatewayType::CREDIT_CARD]))->run();

        $final = Invoice::withTrashed()->find($invoice->id);

        Bus::assertDispatched(SystemLogger::class, function (SystemLogger $job) {
            return (new \ReflectionProperty($job, 'event_id'))->getValue($job) === SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE;
        });

        $this->assertEquals(100, round((float) $final->amount, 2), 'the fee landed despite every claim being lost');
    }

    /* -------------------------------------------------------------------- drain */

    /** The drain promotes a fee whose payment landed rather than deleting it. */
    public function testTheDrainPromotesAPendingFeeWhosePaymentLanded(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $hash = Str::random(32);

        $payment = \App\Factory\PaymentFactory::create($this->company->id, $this->user->id, $this->client->id);
        $payment->amount = 105;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        PaymentHash::create([
            'hash' => $hash,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'data' => ['invoices' => [], 'credits' => 0, 'amount_with_fee' => 105],
        ]);

        $invoice = $this->withLegacyPendingFee($invoice, $hash, 5);

        $invoice->service()->removeUnpaidGatewayFees()->save();

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = $this->feeLines($final, $hash);

        $this->assertCount(1, $lines, 'the drain deleted a fee that had been paid for');
        $this->assertSame('4', (string) $lines[0]->type_id);
    }

    /** The drain removes a pending fee with no payment behind it. */
    public function testTheDrainRemovesAnAbandonedPendingFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $hash = Str::random(32);

        PaymentHash::create([
            'hash' => $hash,
            'fee_total' => 5,
            'fee_invoice_id' => $invoice->id,
            'data' => ['invoices' => [], 'credits' => 0, 'amount_with_fee' => 105],
        ]);

        $invoice = $this->withLegacyPendingFee($invoice, $hash, 5);

        $invoice->service()->removeUnpaidGatewayFees()->save();

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(0, $this->feeLines($final, $hash));
        $this->assertEquals(100, round($final->amount, 2));
    }

    /** The drain must never touch a confirmed fee. */
    public function testTheDrainNeverRemovesAConfirmedFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->sentInvoice(100);
        $payment_hash = $this->initiate($invoice, $cg);

        $this->confirm($cg, $payment_hash);

        Invoice::withTrashed()->find($invoice->id)->service()->removeUnpaidGatewayFees()->save();

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = $this->feeLines($final, $payment_hash->hash);

        $this->assertCount(1, $lines, 'the drain removed a confirmed gateway fee');
        $this->assertSame('4', (string) $lines[0]->type_id);
        $this->assertEquals(105, round($final->amount, 2));
    }

    /** Writes a type 3 line the way the previous design did, to exercise the drain. */
    private function withLegacyPendingFee(Invoice $invoice, string $hash, float $cost, string $type_id = '3'): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;
        $item->type_id = $type_id;
        $item->unit_code = $hash;

        $line_items = (array) $invoice->line_items;
        $line_items[] = $item;
        $invoice->line_items = array_values($line_items);

        return $invoice->calc()->getInvoice();
    }
}
