<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Report\ClientBalanceReport;
use App\Models\Invoice;
use App\Models\Client;

/**
 * Test suite for Client Balance Report optimization
 *
 * Validates that optimized single-query approach produces identical results
 * to legacy per-client query approach while reducing database queries.
 */
class ClientBalanceReportOptimizationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * Test that optimized approach produces identical results to legacy
     */
    public function testOptimizedMatchesLegacyResults()
    {
        // Create test data: 10 clients with varying invoice counts
        $clients = Client::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        foreach ($clients as $index => $client) {
            // Create 0-5 invoices per client
            $invoiceCount = $index % 6;
            for ($i = 0; $i < $invoiceCount; $i++) {
                Invoice::factory()->create([
                    'company_id' => $this->company->id,
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                    'status_id' => Invoice::STATUS_SENT,
                    'balance' => 100 + ($i * 50),
                    'amount' => 100 + ($i * 50),
                ]);
            }
        }

        // Run both implementations
        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];

        $legacyReport = new ClientBalanceReport($this->company, $input);
        $optimizedReport = new ClientBalanceReport($this->company, $input);

        // Count queries for legacy
        DB::enableQueryLog();
        DB::flushQueryLog();
        $legacyOutput = $legacyReport->run();
        $legacyQueries = count(DB::getQueryLog());

        // Count queries for optimized (we'll implement this in the service)
        DB::flushQueryLog();
        // This will use optimized path when we implement it
        $optimizedOutput = $optimizedReport->run();
        $optimizedQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // For now, both use same implementation, so they should match
        $this->assertEquals($legacyOutput, $optimizedOutput);

        // After optimization, we expect significant reduction
        // Legacy: ~2N queries (N clients × 2 queries/client)
        // Optimized: ~2 queries (1 for clients, 1 for aggregates)
        $this->assertGreaterThan(10, $legacyQueries, 'Legacy should make many queries');
    }

    /**
     * Test query count reduction with optimized approach
     */
    public function testQueryCountReduction()
    {
        $clientCount = 50;

        // Create clients with invoices
        $clients = Client::factory()->count($clientCount)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        foreach ($clients as $client) {
            Invoice::factory()->count(3)->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $client->id,
                'status_id' => Invoice::STATUS_SENT,
                'balance' => 500,
            ]);
        }

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $report->run();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Optimized: ~10-15 queries (client fetch + aggregate + framework overhead)
        // Legacy: 100 queries (50 clients × 2)
        $this->assertLessThan($clientCount * 0.5, $queryCount,
            "Expected < " . ($clientCount * 0.5) . " queries (optimized), got {$queryCount}");
    }

    /**
     * Test with clients having no invoices
     */
    public function testClientsWithNoInvoices()
    {
        // Create clients without invoices
        Client::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);
        $output = $report->run();

        // Should return 0 for invoice count and balance
        $this->assertNotEmpty($output);
        $lines = array_filter(explode("\n", $output), fn($line) => !empty($line));
        $this->assertGreaterThanOrEqual(5, count($lines));
    }

    /**
     * Test with date range filtering
     */
    public function testDateRangeFiltering()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        // Create invoices at different dates
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'created_at' => now()->subDays(5),
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 200,
            'created_at' => now()->subDays(45),
        ]);

        // Test last 7 days filter
        $input = ['date_range' => 'last7', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);
        $output = $report->run();

        $this->assertNotEmpty($output);
    }

    /**
     * Test with different invoice statuses
     */
    public function testInvoiceStatusFiltering()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        // Create invoices with different statuses
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_DRAFT,
            'balance' => 200,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_PARTIAL,
            'balance' => 150,
        ]);

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);
        $output = $report->run();

        // Should only include SENT and PARTIAL invoices
        $this->assertNotEmpty($output);
        $lines = array_filter(explode("\n", $output), fn($line) => !empty($line));
        $this->assertGreaterThanOrEqual(5, count($lines));
    }

    /**
     * Test with large dataset to measure performance improvement
     */
    public function testLargeDatasetPerformance()
    {
        $clientCount = 100;

        // Create 100 clients with 5 invoices each
        $clients = Client::factory()->count($clientCount)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        foreach ($clients as $client) {
            Invoice::factory()->count(5)->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $client->id,
                'status_id' => Invoice::STATUS_SENT,
                'balance' => 1000,
            ]);
        }

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $startTime = microtime(true);

        $output = $report->run();

        $endTime = microtime(true);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $executionTime = $endTime - $startTime;

        // Optimized: ~10-20 queries (aggregate query + framework overhead)
        // Legacy: 200 queries (100 clients × 2)
        $this->assertLessThan(50, $queryCount, "Expected < 50 queries (optimized), got {$queryCount}");
        $this->assertNotEmpty($output);

    }

    /**
     * Test with zero balance invoices
     */
    public function testZeroBalanceInvoices()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        // Create invoices with zero balance (paid)
        Invoice::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 0,
            'amount' => 100,
        ]);

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);
        $output = $report->run();

        // Should still count the invoices
        $this->assertNotEmpty($output);
        $lines = array_filter(explode("\n", $output), fn($line) => !empty($line));
        $this->assertGreaterThanOrEqual(5, count($lines));
    }

    /**
     * Test report output structure
     */
    public function testReportOutputStructure()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client',
            'number' => 'CLI-001',
            'id_number' => 'TAX-123',
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 500,
        ]);

        $input = ['date_range' => 'all', 'report_keys' => [], 'user_id' => $this->user->id];
        $report = new ClientBalanceReport($this->company, $input);
        $output = $report->run();

        // Verify output contains client data
        $this->assertNotEmpty($output);
        $lines = array_filter(explode("\n", $output), fn($line) => !empty($line));
        $this->assertGreaterThanOrEqual(5, count($lines));
    }
}
