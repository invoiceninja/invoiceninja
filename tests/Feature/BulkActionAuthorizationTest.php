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

use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests that bulk actions (bulk_download, bulk_print, template) properly
 * filter out entities the authenticated user is not authorized to view.
 */
class BulkActionAuthorizationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;

    private Account $account;
    private Company $company;
    private User $adminUser;
    private User $restrictedUser;
    private Client $client;
    private string $adminToken;
    private string $restrictedToken;

    protected function setUp(): void
    {
        parent::setUp();

        if (\App\Models\Country::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

        $faker = \Faker\Factory::create();

        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);
        $this->account->num_users = 3;
        $this->account->save();

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        // Admin user - owns the entities
        $this->adminUser = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => '123',
            'email' => $faker->unique()->safeEmail(),
        ]);

        $adminCu = CompanyUserFactory::create($this->adminUser->id, $this->company->id, $this->account->id);
        $adminCu->is_owner = true;
        $adminCu->is_admin = true;
        $adminCu->save();

        $this->adminToken = \Illuminate\Support\Str::random(64);
        $adminCompanyToken = new CompanyToken();
        $adminCompanyToken->user_id = $this->adminUser->id;
        $adminCompanyToken->company_id = $this->company->id;
        $adminCompanyToken->account_id = $this->account->id;
        $adminCompanyToken->name = 'admin test token';
        $adminCompanyToken->token = $this->adminToken;
        $adminCompanyToken->is_system = true;
        $adminCompanyToken->save();

        // Restricted user - no view_invoice/view_credit/view_quote permissions
        $this->restrictedUser = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => '123',
            'email' => $faker->unique()->safeEmail(),
        ]);

        $restrictedCu = CompanyUserFactory::create($this->restrictedUser->id, $this->company->id, $this->account->id);
        $restrictedCu->is_owner = false;
        $restrictedCu->is_admin = false;
        $restrictedCu->is_locked = false;
        $restrictedCu->permissions = '[]';
        $restrictedCu->save();

        $this->restrictedToken = \Illuminate\Support\Str::random(64);
        $restrictedCompanyToken = new CompanyToken();
        $restrictedCompanyToken->user_id = $this->restrictedUser->id;
        $restrictedCompanyToken->company_id = $this->company->id;
        $restrictedCompanyToken->account_id = $this->account->id;
        $restrictedCompanyToken->name = 'restricted test token';
        $restrictedCompanyToken->token = $this->restrictedToken;
        $restrictedCompanyToken->is_system = true;
        $restrictedCompanyToken->save();

        // Client owned by admin
        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->adminUser->id,
        ]);
    }

    private function apiHeaders(string $token): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ];
    }

    // ──────────────────────────────────────────────
    // Invoice bulk_download
    // ──────────────────────────────────────────────

    public function testBulkDownloadInvoicesDeniedForRestrictedUser(): void
    {
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_download',
                'ids' => [$invoice1->hashed_id, $invoice2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    public function testBulkDownloadInvoicesAllowedForAdmin(): void
    {
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_download',
                'ids' => [$invoice1->hashed_id, $invoice2->hashed_id],
            ]);

        $response->assertStatus(200);
    }

    public function testBulkDownloadInvoicesAllowedForOwner(): void
    {
        // Invoices owned by the restricted user should be accessible
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->restrictedUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->restrictedUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_download',
                'ids' => [$invoice1->hashed_id, $invoice2->hashed_id],
            ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────
    // Invoice bulk_print
    // ──────────────────────────────────────────────

    public function testBulkPrintInvoicesDeniedForRestrictedUser(): void
    {
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_print',
                'ids' => [$invoice1->hashed_id, $invoice2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    public function testTemplateInvoicesDeniedForRestrictedUser(): void
    {
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'template',
                'template_id' => 'free_text',
                'ids' => [$invoice1->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // Credit bulk_download
    // ──────────────────────────────────────────────

    public function testBulkDownloadCreditsDeniedForRestrictedUser(): void
    {
        $credit1 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $credit2 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/credits/bulk', [
                'action' => 'bulk_download',
                'ids' => [$credit1->hashed_id, $credit2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    public function testBulkDownloadCreditsAllowedForAdmin(): void
    {
        $credit1 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $credit2 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/credits/bulk', [
                'action' => 'bulk_download',
                'ids' => [$credit1->hashed_id, $credit2->hashed_id],
            ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────
    // Credit bulk_print
    // ──────────────────────────────────────────────

    public function testBulkPrintCreditsDeniedForRestrictedUser(): void
    {
        $credit1 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $credit2 = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/credits/bulk', [
                'action' => 'bulk_print',
                'ids' => [$credit1->hashed_id, $credit2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────
    // Quote bulk_download
    // ──────────────────────────────────────────────

    public function testBulkDownloadQuotesDeniedForRestrictedUser(): void
    {
        $quote1 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $quote2 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/quotes/bulk', [
                'action' => 'bulk_download',
                'ids' => [$quote1->hashed_id, $quote2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

    public function testBulkDownloadQuotesAllowedForAdmin(): void
    {
        $quote1 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $quote2 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/quotes/bulk', [
                'action' => 'bulk_download',
                'ids' => [$quote1->hashed_id, $quote2->hashed_id],
            ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────
    // Quote bulk_print
    // ──────────────────────────────────────────────

    public function testBulkPrintQuotesDeniedForRestrictedUser(): void
    {
        $quote1 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $quote2 = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/quotes/bulk', [
                'action' => 'bulk_print',
                'ids' => [$quote1->hashed_id, $quote2->hashed_id],
            ]);

        $response->assertStatus(403);
    }

}
