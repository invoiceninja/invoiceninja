<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Account;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Company;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test AR Summary Report optimization strategy.
 * 
 * This test compares the current N+1 query approach (6 queries per client)
 * against an optimized single-query approach using CASE statements.
 */
class ARSummaryReportOptimizationTest extends TestCase
{
    use DatabaseTransactions, MakesHash;

    protected Company $company;
    protected User $user;
    protected array $testClients = [];
    protected array $testInvoices = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $account = Account::factory()->create();
        
        $this->company = Company::factory()->create([
            'account_id' => $account->id,
        ]);
        
        $this->user = User::factory()->create([
            'account_id' => $account->id,
        ]);
    }

    /**
     * Create test data: clients with invoices in various aging buckets.
     */
    private function createTestData(int $clientCount = 10): void
    {
        $now = now()->startOfDay();

        for ($i = 0; $i < $clientCount; $i++) {
            $client = Client::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'name' => "Test Client {$i}",
                'number' => "CLI-{$i}",
            ]);

            $this->testClients[] = $client;

            // Create invoices in different aging buckets
            $invoiceScenarios = [
                // Current (due in future or no due date)
                ['balance' => 100, 'due_date' => $now->copy()->addDays(10), 'status' => Invoice::STATUS_SENT],
                ['balance' => 50, 'due_date' => null, 'status' => Invoice::STATUS_SENT],
                
                // 0-30 days overdue
                ['balance' => 200, 'due_date' => $now->copy()->subDays(15), 'status' => Invoice::STATUS_SENT],
                ['balance' => 150, 'due_date' => $now->copy()->subDays(25), 'status' => Invoice::STATUS_PARTIAL],
                
                // 31-60 days overdue
                ['balance' => 300, 'due_date' => $now->copy()->subDays(45), 'status' => Invoice::STATUS_SENT],
                
                // 61-90 days overdue
                ['balance' => 400, 'due_date' => $now->copy()->subDays(75), 'status' => Invoice::STATUS_SENT],
                
                // 91-120 days overdue
                ['balance' => 500, 'due_date' => $now->copy()->subDays(105), 'status' => Invoice::STATUS_SENT],
                
                // 120+ days overdue
                ['balance' => 600, 'due_date' => $now->copy()->subDays(150), 'status' => Invoice::STATUS_SENT],
                ['balance' => 700, 'due_date' => $now->copy()->subDays(365), 'status' => Invoice::STATUS_PARTIAL],
            ];

            foreach ($invoiceScenarios as $scenario) {
                $invoice = Invoice::factory()->create([
                    'company_id' => $this->company->id,
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                    'status_id' => $scenario['status'],
                    'balance' => $scenario['balance'],
                    'amount' => $scenario['balance'],
                    'due_date' => $scenario['due_date'],
                    'is_deleted' => false,
                ]);

                $this->testInvoices[] = $invoice;
            }
        }
    }

    /**
     * Current implementation: N+1 query approach (6 queries per client).
     */
    private function getCurrentImplementationResults(Client $client): array
    {
        $now = now()->startOfDay();

        // Current invoices
        $current = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where(function ($query) use ($now) {
                $query->where('due_date', '>', $now)
                    ->orWhereNull('due_date');
            })
            ->sum('balance');

        // 0-30 days
        $age_30 = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$now->copy()->subDays(30), $now])
            ->sum('balance');

        // 31-60 days
        $age_60 = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)])
            ->sum('balance');

        // 61-90 days
        $age_90 = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$now->copy()->subDays(90), $now->copy()->subDays(61)])
            ->sum('balance');

        // 91-120 days
        $age_120 = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$now->copy()->subDays(120), $now->copy()->subDays(91)])
            ->sum('balance');

        // 120+ days
        $age_120_plus = Invoice::withTrashed()
            ->where('client_id', $client->id)
            ->where('company_id', $client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$now->copy()->subYears(20), $now->copy()->subDays(121)])
            ->sum('balance');

        return [
            'current' => $current,
            'age_30' => $age_30,
            'age_60' => $age_60,
            'age_90' => $age_90,
            'age_120' => $age_120,
            'age_120_plus' => $age_120_plus,
            'total' => $current + $age_30 + $age_60 + $age_90 + $age_120 + $age_120_plus,
        ];
    }

    /**
     * Optimized implementation: Single query with CASE statements.
     */
    private function getOptimizedImplementationResults(array $clientIds)
    {
        $now = now()->startOfDay();
        $nowStr = $now->toDateString();
        $date_30 = $now->copy()->subDays(30)->toDateString();
        $date_31 = $now->copy()->subDays(31)->toDateString();
        $date_60 = $now->copy()->subDays(60)->toDateString();
        $date_61 = $now->copy()->subDays(61)->toDateString();
        $date_90 = $now->copy()->subDays(90)->toDateString();
        $date_91 = $now->copy()->subDays(91)->toDateString();
        $date_120 = $now->copy()->subDays(120)->toDateString();
        $date_121 = $now->copy()->subDays(121)->toDateString();
        $pastDate = $now->copy()->subYears(20)->toDateString();

        $results = DB::table('invoices')
            ->selectRaw('
                client_id,
                SUM(CASE 
                    WHEN (due_date > ? OR due_date IS NULL) 
                    THEN balance 
                    ELSE 0 
                END) as current,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_30,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_60,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_90,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_120,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_120_plus,
                SUM(balance) as total
            ', [
                $nowStr,                // current > now
                $date_30, $nowStr,     // 0-30 days
                $date_60, $date_31,    // 31-60 days
                $date_90, $date_61,    // 61-90 days
                $date_120, $date_91,   // 91-120 days
                $pastDate, $date_121,  // 120+ days
            ])
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        return $results;
    }

    /**
     * Test data quality: verify optimized query produces same results as current.
     */
    public function testDataQualityOptimizedMatchesCurrent()
    {
        $this->createTestData(5);

        $clientIds = collect($this->testClients)->pluck('id')->toArray();
        $optimizedResults = $this->getOptimizedImplementationResults($clientIds);

        foreach ($this->testClients as $client) {
            $currentResults = $this->getCurrentImplementationResults($client);
            $optimizedResult = $optimizedResults->get($client->id);

            $this->assertNotNull($optimizedResult, "Client {$client->id} not found in optimized results");

            // Compare each aging bucket
            $this->assertEquals(
                $currentResults['current'],
                $optimizedResult->current,
                "Current balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['age_30'],
                $optimizedResult->age_30,
                "0-30 days balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['age_60'],
                $optimizedResult->age_60,
                "31-60 days balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['age_90'],
                $optimizedResult->age_90,
                "61-90 days balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['age_120'],
                $optimizedResult->age_120,
                "91-120 days balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['age_120_plus'],
                $optimizedResult->age_120_plus,
                "120+ days balance mismatch for client {$client->name}"
            );

            $this->assertEquals(
                $currentResults['total'],
                $optimizedResult->total,
                "Total balance mismatch for client {$client->name}"
            );
        }
    }

    /**
     * Test edge case: client with no invoices.
     */
    public function testClientWithNoInvoices()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $currentResults = $this->getCurrentImplementationResults($client);
        $optimizedResults = $this->getOptimizedImplementationResults([$client->id]);

        // Current implementation returns 0 for all buckets
        $this->assertEquals(0, $currentResults['current']);
        $this->assertEquals(0, $currentResults['total']);

        // Optimized should either not return the client or return zeros
        $optimizedResult = $optimizedResults->get($client->id);
        if ($optimizedResult) {
            $this->assertEquals(0, $optimizedResult->total);
        }
    }

    /**
     * Test edge case: invoices with status other than SENT/PARTIAL should be excluded.
     */
    public function testExcludesNonSentPartialInvoices()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        // Create invoices with various statuses
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_DRAFT, // Should be excluded
            'balance' => 200,
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_PAID, // Should be excluded
            'balance' => 0,
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        $currentResults = $this->getCurrentImplementationResults($client);
        $optimizedResults = $this->getOptimizedImplementationResults([$client->id]);
        $optimizedResult = $optimizedResults->get($client->id);

        // Should only count the SENT invoice
        $this->assertEquals(100, $currentResults['age_30']);
        $this->assertEquals(100, $optimizedResult->age_30);
        $this->assertEquals(100, $currentResults['total']);
        $this->assertEquals(100, $optimizedResult->total);
    }

    /**
     * Test edge case: deleted invoices should be excluded.
     */
    public function testExcludesDeletedInvoices()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 200,
            'due_date' => now()->subDays(10),
            'is_deleted' => true, // Should be excluded
        ]);

        $currentResults = $this->getCurrentImplementationResults($client);
        $optimizedResults = $this->getOptimizedImplementationResults([$client->id]);
        $optimizedResult = $optimizedResults->get($client->id);

        $this->assertEquals(100, $currentResults['age_30']);
        $this->assertEquals(100, $optimizedResult->age_30);
    }

    /**
     * Test edge case: invoices with zero balance should be excluded.
     */
    public function testExcludesZeroBalanceInvoices()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 0, // Should be excluded
            'due_date' => now()->subDays(10),
            'is_deleted' => false,
        ]);

        $currentResults = $this->getCurrentImplementationResults($client);
        $optimizedResults = $this->getOptimizedImplementationResults([$client->id]);
        $optimizedResult = $optimizedResults->get($client->id);

        $this->assertEquals(100, $currentResults['age_30']);
        $this->assertEquals(100, $optimizedResult->age_30);
        $this->assertEquals(100, $currentResults['total']);
        $this->assertEquals(100, $optimizedResult->total);
    }

    /**
     * Performance test: compare query count between implementations.
     */
    public function testPerformanceQueryCount()
    {
        $this->createTestData(10);
        $clientIds = collect($this->testClients)->pluck('id')->toArray();

        // Count queries for current implementation
        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach ($this->testClients as $client) {
            $this->getCurrentImplementationResults($client);
        }
        $currentQueries = DB::getQueryLog();
        // Only count invoice queries (not time zone or other queries)
        $currentQueryCount = count(array_filter($currentQueries, function($query) {
            return strpos($query['query'], 'from `invoices`') !== false;
        }));
        DB::disableQueryLog();

        // Count queries for optimized implementation
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getOptimizedImplementationResults($clientIds);
        $optimizedQueries = DB::getQueryLog();
        // Only count invoice queries
        $optimizedQueryCount = count(array_filter($optimizedQueries, function($query) {
            return strpos($query['query'], 'from `invoices`') !== false;
        }));
        DB::disableQueryLog();

        // Current should be 6 queries per client (6 * 10 = 60)
        $this->assertEquals(60, $currentQueryCount, 'Current implementation should execute 6 queries per client');

        // Optimized should be 1 query total
        $this->assertEquals(1, $optimizedQueryCount, 'Optimized implementation should execute 1 query total');

        $improvement = $currentQueryCount / $optimizedQueryCount;
        $this->assertGreaterThan(50, $improvement, 'Optimized should be at least 50x better');
    }

    /**
     * Performance test: measure execution time difference.
     */
    public function testPerformanceExecutionTime()
    {
        $this->createTestData(50);
        $clientIds = collect($this->testClients)->pluck('id')->toArray();

        // Measure current implementation time
        $currentStart = microtime(true);
        foreach ($this->testClients as $client) {
            $this->getCurrentImplementationResults($client);
        }
        $currentTime = microtime(true) - $currentStart;

        // Measure optimized implementation time
        $optimizedStart = microtime(true);
        $this->getOptimizedImplementationResults($clientIds);
        $optimizedTime = microtime(true) - $optimizedStart;

        // Optimized should be significantly faster
        $this->assertLessThan($currentTime, $optimizedTime, 'Optimized should be faster');
        
        $speedup = $currentTime / $optimizedTime;
        // dump([
        //     'clients' => 50,
        //     'current_time' => round($currentTime, 4) . 's',
        //     'optimized_time' => round($optimizedTime, 4) . 's',
        //     'speedup' => round($speedup, 2) . 'x',
        // ]);
    }

    /**
     * Test aging bucket boundaries are correct.
     */
    public function testAgingBucketBoundaries()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $now = now()->startOfDay();

        // Create invoices exactly on bucket boundaries
        $boundaries = [
            ['days' => 0, 'balance' => 100, 'expected_bucket' => 'age_30'],   // Today = 0-30
            ['days' => 30, 'balance' => 200, 'expected_bucket' => 'age_30'],  // Exactly 30 days
            ['days' => 31, 'balance' => 300, 'expected_bucket' => 'age_60'],  // Exactly 31 days
            ['days' => 60, 'balance' => 400, 'expected_bucket' => 'age_60'],  // Exactly 60 days
            ['days' => 61, 'balance' => 500, 'expected_bucket' => 'age_90'],  // Exactly 61 days
            ['days' => 90, 'balance' => 600, 'expected_bucket' => 'age_90'],  // Exactly 90 days
            ['days' => 91, 'balance' => 700, 'expected_bucket' => 'age_120'], // Exactly 91 days
            ['days' => 120, 'balance' => 800, 'expected_bucket' => 'age_120'], // Exactly 120 days
            ['days' => 121, 'balance' => 900, 'expected_bucket' => 'age_120_plus'], // Exactly 121 days
        ];

        foreach ($boundaries as $boundary) {
            Invoice::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $client->id,
                'status_id' => Invoice::STATUS_SENT,
                'balance' => $boundary['balance'],
                'due_date' => $now->copy()->subDays($boundary['days']),
                'is_deleted' => false,
            ]);
        }

        $currentResults = $this->getCurrentImplementationResults($client);
        $optimizedResults = $this->getOptimizedImplementationResults([$client->id]);
        $optimizedResult = $optimizedResults->get($client->id);

        // Both implementations should produce same bucket totals
        foreach (['age_30', 'age_60', 'age_90', 'age_120', 'age_120_plus'] as $bucket) {
            $this->assertEquals(
                $currentResults[$bucket],
                $optimizedResult->$bucket,
                "Bucket boundary mismatch for {$bucket}"
            );
        }
    }
}
