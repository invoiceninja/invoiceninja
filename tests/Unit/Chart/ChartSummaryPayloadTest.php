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

namespace Tests\Unit\Chart;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Chart\ChartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\MockAccountData;
use Tests\TestCase;

class ChartSummaryPayloadTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private Company $chartCompany;

    private Client $usdClient;

    private Client $gbpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $companySettings = CompanySettings::defaults();
        $companySettings->currency_id = '1';

        $this->chartCompany = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $companySettings,
        ]);

        $usdSettings = ClientSettings::defaults();
        $usdSettings->currency_id = '1';

        $this->usdClient = Client::factory()->create([
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'settings' => $usdSettings,
        ]);

        $gbpSettings = ClientSettings::defaults();
        $gbpSettings->currency_id = '2';

        $this->gbpClient = Client::factory()->create([
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'settings' => $gbpSettings,
        ]);
    }

    public function testChartSummaryPreservesTheCompleteMultiCurrencyPayloadAndCalculations(): void
    {
        $this->createInvoices();
        $this->createPayments();
        $this->createExpenses();

        $chartService = new ChartService($this->chartCompany, $this->user, true);
        $payload = $this->assertBatchedSummaryMatchesLegacy($chartService, '2026-01-01', '2026-03-31');

        $chartServiceWithDrafts = new ChartService($this->chartCompany, $this->user, true, true);
        $payloadWithDrafts = $this->assertBatchedSummaryMatchesLegacy(
            $chartServiceWithDrafts,
            '2026-01-01',
            '2026-03-31',
            true,
            true
        );

        $this->assertSame(
            ['2026-01-10', '2026-01-20', '2026-02-10', '2026-02-15'],
            array_column($payloadWithDrafts[1]['invoices'], 'date')
        );
        $this->assertSame('999.000000', $payloadWithDrafts[1]['invoices'][3]['total']);
        $this->assertSame('1059.000000', $payloadWithDrafts[1]['outstanding'][1]['total']);

        $this->assertEqualsCanonicalizing(
            ['start_date', 'end_date', 1, 2, 999],
            array_keys($payload)
        );

        $payload = [
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            1 => $payload[1],
            2 => $payload[2],
            999 => $payload[999],
        ];

        $this->assertSame([
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            1 => [
                'invoices' => [
                    ['total' => '100.000000', 'date' => '2026-01-10'],
                    ['total' => '50.000000', 'date' => '2026-01-20'],
                    ['total' => '80.000000', 'date' => '2026-02-10'],
                ],
                'outstanding' => [
                    ['total' => '40.000000', 'date' => '2026-01-31'],
                    ['total' => '60.000000', 'date' => '2026-02-28'],
                ],
                'payments' => [
                    ['total' => '70.000000', 'date' => '2026-01-25'],
                    ['total' => '30.000000', 'date' => '2026-03-20'],
                ],
                'expenses' => [
                    ['total' => '120.0000000000000000', 'date' => '2026-01-12'],
                    ['total' => '50.0000000000000000', 'date' => '2026-02-12'],
                ],
            ],
            2 => [
                'invoices' => [
                    ['total' => '200.000000', 'date' => '2026-01-15'],
                    ['total' => '120.000000', 'date' => '2026-03-05'],
                ],
                'outstanding' => [
                    ['total' => '100.000000', 'date' => '2026-01-31'],
                    ['total' => '160.000000', 'date' => '2026-03-31'],
                ],
                'payments' => [
                    ['total' => '180.000000', 'date' => '2026-02-20'],
                ],
                'expenses' => [
                    ['total' => '200.0000000000000000', 'date' => '2026-02-12'],
                    ['total' => '120.0000000000000000', 'date' => '2026-03-12'],
                ],
            ],
            999 => [
                'invoices' => [
                    ['total' => '100.0000000000', 'date' => '2026-01-10'],
                    ['total' => '100.0000000000', 'date' => '2026-01-15'],
                    ['total' => '50.0000000000', 'date' => '2026-01-20'],
                    ['total' => '80.0000000000', 'date' => '2026-02-10'],
                    ['total' => '60.0000000000', 'date' => '2026-03-05'],
                ],
                'outstanding' => [
                    ['total' => '90.0000000000', 'date' => '2026-01-31'],
                    ['total' => '110.0000000000', 'date' => '2026-02-28'],
                    ['total' => '140.0000000000', 'date' => '2026-03-31'],
                ],
                'payments' => [
                    ['total' => '70.000000000000', 'date' => '2026-01-25'],
                    ['total' => '90.000000000000', 'date' => '2026-02-20'],
                    ['total' => '30.000000000000', 'date' => '2026-03-20'],
                ],
                'expenses' => [
                    ['total' => '120.0000000000000000000000', 'date' => '2026-01-12'],
                    ['total' => '150.0000000000000000000000', 'date' => '2026-02-12'],
                    ['total' => '60.0000000000000000000000', 'date' => '2026-03-12'],
                ],
            ],
        ], $payload);
    }

    public function testChartSummaryPreservesAnEmptyPayload(): void
    {
        $payload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $emptyCurrencyPayload = [
            'invoices' => [],
            'outstanding' => [],
            'payments' => [],
            'expenses' => [],
        ];

        $this->assertSame($emptyCurrencyPayload, $payload[1]);
        $this->assertSame($emptyCurrencyPayload, $payload[2]);
        $this->assertSame($emptyCurrencyPayload, $payload[999]);
    }

    public function testChartSummaryPreservesInclusiveDateBoundaries(): void
    {
        foreach ([
            ['2025-12-31', 100, 100],
            ['2026-01-01', 10, 4],
            ['2026-01-31', 20, 6],
            ['2026-02-01', 200, 200],
        ] as [$date, $amount, $balance]) {
            $this->createChartInvoice($this->usdClient, [
                'date' => $date,
                'due_date' => $date,
                'amount' => $amount,
                'balance' => $balance,
            ]);

            $this->createChartPayment($this->usdClient, [
                'date' => $date,
                'amount' => $amount,
                'refunded' => $amount / 10,
            ]);

            $this->createChartExpense([
                'date' => $date,
                'amount' => $amount,
                'uses_inclusive_taxes' => true,
            ]);
        }

        $payload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertSame(['2026-01-01', '2026-01-31'], array_column($payload[1]['invoices'], 'date'));
        $this->assertSame(['10.000000', '20.000000'], array_column($payload[1]['invoices'], 'total'));
        $this->assertSame([['total' => '10.000000', 'date' => '2026-01-31']], $payload[1]['outstanding']);
        $this->assertSame(['2026-01-01', '2026-01-31'], array_column($payload[1]['payments'], 'date'));
        $this->assertSame(['9.000000', '18.000000'], array_column($payload[1]['payments'], 'total'));
        $this->assertSame(['2026-01-01', '2026-01-31'], array_column($payload[1]['expenses'], 'date'));
    }

    public function testChartSummaryPreservesInvoiceAndPaymentStatusRules(): void
    {
        foreach ([
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_SENT,
            Invoice::STATUS_PARTIAL,
            Invoice::STATUS_PAID,
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_REVERSED,
        ] as $statusId) {
            $this->createChartInvoice($this->usdClient, [
                'date' => sprintf('2026-01-%02d', $statusId),
                'due_date' => sprintf('2026-01-%02d', $statusId),
                'amount' => $statusId * 10,
                'balance' => $statusId * 2,
                'status_id' => $statusId,
            ]);
        }

        foreach ([
            Payment::STATUS_PENDING,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_FAILED,
            Payment::STATUS_COMPLETED,
            Payment::STATUS_PARTIALLY_REFUNDED,
            Payment::STATUS_REFUNDED,
        ] as $statusId) {
            $this->createChartPayment($this->usdClient, [
                'date' => sprintf('2026-02-%02d', $statusId),
                'amount' => $statusId * 10,
                'status_id' => $statusId,
            ]);
        }

        $withoutDrafts = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-02-28'
        );
        $withDrafts = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true, true),
            '2026-01-01',
            '2026-02-28',
            true,
            true
        );

        $this->assertSame(['2026-01-02', '2026-01-03', '2026-01-04'], array_column($withoutDrafts[1]['invoices'], 'date'));
        $this->assertSame([['total' => '10.000000', 'date' => '2026-01-31']], $withoutDrafts[1]['outstanding']);
        $this->assertSame(['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'], array_column($withDrafts[1]['invoices'], 'date'));
        $this->assertSame([['total' => '12.000000', 'date' => '2026-01-31']], $withDrafts[1]['outstanding']);
        $this->assertSame(['2026-02-04', '2026-02-05', '2026-02-06'], array_column($withoutDrafts[1]['payments'], 'date'));
        $this->assertSame($withoutDrafts[1]['payments'], $withDrafts[1]['payments']);
    }

    public function testChartSummaryPreservesAdminAndRestrictedUserVisibility(): void
    {
        $otherUser = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('chart-summary-user-', true) . '@example.com',
        ]);
        $otherClient = $this->createChartClient(3, $otherUser);

        $this->createChartInvoice($this->usdClient, ['date' => '2026-01-10', 'amount' => 10]);
        $this->createChartInvoice($otherClient, ['date' => '2026-01-11', 'amount' => 30]);
        $this->createChartPayment($this->usdClient, ['date' => '2026-01-12', 'amount' => 10]);
        $this->createChartPayment($otherClient, [
            'date' => '2026-01-13',
            'amount' => 40,
            'currency_id' => 3,
            'user_id' => $otherUser->id,
        ]);
        $this->createChartPayment($otherClient, [
            'date' => '2026-01-14',
            'amount' => 15,
            'currency_id' => 3,
            'user_id' => $this->user->id,
        ]);
        $this->createChartExpense(['date' => '2026-01-15', 'amount' => 10]);
        $this->createChartExpense([
            'date' => '2026-01-16',
            'amount' => 50,
            'currency_id' => 3,
            'user_id' => $otherUser->id,
        ]);

        $restrictedPayload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, false),
            '2026-01-01',
            '2026-01-31',
            false
        );
        $adminPayload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertArrayNotHasKey(3, $restrictedPayload);
        $this->assertSame(['10.000000'], array_column($restrictedPayload[1]['invoices'], 'total'));
        $this->assertSame(['10.000000'], array_column($restrictedPayload[1]['payments'], 'total'));
        $this->assertSame(['10.0000000000000000'], array_column($restrictedPayload[1]['expenses'], 'total'));
        $this->assertSame(['10.000000000000', '15.000000000000'], array_column($restrictedPayload[999]['payments'], 'total'));
        $this->assertArrayHasKey(3, $adminPayload);
        $this->assertSame(['30.000000'], array_column($adminPayload[3]['invoices'], 'total'));
        $this->assertSame(['40.000000', '15.000000'], array_column($adminPayload[3]['payments'], 'total'));
        $this->assertSame(['50.0000000000000000'], array_column($adminPayload[3]['expenses'], 'total'));
    }

    public function testChartSummaryPreservesDeletionAndRelationshipFilters(): void
    {
        $deletedClient = $this->createChartClient(1, $this->user, ['is_deleted' => true]);
        $deletedVendor = Vendor::factory()->create([
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'is_deleted' => true,
        ]);

        $this->createChartInvoice($this->usdClient, ['date' => '2026-01-10', 'amount' => 10, 'balance' => 4]);
        $this->createChartInvoice($this->usdClient, ['date' => '2026-01-11', 'amount' => 20, 'is_deleted' => true]);
        $this->createChartInvoice($this->usdClient, ['date' => '2026-01-12', 'amount' => 30, 'status_id' => Invoice::STATUS_CANCELLED]);
        $this->createChartInvoice($deletedClient, ['date' => '2026-01-13', 'amount' => 40]);

        $this->createChartPayment($this->usdClient, ['date' => '2026-01-14', 'amount' => 10, 'refunded' => 1]);
        $this->createChartPayment($this->usdClient, ['date' => '2026-01-15', 'amount' => 20, 'is_deleted' => true]);
        $this->createChartPayment($this->usdClient, ['date' => '2026-01-16', 'amount' => 30, 'status_id' => Payment::STATUS_FAILED]);
        $this->createChartPayment($deletedClient, ['date' => '2026-01-17', 'amount' => 40]);

        $this->createChartExpense(['date' => '2026-01-18', 'amount' => 10, 'uses_inclusive_taxes' => true]);
        $this->createChartExpense(['date' => '2026-01-19', 'amount' => 20, 'is_deleted' => true]);
        $this->createChartExpense(['date' => '2026-01-20', 'amount' => 30, 'client_id' => $deletedClient->id]);
        $this->createChartExpense(['date' => '2026-01-21', 'amount' => 40, 'vendor_id' => $deletedVendor->id]);

        $payload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertSame([['total' => '10.000000', 'date' => '2026-01-10']], $payload[1]['invoices']);
        $this->assertSame([['total' => '4.000000', 'date' => '2026-01-31']], $payload[1]['outstanding']);
        $this->assertSame([['total' => '9.000000', 'date' => '2026-01-14']], $payload[1]['payments']);
        $this->assertSame([['total' => '10.0000000000000000', 'date' => '2026-01-18']], $payload[1]['expenses']);
    }

    public function testChartSummaryPreservesFallbackAndUnknownCurrencyHandling(): void
    {
        $fallbackSettings = ClientSettings::defaults();

        $fallbackClient = Client::factory()->create([
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'settings' => $fallbackSettings,
        ]);
        $unknownClient = $this->createChartClient(9998, $this->user);

        $this->createChartInvoice($fallbackClient, ['date' => '2026-01-10', 'amount' => 10]);
        $this->createChartInvoice($unknownClient, ['date' => '2026-01-11', 'amount' => 20]);
        $this->createChartPayment($unknownClient, ['date' => '2026-01-12', 'amount' => 50, 'currency_id' => 9998]);
        $this->createChartExpense(['date' => '2026-01-13', 'amount' => 30, 'currency_id' => null, 'uses_inclusive_taxes' => true]);
        $this->createChartExpense(['date' => '2026-01-14', 'amount' => 40, 'currency_id' => 9998, 'uses_inclusive_taxes' => true]);

        $payload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertArrayNotHasKey(9998, $payload);
        $this->assertSame([['total' => '10.000000', 'date' => '2026-01-10']], $payload[1]['invoices']);
        $this->assertSame([['total' => '30.0000000000000000', 'date' => '2026-01-13']], $payload[1]['expenses']);
        $this->assertSame(['2026-01-10', '2026-01-11'], array_column($payload[999]['invoices'], 'date'));
        $this->assertSame(['2026-01-13', '2026-01-14'], array_column($payload[999]['expenses'], 'date'));
        $this->assertSame([['total' => '50.000000000000', 'date' => '2026-01-12']], $payload[999]['payments']);
    }

    public function testMysqlAndPhpShimsPreserveSoftDeletedRowsWhenIsDeletedIsFalse(): void
    {
        $softDeletedClient = $this->createChartClient(3, $this->user);
        $invoice = $this->createChartInvoice($softDeletedClient, [
            'date' => '2026-01-10',
            'amount' => 30,
            'balance' => 12,
        ]);
        $payment = $this->createChartPayment($softDeletedClient, [
            'date' => '2026-01-11',
            'currency_id' => 3,
            'amount' => 20,
            'refunded' => 2,
        ]);
        $expense = $this->createChartExpense([
            'client_id' => $softDeletedClient->id,
            'date' => '2026-01-12',
            'currency_id' => 3,
            'amount' => 10,
            'uses_inclusive_taxes' => true,
        ]);

        DB::table('clients')->where('id', $softDeletedClient->id)->update(['deleted_at' => now()]);
        DB::table('invoices')->where('id', $invoice->id)->update(['deleted_at' => now()]);
        DB::table('payments')->where('id', $payment->id)->update(['deleted_at' => now()]);
        DB::table('expenses')->where('id', $expense->id)->update(['deleted_at' => now()]);

        $payload = $this->assertBatchedSummaryMatchesLegacy(
            new ChartService($this->chartCompany, $this->user, true),
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertSame([['total' => '30.000000', 'date' => '2026-01-10']], $payload[3]['invoices']);
        $this->assertSame([['total' => '12.000000', 'date' => '2026-01-31']], $payload[3]['outstanding']);
        $this->assertSame([['total' => '18.000000', 'date' => '2026-01-11']], $payload[3]['payments']);
        $this->assertSame([['total' => '10.0000000000000000', 'date' => '2026-01-12']], $payload[3]['expenses']);
    }

    public function testMysqlAndPhpShimsMatchDenseDecimalPermutations(): void
    {
        $clients = [
            1 => $this->usdClient,
            2 => $this->gbpClient,
            3 => $this->createChartClient(3, $this->user),
        ];
        $invoiceAmounts = ['0.010001', '1.234567', '10.000001', '-99.999999', '4.500001', '250.123456'];
        $balances = ['0.000001', '1.111111', '-2.222222', '0', '4.444444', '5.555555'];
        $paymentAmounts = ['0.010001', '1.234567', '10.000001', '99.999999', '4.500001', '250.123456'];
        $refunds = ['0', '0.000001', '1.111111', '100.000001', '0.500001', '250.123456'];
        $exchangeRates = ['1', '2', '0', '0.25', '3', '1.333333'];
        $invoiceStatuses = [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_SENT,
            Invoice::STATUS_PARTIAL,
            Invoice::STATUS_PAID,
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_REVERSED,
        ];
        $paymentStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_FAILED,
            Payment::STATUS_COMPLETED,
            Payment::STATUS_PARTIALLY_REFUNDED,
            Payment::STATUS_REFUNDED,
        ];

        foreach ($clients as $currencyId => $client) {
            foreach (range(0, 5) as $case) {
                $date = sprintf('2026-%02d-%02d', intdiv($case, 2) + 1, 10 + ($case % 2));

                $this->createChartInvoice($client, [
                    'date' => $date,
                    'due_date' => $date,
                    'amount' => $invoiceAmounts[$case],
                    'balance' => $balances[$case],
                    'paid_to_date' => 0,
                    'exchange_rate' => $exchangeRates[$case],
                    'status_id' => $invoiceStatuses[$case],
                ]);

                $this->createChartPayment($client, [
                    'date' => $date,
                    'currency_id' => $currencyId,
                    'amount' => $paymentAmounts[$case],
                    'refunded' => $refunds[$case],
                    'exchange_rate' => $exchangeRates[$case],
                    'status_id' => $paymentStatuses[$case],
                ]);

                $this->createChartExpense([
                    'client_id' => $client->id,
                    'date' => $date,
                    'currency_id' => $case === 0 ? null : $currencyId,
                    'amount' => $invoiceAmounts[$case],
                    'exchange_rate' => $exchangeRates[$case],
                    'uses_inclusive_taxes' => $case % 2 === 1,
                    'tax_amount1' => $case === 2 ? 0 : '0.123456',
                    'tax_amount2' => '0.000001',
                    'tax_amount3' => $case === 4 ? 0 : '1.000001',
                    'tax_rate1' => '5.555555',
                    'tax_rate2' => $case === 2 ? 0 : '10.125000',
                    'tax_rate3' => '0.000001',
                ]);
            }
        }

        foreach ([
            [true, false],
            [true, true],
            [false, false],
            [false, true],
        ] as [$isAdmin, $includeDrafts]) {
            $this->assertBatchedSummaryMatchesLegacy(
                new ChartService($this->chartCompany, $this->user, $isAdmin, $includeDrafts),
                '2026-01-01',
                '2026-03-31',
                $isAdmin,
                $includeDrafts
            );
        }
    }

    public function testBatchedChartSummaryUsesAConstantNumberOfQueriesAsCurrenciesGrow(): void
    {
        app('currencies');

        $chartService = new ChartService($this->chartCompany, $this->user, true);
        $connection = DB::connection();
        $connection->enableQueryLog();

        $connection->flushQueryLog();
        $chartService->chart_summary('2026-01-01', '2026-03-31');
        $twoCurrencyLegacyQueryCount = count($connection->getQueryLog());

        $connection->flushQueryLog();
        $chartService->chart_summary_batched('2026-01-01', '2026-03-31');
        $twoCurrencyBatchedQueryCount = count($connection->getQueryLog());

        foreach (range(3, 7) as $currencyId) {
            $this->createChartClient($currencyId, $this->user);
        }

        $connection->flushQueryLog();
        $chartService->chart_summary('2026-01-01', '2026-03-31');
        $sevenCurrencyLegacyQueryCount = count($connection->getQueryLog());

        $connection->flushQueryLog();
        $chartService->chart_summary_batched('2026-01-01', '2026-03-31');
        $sevenCurrencyBatchedQueryCount = count($connection->getQueryLog());

        $connection->disableQueryLog();

        $this->assertSame(14, $twoCurrencyLegacyQueryCount);
        $this->assertSame(10, $twoCurrencyBatchedQueryCount);
        $this->assertSame(34, $sevenCurrencyLegacyQueryCount);
        $this->assertSame(10, $sevenCurrencyBatchedQueryCount);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function assertBatchedSummaryMatchesLegacy(
        ChartService $chartService,
        string $startDate,
        string $endDate,
        bool $isAdmin = true,
        bool $includeDrafts = false
    ): array {
        $legacyPayload = json_encode($chartService->chart_summary($startDate, $endDate), JSON_THROW_ON_ERROR);
        $batchedPayload = json_encode($chartService->chart_summary_batched($startDate, $endDate), JSON_THROW_ON_ERROR);

        $this->assertSame($legacyPayload, $batchedPayload);

        $decodedPayload = json_decode($legacyPayload, true, 512, JSON_THROW_ON_ERROR);
        $phpShimPayload = (new ChartSummaryPhpShim(
            $this->chartCompany,
            $this->user,
            $isAdmin,
            $includeDrafts
        ))->calculate($startDate, $endDate);

        $this->assertSame($decodedPayload, $phpShimPayload);

        return $decodedPayload;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createChartClient(int $currencyId, User $user, array $attributes = []): Client
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = (string) $currencyId;

        return Client::factory()->create(array_merge([
            'company_id' => $this->chartCompany->id,
            'user_id' => $user->id,
            'settings' => $settings,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createChartInvoice(Client $client, array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'client_id' => $client->id,
            'company_id' => $this->chartCompany->id,
            'user_id' => $client->user_id,
            'amount' => 10,
            'balance' => 0,
            'paid_to_date' => 10,
            'status_id' => Invoice::STATUS_SENT,
            'exchange_rate' => 1,
            'date' => '2026-01-01',
            'due_date' => '2026-01-01',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createChartPayment(Client $client, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'client_id' => $client->id,
            'company_id' => $this->chartCompany->id,
            'user_id' => $client->user_id,
            'currency_id' => 1,
            'amount' => 10,
            'refunded' => 0,
            'exchange_rate' => 1,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-01-01',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createChartExpense(array $attributes = []): Expense
    {
        return Expense::factory()->create(array_merge([
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'currency_id' => 1,
            'amount' => 10,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'date' => '2026-01-01',
        ], $attributes));
    }

    private function createInvoices(): void
    {
        $invoices = [
            [$this->usdClient, '2026-01-10', 100, 40, Invoice::STATUS_SENT, 1],
            [$this->usdClient, '2026-01-20', 50, 0, Invoice::STATUS_PAID, 1],
            [$this->usdClient, '2026-02-10', 80, 20, Invoice::STATUS_PARTIAL, 1],
            [$this->gbpClient, '2026-01-15', 200, 100, Invoice::STATUS_SENT, 2],
            [$this->gbpClient, '2026-03-05', 120, 60, Invoice::STATUS_PARTIAL, 2],
        ];

        foreach ($invoices as [$client, $date, $amount, $balance, $statusId, $exchangeRate]) {
            Invoice::factory()->create([
                'client_id' => $client->id,
                'company_id' => $this->chartCompany->id,
                'user_id' => $this->user->id,
                'amount' => $amount,
                'balance' => $balance,
                'paid_to_date' => $amount - $balance,
                'status_id' => $statusId,
                'exchange_rate' => $exchangeRate,
                'date' => $date,
                'due_date' => $date,
            ]);
        }

        Invoice::factory()->create([
            'client_id' => $this->usdClient->id,
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'amount' => 999,
            'balance' => 999,
            'status_id' => Invoice::STATUS_DRAFT,
            'exchange_rate' => 1,
            'date' => '2026-02-15',
            'due_date' => '2026-02-15',
        ]);
    }

    private function createPayments(): void
    {
        $payments = [
            [$this->usdClient, '2026-01-25', 70, 10, 1, Payment::STATUS_PARTIALLY_REFUNDED],
            [$this->usdClient, '2026-01-25', 10, 0, 1, Payment::STATUS_COMPLETED],
            [$this->gbpClient, '2026-02-20', 200, 20, 0.5, Payment::STATUS_PARTIALLY_REFUNDED],
            [$this->usdClient, '2026-03-20', 30, 0, 1, Payment::STATUS_COMPLETED],
        ];

        foreach ($payments as [$client, $date, $amount, $refunded, $exchangeRate, $statusId]) {
            Payment::factory()->create([
                'client_id' => $client->id,
                'company_id' => $this->chartCompany->id,
                'user_id' => $this->user->id,
                'currency_id' => $client === $this->gbpClient ? 2 : 1,
                'amount' => $amount,
                'refunded' => $refunded,
                'exchange_rate' => $exchangeRate,
                'status_id' => $statusId,
                'date' => $date,
            ]);
        }

        Payment::factory()->create([
            'client_id' => $this->usdClient->id,
            'company_id' => $this->chartCompany->id,
            'user_id' => $this->user->id,
            'currency_id' => 1,
            'amount' => 999,
            'refunded' => 0,
            'exchange_rate' => 1,
            'status_id' => Payment::STATUS_FAILED,
            'date' => '2026-02-25',
        ]);
    }

    private function createExpenses(): void
    {
        $expenses = [
            ['2026-01-12', 1, 100, 1, false, 3, 2, 0, 10, 5, 0],
            ['2026-02-12', null, 50, 1, true, 0, 0, 0, 0, 0, 0],
            ['2026-02-12', 2, 200, 0.5, true, 0, 0, 0, 0, 0, 0],
            ['2026-03-12', 2, 100, 0.5, false, 5, 5, 0, 10, 0, 0],
        ];

        foreach ($expenses as [$date, $currencyId, $amount, $exchangeRate, $usesInclusiveTaxes, $taxAmount1, $taxAmount2, $taxAmount3, $taxRate1, $taxRate2, $taxRate3]) {
            Expense::factory()->create([
                'company_id' => $this->chartCompany->id,
                'user_id' => $this->user->id,
                'currency_id' => $currencyId,
                'amount' => $amount,
                'exchange_rate' => $exchangeRate,
                'uses_inclusive_taxes' => $usesInclusiveTaxes,
                'tax_amount1' => $taxAmount1,
                'tax_amount2' => $taxAmount2,
                'tax_amount3' => $taxAmount3,
                'tax_rate1' => $taxRate1,
                'tax_rate2' => $taxRate2,
                'tax_rate3' => $taxRate3,
                'date' => $date,
            ]);
        }
    }
}
