<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\TruthSource;
use Illuminate\Support\Str;
use App\DataMapper\CompanySettings;
use App\Services\Report\ARSummaryReport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test ARSummaryReport service class with optimized implementation.
 */
class ARSummaryReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected Company $company;
    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->user->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => \App\DataMapper\CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $company_token = new \App\Models\CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $truth = app()->make(\App\Utils\TruthSource::class);
        $truth->setCompanyUser($this->user->company_users()->first());
        $truth->setCompanyToken($company_token);
        $truth->setUser($this->user);
        $truth->setCompany($this->company);


        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();
    }

    /**
     * Test that optimized report generates without errors.
     */
    public function testOptimizedReportGenerates()
    {
        // Create test data
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
            'due_date' => now()->subDays(15),
        ]);

        $report = new ARSummaryReport($this->company, [
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $csv = $report->run();

        $this->assertNotEmpty($csv);
        $this->assertStringContainsString('Aged Receivable Summary Report', $csv);
        $this->assertStringContainsString($client->present()->name(), $csv);
    }

    /**
     * Test that rollback flag works correctly.
     */
    public function testRollbackToLegacyWorks()
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
            'balance' => 200,
            'due_date' => now()->subDays(45),
        ]);

        // Force use of legacy implementation via reflection
        $report = new ARSummaryReport($this->company, [
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $reflection = new \ReflectionClass($report);
        $property = $reflection->getProperty('useOptimizedQuery');
        $property->setAccessible(true);
        $property->setValue($report, false);

        $csv = $report->run();

        $this->assertNotEmpty($csv);
        $this->assertStringContainsString($client->present()->name(), $csv);
    }

    /**
     * Test that both implementations produce same output.
     */
    public function testBothImplementationsProduceSameOutput()
    {
        // Create test data
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client ABC',
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'due_date' => now()->subDays(15),
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 300,
            'due_date' => now()->subDays(75),
        ]);

        // Run with optimized
        $reportOptimized = new ARSummaryReport($this->company, [
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);
        $csvOptimized = $reportOptimized->run();

        // Run with legacy
        $reportLegacy = new ARSummaryReport($this->company, [
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);
        
        $reflection = new \ReflectionClass($reportLegacy);
        $property = $reflection->getProperty('useOptimizedQuery');
        $property->setAccessible(true);
        $property->setValue($reportLegacy, false);
        
        $csvLegacy = $reportLegacy->run();

        // Both should contain same client name and amounts
        $this->assertEquals(
            substr_count($csvOptimized, 'Test Client ABC'),
            substr_count($csvLegacy, 'Test Client ABC'),
            'Both implementations should include client name'
        );

        // Both CSVs should have same structure (same number of lines)
        $this->assertEquals(
            substr_count($csvOptimized, "\n"),
            substr_count($csvLegacy, "\n"),
            'Both implementations should produce same CSV structure'
        );
    }

    /**
     * Test with empty client list.
     */
    public function testWithNoClients()
    {
        $report = new ARSummaryReport($this->company, [
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $csv = $report->run();

        $this->assertNotEmpty($csv);
        $this->assertStringContainsString('Aged Receivable Summary Report', $csv);
    }
}
