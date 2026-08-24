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
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\BaseDriver;
use App\PaymentDrivers\CheckoutCom\CheckoutWebhook;
use App\PaymentDrivers\LawPay\Jobs\LawPayWebhook;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\PaymentDrivers\MolliePaymentDriver;
use App\PaymentDrivers\Square\SquareWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Every gateway ends a pending debit that will not settle the same way: the payment is
 * unwound, then marked failed. The unwind returns the invoice balance, and the failed
 * state is what releases the gateway fee.
 *
 * Stripe's path is covered in GatewayFeeConcurrencyTest. Mollie's is not reachable from a
 * test - its webhook fetches the payment from the Mollie API before it decides anything.
 *
 * @see \App\Services\Invoice\ReverseGatewayFee
 */
class GatewayFailurePathTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function gateway(string $gateway_key, array $config = [], float $fee_amount = 5): CompanyGateway
    {
        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = $gateway_key;
        $cg->config = encrypt(json_encode((object) $config));
        $cg->fees_and_limits = [
            GatewayType::CREDIT_CARD => [
                'min_limit' => -1, 'max_limit' => -1,
                'fee_amount' => $fee_amount, 'fee_percent' => 0,
                'fee_tax_name1' => '', 'fee_tax_rate1' => 0,
                'fee_tax_name2' => '', 'fee_tax_rate2' => 0,
                'fee_tax_name3' => '', 'fee_tax_rate3' => 0,
                'fee_cap' => 0, 'adjust_fee_percent' => false, 'is_enabled' => true,
            ],
        ];
        $cg->save();

        return $cg;
    }

    private function sentInvoice(float $cost = 100): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->service()->markSent()->save();

        /** The failure mailer reads the invoice invitation for its reference. */
        return $invoice->service()->createInvitations()->save();
    }

    /**
     * A pending payment carrying a confirmed fee - what an async debit looks like once the
     * gateway reports it as processing.
     *
     * @return array{0: PaymentHash, 1: Payment}
     */
    private function pendingAttempt(CompanyGateway $cg, Invoice $invoice, string $reference): array
    {
        $quote = $invoice->service()->quoteGatewayFee($cg, GatewayType::CREDIT_CARD, 100);

        $payment_hash = PaymentHash::create([
            'hash' => Str::random(32),
            'fee_total' => $quote['gross'],
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
                'fee_net' => $quote['net'],
                'amount_with_fee' => round(100 + $quote['gross'], 2),
            ],
        ]);

        $payment = (new BaseDriver($cg, $this->client))
            ->setPaymentHash($payment_hash)
            ->createPayment([
                'amount' => $payment_hash->data->amount_with_fee,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'transaction_reference' => $reference,
            ], Payment::STATUS_PENDING);

        return [$payment_hash, $payment];
    }

    private function feeLines(Invoice $invoice, string $hash): array
    {
        return collect($invoice->line_items)
                ->filter(fn ($item) => ($item->unit_code ?? '') === $hash)
                ->values()
                ->all();
    }

    private function assertPendingAttemptWasUnwound(Invoice $invoice, PaymentHash $payment_hash, Payment $payment): void
    {
        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status_id, 'the failed debit did not end as failed');
        $this->assertTrue((bool) $payment->fresh()->is_deleted, 'the failed debit was left applied to the invoice');
        $this->assertCount(0, $this->feeLines($final, $payment_hash->hash), 'the failed debit left its gateway fee on the invoice');
        $this->assertEquals(100, round((float) $final->amount, 2));
        $this->assertEquals(100, round((float) $final->balance, 2));
        $this->assertEquals(0, round((float) $final->paid_to_date, 2));
    }

    /** Checkout.com reported a pending payment as declined. */
    public function testCheckoutWebhookUnwindsAPendingPaymentAndReversesTheFee(): void
    {
        $cg = $this->gateway('3758e7f7c6f4cecf0f4f348b9a00f456');
        $invoice = $this->sentInvoice(100);
        $reference = 'pay_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        (new CheckoutWebhook(
            [
                'type' => 'payment_declined',
                'data' => ['id' => $reference, 'metadata' => ['udf2' => $payment_hash->hash]],
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /**
     * A decline that arrives after the payment settled unwinds it too - the money is not
     * coming, whichever state the payment was in when the gateway said so.
     */
    public function testCheckoutWebhookUnwindsASettledPaymentThatIsLaterDeclined(): void
    {
        $cg = $this->gateway('3758e7f7c6f4cecf0f4f348b9a00f456');
        $invoice = $this->sentInvoice(100);
        $reference = 'pay_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        (new CheckoutWebhook(
            [
                'type' => 'payment_canceled',
                'data' => ['id' => $reference, 'metadata' => ['udf2' => $payment_hash->hash]],
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /** A second delivery of the same decline changes nothing. */
    public function testCheckoutWebhookIgnoresARedeliveredDecline(): void
    {
        $cg = $this->gateway('3758e7f7c6f4cecf0f4f348b9a00f456');
        $invoice = $this->sentInvoice(100);
        $reference = 'pay_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $webhook = [
            'type' => 'payment_declined',
            'data' => ['id' => $reference, 'metadata' => ['udf2' => $payment_hash->hash]],
        ];

        (new CheckoutWebhook($webhook, $this->company->company_key, $cg->id))->handle();
        (new CheckoutWebhook($webhook, $this->company->company_key, $cg->id))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /** LawPay reported an ACH debit as returned. */
    public function testLawPayWebhookUnwindsAPendingPaymentAndReversesTheFee(): void
    {
        $cg = $this->gateway('f4lafbnygsmkflagbqp7zqnfpgeoekdn');
        $invoice = $this->sentInvoice(100);
        $reference = 'chr_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        (new LawPayWebhook(
            [
                'event' => 'charge.returned',
                'id' => $reference,
                'status' => 'returned',
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /** Square reported a pending payment as cancelled - the same outcome as failed. */
    public function testSquareWebhookFailsAPendingPaymentAndReversesTheFee(): void
    {
        /** The webhook builds a Square client before it reads the payment. */
        $cg = $this->gateway('65faab2ab6e3223dbe848b1686490baz', [
            'accessToken' => 'sandbox-token',
            'testMode' => true,
        ]);
        $invoice = $this->sentInvoice(100);
        $reference = 'sq_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        (new SquareWebhook(
            [
                'data' => ['object' => ['payment' => ['id' => $reference, 'status' => 'CANCELED']]],
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /** When payment_id was never linked, Square's reference_id resolves the hash directly. */
    public function testSquareWebhookResolvesPaymentHashFromReferenceId(): void
    {
        $cg = $this->gateway('65faab2ab6e3223dbe848b1686490baz', [
            'accessToken' => 'sandbox-token',
            'testMode' => true,
        ]);
        $invoice = $this->sentInvoice(100);
        $reference = 'sq_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $payment_hash->payment_id = null;
        $payment_hash->save();

        (new SquareWebhook(
            [
                'data' => ['object' => ['payment' => [
                    'id' => $reference,
                    'status' => 'CANCELED',
                    'reference_id' => $payment_hash->hash,
                ]]],
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);
    }

    /** An ACH return that arrives after LawPay settled the debit unwinds it too. */
    public function testLawPayWebhookUnwindsASettledDebitThatIsLaterReturned(): void
    {
        $cg = $this->gateway('f4lafbnygsmkflagbqp7zqnfpgeoekdn');
        $invoice = $this->sentInvoice(100);
        $reference = 'chr_' . Str::random(20);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        (new LawPayWebhook(
            [
                'event' => 'charge.returned',
                'id' => $reference,
                'status' => 'returned',
            ],
            $this->company->company_key,
            $cg->id
        ))->handle();

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }

    /**
     * Mollie reported a pending payment as expired.
     *
     * Its webhook reads the payment from the Mollie API before it decides anything, so the
     * API client is stubbed - everything after it is the real path.
     */
    public function testMollieWebhookFailsAnExpiredPaymentAndReversesTheFee(): void
    {
        $cg = $this->gateway('1bd651fb213ca0c9d66ae3c336dc77e8');
        $invoice = $this->sentInvoice(100);
        $reference = 'tr_' . Str::random(10);

        [$payment_hash, $payment] = $this->pendingAttempt($cg, $invoice, $reference);

        $this->assertEquals(105, round((float) Invoice::withTrashed()->find($invoice->id)->amount, 2));

        $mollie_payment = (object) [
            'id' => $reference,
            'status' => 'expired',
            'details' => (object) ['failureMessage' => 'The payment expired'],
            'metadata' => (object) [
                'hash' => $payment_hash->hash,
                'client_id' => $this->client->hashed_id,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type_id' => PaymentType::CREDIT_CARD_OTHER,
            ],
        ];

        $driver = new class ($cg, $this->client) extends MolliePaymentDriver {
            public $stub_payment;

            public function init(): self
            {
                $this->gateway = new class ($this->stub_payment) {
                    public $payments;

                    public function __construct($payment)
                    {
                        $this->payments = new class ($payment) {
                            public function __construct(private $payment)
                            {
                            }

                            public function get($id)
                            {
                                return $this->payment;
                            }
                        };
                    }
                };

                return $this;
            }
        };

        $driver->stub_payment = $mollie_payment;

        $request = PaymentWebhookRequest::create('/', 'POST', ['id' => $reference]);
        $request->setContainer(app());

        $driver->processWebhookRequest($request);

        $this->assertPendingAttemptWasUnwound($invoice, $payment_hash, $payment);
    }
}
