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

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Tests\MockAccountData;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\Chart\ChartService;
use App\Services\Chart\ClientPaymentAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ClientPaymentAnalyticsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $test_company;
    private Client $test_client;
    private Client $test_client_b;
    private Client $test_client_c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->country_id = '840';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $this->test_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '1';

        $this->test_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $client_settings,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);

        $this->test_client_b = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $client_settings,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);

        $this->test_client_c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $client_settings,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);
    }

    private function getService(): ChartService
    {
        return new ChartService($this->test_company, $this->user, true);
    }

    private function createPaidInvoice(
        Client $client,
        float $amount,
        string $invoice_date,
        string $payment_date,
        ?string $due_date = null
    ): void {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => $amount,
            'balance' => 0,
            'paid_to_date' => $amount,
            'status_id' => Invoice::STATUS_PAID,
            'date' => $invoice_date,
            'due_date' => $due_date ?? $invoice_date,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => $amount,
            'applied' => $amount,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $payment_date,
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
        ]);

        $payment->invoices()->attach($invoice->id, [
            'amount' => $amount,
        ]);
    }

    // ─── Green Indicators ───────────────────────────────────────────

    public function testGreenIndicators(): void
    {
        // Create a client that pays fast (avg ~5 days), never late, lots of data
        for ($i = 0; $i < 35; $i++) {
            $invoiceDate = now()->subDays(365 - ($i * 10))->format('Y-m-d');
            $dueDate = now()->subDays(365 - ($i * 10) - 30)->format('Y-m-d');
            $paymentDate = now()->subDays(365 - ($i * 10) - 5)->format('Y-m-d');
            $this->createPaidInvoice($this->test_client, 100, $invoiceDate, $paymentDate, $dueDate);
        }

        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $this->assertArrayHasKey('clients', $result);
        $this->assertNotEmpty($result['clients']);

        $client = collect($result['clients'])->firstWhere('client_id', $this->test_client->hashed_id);
        $this->assertNotNull($client);

        $this->assertEquals('green', $client['indicators']['avg_days']);
        $this->assertEquals('green', $client['indicators']['data_points']);
        $this->assertEquals('low', $client['risk_level']);
    }

    // ─── Red Indicators ─────────────────────────────────────────────

    public function testRedIndicators(): void
    {
        // Create a client that pays very late (avg ~40 days) with few data points
        for ($i = 0; $i < 5; $i++) {
            $invoiceDate = now()->subDays(200 - ($i * 30))->format('Y-m-d');
            $dueDate = now()->subDays(200 - ($i * 30) - 15)->format('Y-m-d');
            // Pays after due date (40 days after invoice)
            $paymentDate = now()->subDays(200 - ($i * 30) - 40)->format('Y-m-d');
            $this->createPaidInvoice($this->test_client, 100, $invoiceDate, $paymentDate, $dueDate);
        }

        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $client = collect($result['clients'])->firstWhere('client_id', $this->test_client->hashed_id);
        $this->assertNotNull($client);

        $this->assertEquals('red', $client['indicators']['avg_days']);
        $this->assertEquals('red', $client['indicators']['data_points']);
    }

    // ─── Risk Score Ordering ────────────────────────────────────────

    public function testRiskScoreOrdering(): void
    {
        // Good client: pays in 5 days
        for ($i = 0; $i < 10; $i++) {
            $invoiceDate = now()->subDays(200 - ($i * 15))->format('Y-m-d');
            $dueDate = now()->subDays(200 - ($i * 15) - 30)->format('Y-m-d');
            $paymentDate = now()->subDays(200 - ($i * 15) - 5)->format('Y-m-d');
            $this->createPaidInvoice($this->test_client, 100, $invoiceDate, $paymentDate, $dueDate);
        }

        // Bad client: pays in 35 days, always late
        for ($i = 0; $i < 10; $i++) {
            $invoiceDate = now()->subDays(200 - ($i * 15))->format('Y-m-d');
            $dueDate = now()->subDays(200 - ($i * 15) - 10)->format('Y-m-d');
            $paymentDate = now()->subDays(200 - ($i * 15) - 35)->format('Y-m-d');
            $this->createPaidInvoice($this->test_client_b, 100, $invoiceDate, $paymentDate, $dueDate);
        }

        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $this->assertCount(2, $result['clients']);

        // First client (highest risk) should be client_b
        $this->assertEquals($this->test_client_b->hashed_id, $result['clients'][0]['client_id']);
        $this->assertEquals($this->test_client->hashed_id, $result['clients'][1]['client_id']);

        // First should have higher risk score
        $this->assertGreaterThan($result['clients'][1]['risk_score'], $result['clients'][0]['risk_score']);
    }

    // ─── Risk Level Mapping ─────────────────────────────────────────

    public function testRiskLevelMapping(): void
    {
        $service = new ClientPaymentAnalyticsService($this->test_company);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('riskLevel');
        $method->setAccessible(true);

        $this->assertEquals('low', $method->invoke($service, 10));
        $this->assertEquals('low', $method->invoke($service, 32.99));
        $this->assertEquals('medium', $method->invoke($service, 33));
        $this->assertEquals('medium', $method->invoke($service, 50));
        $this->assertEquals('medium', $method->invoke($service, 66));
        $this->assertEquals('high', $method->invoke($service, 66.01));
        $this->assertEquals('high', $method->invoke($service, 100));
    }

    // ─── Company Summary Included ───────────────────────────────────

    public function testCompanySummaryIncluded(): void
    {
        $this->createPaidInvoice($this->test_client, 100, '2025-12-01', '2025-12-11', '2025-12-15');

        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $this->assertArrayHasKey('company_summary', $result);
        $this->assertArrayHasKey('avg_payment_days', $result['company_summary']);
        $this->assertArrayHasKey('stddev_payment_days', $result['company_summary']);
        $this->assertArrayHasKey('total_invoices', $result['company_summary']);
        $this->assertArrayHasKey('late_payment_ratio', $result['company_summary']);
    }

    // ─── Thresholds Included ────────────────────────────────────────

    public function testThresholdsIncluded(): void
    {
        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $this->assertArrayHasKey('thresholds', $result);
        $this->assertEquals(15, $result['thresholds']['avg_days']['green']);
        $this->assertEquals(30, $result['thresholds']['avg_days']['yellow']);
        $this->assertEquals(0.10, $result['thresholds']['late_rate']['green']);
        $this->assertEquals(0.25, $result['thresholds']['late_rate']['yellow']);
    }

    // ─── Empty Results ──────────────────────────────────────────────

    public function testEmptyResults(): void
    {
        $cs = $this->getService();
        $result = $cs->client_payment_analytics();

        $this->assertArrayHasKey('clients', $result);
        $this->assertEmpty($result['clients']);
        $this->assertArrayHasKey('company_summary', $result);
        $this->assertEquals(0, $result['company_summary']['total_invoices']);
    }

    // ─── API Endpoint ───────────────────────────────────────────────

    public function testClientPaymentAnalyticsEndpoint(): void
    {
        $data = [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/client_payment_analytics', $data);

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertArrayHasKey('company_summary', $json);
        $this->assertArrayHasKey('thresholds', $json);
        $this->assertArrayHasKey('clients', $json);
    }
}
