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

use App\Factory\InvoiceItemFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Models\Invoice;
use App\Repositories\PaymentRepository;
use App\Services\Payment\DeletePaymentV2;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceMarkPaidCycleTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
        Model::reguard();
        $this->makeTestData();
    }

    public function testMarkPaidAfterApplyDeleteApplyDeleteMarkPaidDelete()
    {
        // 1) Create a $3000 invoice and mark it sent
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 3000;
        $item->product_key = 'test';
        $item->notes = 'test';
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->service()->markSent()->save();
        $this->assertEquals(3000, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        // Helper: apply a $3000 payment via the same path the API uses
        $applyPayment = function () use ($invoice) {
            $data = [
                'amount' => 3000,
                'client_id' => $this->client->id,
                'invoices' => [
                    ['invoice_id' => $invoice->id, 'amount' => 3000],
                ],
                'date' => '2026-01-01',
            ];

            $payment = PaymentFactory::create($this->company->id, $this->user->id);

            return (new PaymentRepository(new \App\Repositories\CreditRepository()))
                ->save($data, $payment);
        };

        // 2) Apply $3000 payment
        $payment_a = $applyPayment();
        $invoice = $invoice->fresh();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(3000, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // 3) Delete payment
        (new DeletePaymentV2($payment_a, true))->run();
        $invoice = $invoice->fresh();
        $this->assertEquals(3000, $invoice->balance, 'after first delete, balance should be restored to 3000');
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        // 4) Apply $3000 payment
        $payment_b = $applyPayment();
        $invoice = $invoice->fresh();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(3000, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // 5) Delete payment
        (new DeletePaymentV2($payment_b, true))->run();
        $invoice = $invoice->fresh();
        $this->assertEquals(3000, $invoice->balance, 'after second delete, balance should be restored to 3000');
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        // 6) Mark Paid → creates payment 0047
        $invoice = $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();
        $payment_c = $invoice->payments()->orderByDesc('id')->first();
        $this->assertNotNull($payment_c);
        $this->assertEquals(3000, $payment_c->amount, 'mark paid should produce a $3000 payment');
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(3000, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // 7) Delete that mark-paid payment
        (new DeletePaymentV2($payment_c, true))->run();
        $invoice = $invoice->fresh();
        $this->assertEquals(3000, $invoice->balance, 'after deleting the mark-paid payment, balance should be 3000');
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        // 8) Mark Paid again — the straight sequence works fine
        $invoice = $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();
        $payment_d = $invoice->payments()->whereNull('payments.deleted_at')->orderByDesc('id')->first();

        $this->assertNotNull($payment_d);
        $this->assertEquals(3000, $payment_d->amount);
    }

    /**
     * Reproduces a production case: invoice 4422.01 paid down via three
     * partial payments (1500, 1000, 1922.01). The third payment must zero
     * out the balance — production was leaving balance at 1922.01 while
     * paid_to_date and status_id transitioned correctly.
     */
    public function testPartialPaymentSequenceZeroesBalanceOnFinalPayment()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 4422.01;
        $item->product_key = 'test';
        $item->notes = 'test';
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->service()->markSent()->save();
        $this->assertEquals(4422.01, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        $applyPayment = function (float $amount) use ($invoice) {
            $data = [
                'amount' => $amount,
                'client_id' => $this->client->id,
                'invoices' => [
                    ['invoice_id' => $invoice->id, 'amount' => $amount],
                ],
                'date' => '2026-01-01',
            ];

            $payment = PaymentFactory::create($this->company->id, $this->user->id);

            return (new PaymentRepository(new \App\Repositories\CreditRepository()))
                ->save($data, $payment);
        };

        // Payment 1: 1500.00
        $applyPayment(1500.00);
        $invoice = $invoice->fresh();
        $this->assertEquals(2922.01, round($invoice->balance, 2));
        $this->assertEquals(1500.00, round($invoice->paid_to_date, 2));
        $this->assertEquals(Invoice::STATUS_PARTIAL, $invoice->status_id);

        // Payment 2: 1000.00
        $applyPayment(1000.00);
        $invoice = $invoice->fresh();
        $this->assertEquals(1922.01, round($invoice->balance, 2));
        $this->assertEquals(2500.00, round($invoice->paid_to_date, 2));
        $this->assertEquals(Invoice::STATUS_PARTIAL, $invoice->status_id);

        // Payment 3: 1922.01 — final payment, balance MUST reach 0
        $applyPayment(1922.01);
        $invoice = $invoice->fresh();
        $this->assertEquals(0, round($invoice->balance, 2), 'balance must be 0 after final partial payment');
        $this->assertEquals(4422.01, round($invoice->paid_to_date, 2));
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);
    }

    /**
     * Same production amounts (4422.01 / 1500 / 1000 / 1922.01) but the
     * final settlement happens via the Mark Paid button (InvoiceService::markPaid
     * → MarkPaid::run) instead of creating a payment record explicitly.
     */
    public function testPartialPaymentsThenMarkPaidZeroesBalance()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 4422.01;
        $item->product_key = 'test';
        $item->notes = 'test';
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->service()->markSent()->save();

        $applyPayment = function (float $amount) use ($invoice) {
            $data = [
                'amount' => $amount,
                'client_id' => $this->client->id,
                'invoices' => [
                    ['invoice_id' => $invoice->id, 'amount' => $amount],
                ],
                'date' => '2026-01-01',
            ];

            $payment = PaymentFactory::create($this->company->id, $this->user->id);

            return (new PaymentRepository(new \App\Repositories\CreditRepository()))
                ->save($data, $payment);
        };

        $applyPayment(1500.00);
        $applyPayment(1000.00);

        $invoice = $invoice->fresh();
        $this->assertEquals(1922.01, round($invoice->balance, 2));
        $this->assertEquals(2500.00, round($invoice->paid_to_date, 2));

        // Final settlement: Mark Paid (creates payment for remaining balance)
        $invoice = $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(0, round($invoice->balance, 2), 'balance must be 0 after MarkPaid on remaining partial');
        $this->assertEquals(4422.01, round($invoice->paid_to_date, 2));
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        $mark_paid_payment = $invoice->payments()->orderByDesc('id')->first();
        $this->assertNotNull($mark_paid_payment);
        $this->assertEquals(1922.01, round($mark_paid_payment->amount, 2));
    }

    /**
     * Same scenario but the partial payments are mixed: PaymentRepository for
     * the first two, then MarkPaid for the last. Also covers the case where
     * $invoice->partial is set non-zero before the final payment lands.
     */
    public function testPartialAttributeSetThenMarkPaidZeroesBalance()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 4422.01;
        $item->product_key = 'test';
        $item->notes = 'test';
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice->partial = 1500.00;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->service()->markSent()->save();

        $applyPayment = function (float $amount) use ($invoice) {
            $data = [
                'amount' => $amount,
                'client_id' => $this->client->id,
                'invoices' => [
                    ['invoice_id' => $invoice->id, 'amount' => $amount],
                ],
                'date' => '2026-01-01',
            ];

            $payment = PaymentFactory::create($this->company->id, $this->user->id);

            return (new PaymentRepository(new \App\Repositories\CreditRepository()))
                ->save($data, $payment);
        };

        // Pay the partial deposit
        $applyPayment(1500.00);
        $invoice = $invoice->fresh();
        $this->assertEquals(2922.01, round($invoice->balance, 2));

        // Another partial
        $applyPayment(1000.00);
        $invoice = $invoice->fresh();
        $this->assertEquals(1922.01, round($invoice->balance, 2));

        // Final
        $applyPayment(1922.01);
        $invoice = $invoice->fresh();
        $this->assertEquals(0, round($invoice->balance, 2), 'balance must be 0 even when partial was set on entry');
        $this->assertEquals(4422.01, round($invoice->paid_to_date, 2));
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);
    }
}
