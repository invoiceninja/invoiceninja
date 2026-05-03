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

namespace Tests\Feature\Import\CSV;

use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Tests that invoice CSV import correctly maintains client and invoice balances.
 *
 * The root cause being guarded against: InvoiceSum::setCalculatedAttributes()
 * detects `amount != balance` and computes `balance = total - paid_to_date`.
 * For a freshly-imported invoice with paid_to_date = 0, this resets balance to
 * the full amount, discarding any CSV-provided partial balance. The fix creates
 * an implied payment for the already-paid portion (amount - balance) so that the
 * existing payment pipeline correctly reduces both the invoice balance and the
 * client balance to the right values.
 */
class InvoiceImportBalanceTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build and execute a CSV invoice import with the given raw CSV string and
     * column mapping, returning the importer instance.
     */
    private function runInvoiceImport(string $csv, array $column_map): Csv
    {
        $hash = Str::random(32);

        $data = [
            'hash'       => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $importer = new Csv($data, $this->company);
        $importer->import('invoice');

        return $importer;
    }

    /**
     * Column map for the standard test CSV format:
     *
     *   Col 0: client.name
     *   Col 1: invoice.number
     *   Col 2: invoice.amount
     *   Col 3: invoice.balance
     *   Col 4: invoice.status
     *   Col 5: invoice.date
     *   Col 6: item.cost
     *   Col 7: item.quantity
     */
    private function standardColumnMap(): array
    {
        return [
            0 => 'client.name',
            1 => 'invoice.number',
            2 => 'invoice.amount',
            3 => 'invoice.balance',
            4 => 'invoice.status',
            5 => 'invoice.date',
            6 => 'item.cost',
            7 => 'item.quantity',
        ];
    }

    private function csvHeader(): string
    {
        return "Client Name,Invoice Number,Amount,Balance,Status,Date,Item Cost,Item Qty\n";
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Core regression test: importing a SENT invoice where balance < amount
     * (i.e. a partial payment has already been made) must result in:
     *   - An implied payment record for (amount - balance)
     *   - invoice.balance  = the CSV-provided balance (not the full amount)
     *   - invoice.status   = PARTIAL
     *   - client.balance   = the CSV-provided balance (not the full amount)
     *   - client.paid_to_date = amount - balance
     */
    public function testPartialInvoiceImportCreatesImpliedPaymentAndCorrectBalance(): void
    {
        // amount=100, balance=60 → 40 already paid
        $csv = $this->csvHeader()
            ."Balance Test Client A,INV-BAL-001,100.00,60.00,sent,2024-01-15,100.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);
        $this->assertTrue($transformer->hasInvoice('INV-BAL-001'), 'Invoice should have been created');

        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-BAL-001'));

        // --- invoice balance ---
        $this->assertEquals(
            60.00,
            round($invoice->balance, 2),
            'Invoice balance should equal the CSV-provided balance (60), not the full amount (100)'
        );

        // --- implied payment ---
        $this->assertEquals(
            1,
            $invoice->payments()->count(),
            'One implied payment should be created for the already-paid portion'
        );
        $this->assertEquals(
            40.00,
            round($invoice->payments()->sum('payments.amount'), 2),
            'Implied payment should equal amount - balance (100 - 60 = 40)'
        );

        // --- invoice paid_to_date ---
        $this->assertEquals(
            40.00,
            round($invoice->fresh()->paid_to_date, 2),
            'Invoice paid_to_date should reflect the implied payment (40)'
        );

        // --- invoice status ---
        $this->assertEquals(
            Invoice::STATUS_PARTIAL,
            $invoice->fresh()->status_id,
            'Invoice status should be PARTIAL because balance < amount'
        );

        // --- client balances ---
        $client = Client::find($invoice->client_id)->fresh();

        $this->assertEquals(
            60.00,
            round($client->balance, 2),
            'Client balance should equal the remaining invoice balance (60), not the full amount'
        );
        $this->assertEquals(
            40.00,
            round($client->paid_to_date, 2),
            'Client paid_to_date should reflect the implied payment (40)'
        );
    }

    /**
     * When balance == amount (nothing paid), no implied payment is created and
     * the invoice is imported as SENT with the full amount outstanding.
     */
    public function testSentInvoiceWithFullBalanceCreatesNoImpliedPayment(): void
    {
        $csv = $this->csvHeader()
            ."Balance Test Client B,INV-BAL-002,150.00,150.00,sent,2024-01-15,150.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-BAL-002'));

        $this->assertEquals(
            0,
            $invoice->payments()->count(),
            'No payment should be created when balance equals amount'
        );

        $this->assertEquals(150.00, round($invoice->balance, 2));
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->fresh()->status_id);

        $client = Client::find($invoice->client_id)->fresh();
        $this->assertEquals(150.00, round($client->balance, 2));
        $this->assertEquals(0.00, round($client->paid_to_date, 2));
    }

    /**
     * When no invoice.balance column is present in the CSV, balance defaults
     * to the invoice amount and no implied payment is created.
     */
    public function testSentInvoiceWithNoBalanceColumnCreatesNoImpliedPayment(): void
    {
        $noBalanceMap = [
            0 => 'client.name',
            1 => 'invoice.number',
            2 => 'invoice.amount',
            3 => 'invoice.status',
            4 => 'invoice.date',
            5 => 'item.cost',
            6 => 'item.quantity',
        ];

        $csv = "Client Name,Invoice Number,Amount,Status,Date,Item Cost,Item Qty\n"
            ."Balance Test Client C,INV-BAL-003,200.00,sent,2024-01-15,200.00,1\n";

        $this->runInvoiceImport($csv, $noBalanceMap);

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-BAL-003'));

        $this->assertEquals(
            0,
            $invoice->payments()->count(),
            'No payment should be created when the balance column is absent'
        );

        $this->assertEquals(200.00, round($invoice->balance, 2));
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->fresh()->status_id);

        $client = Client::find($invoice->client_id)->fresh();
        $this->assertEquals(200.00, round($client->balance, 2));
    }

    /**
     * A PAID status invoice follows the existing full-payment path regardless
     * of what the balance column contains. Client balance should be 0.
     */
    public function testPaidStatusImportCreatesFullPaymentAndZeroClientBalance(): void
    {
        $csv = $this->csvHeader()
            ."Balance Test Client D,INV-BAL-004,80.00,80.00,paid,2024-01-15,80.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-BAL-004'));

        $this->assertEquals(
            1,
            $invoice->payments()->count(),
            'A single payment for the full amount should be created for a PAID invoice'
        );
        $this->assertEquals(80.00, round($invoice->payments()->sum('payments.amount'), 2));
        $this->assertEquals(0.00, round($invoice->fresh()->balance, 2));
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->fresh()->status_id);

        $client = Client::find($invoice->client_id)->fresh();
        $this->assertEquals(
            0.00,
            round($client->balance, 2),
            'Client balance should be 0 for a fully paid invoice'
        );
        $this->assertEquals(80.00, round($client->paid_to_date, 2));
    }

    /**
     * A SENT invoice imported with balance=0 (fully paid but not explicitly
     * marked as "paid") should also generate an implied payment for the full
     * amount, resulting in status=PAID and client balance=0.
     */
    public function testZeroBalanceWithSentStatusBecomesFullyPaid(): void
    {
        $csv = $this->csvHeader()
            ."Balance Test Client E,INV-BAL-005,75.00,0.00,sent,2024-01-15,75.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-BAL-005'));

        $this->assertEquals(
            1,
            $invoice->payments()->count(),
            'An implied payment for the full amount should be created when balance=0'
        );
        $this->assertEquals(75.00, round($invoice->payments()->sum('payments.amount'), 2));
        $this->assertEquals(0.00, round($invoice->fresh()->balance, 2));
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->fresh()->status_id);

        $client = Client::find($invoice->client_id)->fresh();
        $this->assertEquals(0.00, round($client->balance, 2));
        $this->assertEquals(75.00, round($client->paid_to_date, 2));
    }

    /**
     * When a payment.amount column is present in the CSV it takes precedence
     * over any implied payment derived from balance < amount. The explicit
     * payment amount is used as-is.
     */
    public function testExplicitPaymentAmountColumnTakesPrecedenceOverImpliedPayment(): void
    {
        // amount=100, balance=60 would imply a payment of 40, but an explicit
        // payment.amount column maps 50 — the explicit value must win.
        $explicitPaymentMap = [
            0 => 'client.name',
            1 => 'invoice.number',
            2 => 'invoice.amount',
            3 => 'invoice.balance',
            4 => 'invoice.status',
            5 => 'invoice.date',
            6 => 'item.cost',
            7 => 'item.quantity',
            8 => 'payment.amount',
        ];

        $csv = "Client Name,Invoice Number,Amount,Balance,Status,Date,Item Cost,Item Qty,Payment Amount\n"
            ."Balance Test Client F,INV-BAL-006,100.00,60.00,sent,2024-01-15,100.00,1,50.00\n";

        $this->runInvoiceImport($csv, $explicitPaymentMap);

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with('payments')->find($transformer->getInvoiceId('INV-BAL-006'));

        $this->assertEquals(
            1,
            $invoice->payments()->count(),
            'Only one payment should exist (the explicit payment.amount, not an additional implied one)'
        );
        $this->assertEquals(
            50.00,
            round($invoice->payments()->sum('payments.amount'), 2),
            'Payment amount should match the explicit payment.amount column (50), not the implied amount (40)'
        );
    }

    /**
     * Multiple partially-paid invoices for the same client must accumulate
     * correctly: client.balance should equal the sum of all remaining balances.
     */
    public function testMultiplePartialInvoicesAccumulateClientBalanceCorrectly(): void
    {
        // INV-MULTI-001: amount=100, balance=40 → 60 already paid
        // INV-MULTI-002: amount=200, balance=120 → 80 already paid
        // Expected client balance = 40 + 120 = 160
        $csv = $this->csvHeader()
            ."Balance Test Client G,INV-MULTI-001,100.00,40.00,sent,2024-01-15,100.00,1\n"
            ."Balance Test Client G,INV-MULTI-002,200.00,120.00,sent,2024-01-16,200.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);

        $invoice1 = Invoice::find($transformer->getInvoiceId('INV-MULTI-001'));
        $invoice2 = Invoice::find($transformer->getInvoiceId('INV-MULTI-002'));

        $this->assertEquals(40.00, round($invoice1->balance, 2), 'INV-MULTI-001 balance should be 40');
        $this->assertEquals(120.00, round($invoice2->balance, 2), 'INV-MULTI-002 balance should be 120');

        // Both invoices belong to the same auto-created client
        $this->assertEquals($invoice1->client_id, $invoice2->client_id, 'Both invoices should share the same client');

        $client = Client::find($invoice1->client_id)->fresh();
        $this->assertEquals(
            160.00,
            round($client->balance, 2),
            'Client balance should equal the sum of remaining invoice balances (40 + 120 = 160)'
        );
        $this->assertEquals(
            140.00,
            round($client->paid_to_date, 2),
            'Client paid_to_date should equal the sum of implied payments (60 + 80 = 140)'
        );
    }

    /**
     * Draft invoices should not trigger payment creation or client balance
     * changes regardless of the balance column value.
     */
    public function testDraftInvoiceWithPartialBalanceCreatesNoPaymentAndNoClientBalance(): void
    {
        $csv = $this->csvHeader()
            ."Balance Test Client H,INV-DRAFT-001,100.00,60.00,draft,2024-01-15,100.00,1\n";

        $this->runInvoiceImport($csv, $this->standardColumnMap());

        $transformer = new BaseTransformer($this->company);
        $invoice = Invoice::with(['payments', 'client'])->find($transformer->getInvoiceId('INV-DRAFT-001'));

        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status_id, 'Invoice should remain DRAFT');

        $this->assertEquals(
            0,
            $invoice->payments()->count(),
            'No payment should be created for a DRAFT invoice even when balance < amount'
        );

        $client = Client::find($invoice->client_id)->fresh();
        $this->assertEquals(
            0.00,
            round($client->balance, 2),
            'Client balance should not be affected by a DRAFT invoice import'
        );
    }
}
