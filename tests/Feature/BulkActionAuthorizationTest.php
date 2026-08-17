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

use Mockery;
use App\Exceptions\BatchPdfException;
use App\Factory\CompanyUserFactory;
use App\Helpers\Cache\Atomic;
use App\Jobs\Entity\ZipEntity;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PdfMaker\BatchPdfService;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Tests that bulk actions (bulk_download, bulk_print, template) properly
 * filter out invoices, credits, quotes, and purchase orders the authenticated
 * user is not authorized to view.
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
    private Vendor $vendor;
    private string $adminToken;
    private string $restrictedToken;

    protected function setUp(): void
    {
        parent::setUp();

        if (\App\Models\Country::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

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
            'email' => uniqid('testuser') . '@gmail.com',
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
            'email' => uniqid('testuser') . '@gmail.com',
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

        $this->vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->adminUser->id,
            'currency_id' => 1,
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
        Bus::fake([ZipEntity::class]);

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

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $job->entity_ids->all() === [$invoice1->id, $invoice2->id]
                && $job->company->is($this->company)
                && $job->user->is($this->adminUser)
                && $job->entity_class === Invoice::class,
        );
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

    public function testBulkPrintInvoicesUsesBatchPdfService(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $this->mockBatchPdfService(Invoice::class, [$invoice->id], 'invoice-pdf');

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_print',
                'ids' => [$invoice->hashed_id],
            ]);

        $this->assertBatchPdfDownload($response, 'invoice-pdf');
    }

    public function testBulkPrintInvoiceFailureReleasesAtomicLock(): void
    {
        config(['cache.default' => 'array']);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);
        $lock_key = '127.0.0.1|bulk_print|' . $this->company->company_key;
        $service = Mockery::mock(BatchPdfService::class);

        $service->shouldReceive('render')
            ->once()
            ->with(Invoice::class, [$invoice->id], $this->company->db)
            ->andReturnUsing(function () use ($lock_key): never {
                $this->assertNotNull(Atomic::get($lock_key));

                throw new BatchPdfException('Unable to generate the batch PDF.');
            });

        $this->app->instance(BatchPdfService::class, $service);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/invoices/bulk', [
                'action' => 'bulk_print',
                'ids' => [$invoice->hashed_id],
            ]);

        $response
            ->assertStatus(500)
            ->assertJsonPath('message', 'Unable to generate the batch PDF.');
        $this->assertNull(Atomic::get($lock_key));
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
        Bus::fake([ZipEntity::class]);

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

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $this->entityIdsFromJob($job) === [$credit1->id, $credit2->id]
                && $job->company->is($this->company)
                && $job->user->is($this->adminUser)
                && $job->entity_class === Credit::class,
        );
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

    public function testBulkPrintCreditsUsesBatchPdfService(): void
    {
        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->mockBatchPdfService(Credit::class, [$credit->id], 'credit-pdf');

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/credits/bulk', [
                'action' => 'bulk_print',
                'ids' => [$credit->hashed_id],
            ]);

        $this->assertBatchPdfDownload($response, 'credit-pdf');
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
        Bus::fake([ZipEntity::class]);

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

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $this->entityIdsFromJob($job) === [$quote1->id, $quote2->id]
                && $job->company->is($this->company)
                && $job->user->is($this->adminUser)
                && $job->entity_class === Quote::class,
        );
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

    public function testBulkPrintQuotesUsesBatchPdfService(): void
    {
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->adminUser->id,
            'status_id' => Quote::STATUS_SENT,
        ]);

        $this->mockBatchPdfService(Quote::class, [$quote->id], 'quote-pdf');

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/quotes/bulk', [
                'action' => 'bulk_print',
                'ids' => [$quote->hashed_id],
            ]);

        $this->assertBatchPdfDownload($response, 'quote-pdf');
    }

    // ──────────────────────────────────────────────
    // Purchase order bulk_download
    // ──────────────────────────────────────────────

    public function testBulkDownloadPurchaseOrdersOnlyQueuesAuthorizedSelection(): void
    {
        Bus::fake([ZipEntity::class]);

        $authorized_purchase_order = $this->createPurchaseOrder($this->restrictedUser);
        $unauthorized_purchase_order = $this->createPurchaseOrder($this->adminUser);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/purchase_orders/bulk', [
                'action' => 'bulk_download',
                'ids' => [
                    $authorized_purchase_order->hashed_id,
                    $unauthorized_purchase_order->hashed_id,
                ],
            ]);

        $response->assertOk();

        Bus::assertDispatched(ZipEntity::class, 1);
        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $this->entityIdsFromJob($job) === [$authorized_purchase_order->id]
                && $job->entity_class === PurchaseOrder::class,
        );
    }

    public function testBulkDownloadPurchaseOrdersDoesNotDispatchWhenNoneAreAuthorized(): void
    {
        Bus::fake([ZipEntity::class]);

        $purchase_order_1 = $this->createPurchaseOrder($this->adminUser);
        $purchase_order_2 = $this->createPurchaseOrder($this->adminUser);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/purchase_orders/bulk', [
                'action' => 'bulk_download',
                'ids' => [
                    $purchase_order_1->hashed_id,
                    $purchase_order_2->hashed_id,
                ],
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', ctrans('texts.access_denied'));

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testBulkDownloadPurchaseOrdersQueuesCompleteAuthorizedSelection(): void
    {
        Bus::fake([ZipEntity::class]);

        $purchase_order_1 = $this->createPurchaseOrder($this->adminUser);
        $purchase_order_2 = $this->createPurchaseOrder($this->restrictedUser);
        $expected_ids = [$purchase_order_1->id, $purchase_order_2->id];
        sort($expected_ids);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/purchase_orders/bulk', [
                'action' => 'bulk_download',
                'ids' => [
                    $purchase_order_1->hashed_id,
                    $purchase_order_2->hashed_id,
                ],
            ]);

        $response->assertOk();

        Bus::assertDispatched(ZipEntity::class, 1);
        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $this->entityIdsFromJob($job) === $expected_ids
                && $job->entity_class === PurchaseOrder::class,
        );
    }

    public function testBulkDocumentsAcceptsAndTransformsSameCompanyDocumentIds(): void
    {
        Bus::fake([ZipEntity::class]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->adminUser->id,
            'name' => 'supporting-document.pdf',
            'type' => 'pdf',
        ]);
        $this->client->documents()->save($document);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/documents/bulk', [
                'action' => 'download',
                'ids' => [$document->hashed_id],
            ]);

        $response->assertOk();

        Bus::assertDispatched(
            ZipEntity::class,
            fn (ZipEntity $job): bool => $this->entityIdsFromJob($job) === [$document->id]
                && $job->company->is($this->company)
                && $job->user->is($this->adminUser)
                && $job->entity_class === Document::class,
        );
    }

    public function testBulkDocumentsRejectsUnsupportedAction(): void
    {
        Bus::fake([ZipEntity::class]);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->adminUser->id,
            'name' => 'supporting-document.pdf',
            'type' => 'pdf',
        ]);
        $this->client->documents()->save($document);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/documents/bulk', [
                'action' => 'unsupported_action',
                'ids' => [$document->hashed_id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testBulkDocumentsRejectsDocumentFromAnotherCompany(): void
    {
        Bus::fake([ZipEntity::class]);

        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $document = Document::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $this->adminUser->id,
            'name' => 'other-company-document.pdf',
            'type' => 'pdf',
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/documents/bulk', [
                'action' => 'download',
                'ids' => [$document->hashed_id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testBulkDocumentsRejectsNonexistentDocument(): void
    {
        Bus::fake([ZipEntity::class]);

        $missing_document_id = ((int) Document::withTrashed()->max('id')) + 1;

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/documents/bulk', [
                'action' => 'download',
                'ids' => [$this->encodePrimaryKey($missing_document_id)],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testBulkDocumentsRejectsEntireSelectionWhenOneDocumentBelongsToAnotherCompany(): void
    {
        Bus::fake([ZipEntity::class]);

        $same_company_document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->adminUser->id,
            'name' => 'same-company-document.pdf',
            'type' => 'pdf',
        ]);
        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $other_company_document = Document::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $this->adminUser->id,
            'name' => 'other-company-document.pdf',
            'type' => 'pdf',
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->adminToken))
            ->postJson('/api/v1/documents/bulk', [
                'action' => 'download',
                'ids' => [
                    $same_company_document->hashed_id,
                    $other_company_document->hashed_id,
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        Bus::assertNotDispatched(ZipEntity::class);
    }

    public function testBulkPrintPurchaseOrdersUsesOnlyAuthorizedSelection(): void
    {
        $authorized_purchase_order = $this->createPurchaseOrder($this->restrictedUser);
        $unauthorized_purchase_order = $this->createPurchaseOrder($this->adminUser);

        $this->mockBatchPdfService(
            PurchaseOrder::class,
            [$authorized_purchase_order->id],
            'purchase-order-pdf',
        );

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/purchase_orders/bulk', [
                'action' => 'bulk_print',
                'ids' => [
                    $authorized_purchase_order->hashed_id,
                    $unauthorized_purchase_order->hashed_id,
                ],
            ]);

        $this->assertBatchPdfDownload($response, 'purchase-order-pdf');
    }

    public function testBulkPrintPurchaseOrdersRejectsCompletelyUnauthorizedSelection(): void
    {
        $purchase_order_1 = $this->createPurchaseOrder($this->adminUser);
        $purchase_order_2 = $this->createPurchaseOrder($this->adminUser);
        $service = Mockery::mock(BatchPdfService::class);
        $service->shouldNotReceive('render');
        $this->app->instance(BatchPdfService::class, $service);

        $response = $this->withHeaders($this->apiHeaders($this->restrictedToken))
            ->postJson('/api/v1/purchase_orders/bulk', [
                'action' => 'bulk_print',
                'ids' => [
                    $purchase_order_1->hashed_id,
                    $purchase_order_2->hashed_id,
                ],
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', ctrans('texts.access_denied'));
    }

    /**
     * @param class-string $entity_class
     * @param array<int, int> $entity_ids
     */
    private function mockBatchPdfService(string $entity_class, array $entity_ids, string $pdf): void
    {
        $service = Mockery::mock(BatchPdfService::class);

        $service->shouldReceive('render')
            ->once()
            ->with($entity_class, $entity_ids, $this->company->db)
            ->andReturn($pdf);

        $this->app->instance(BatchPdfService::class, $service);
    }

    private function assertBatchPdfDownload(\Illuminate\Testing\TestResponse $response, string $pdf): void
    {
        $response->assertOk();
        $response->assertStreamed();
        $response->assertDownload('print.pdf');
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($pdf, $response->streamedContent());
    }

    private function createPurchaseOrder(User $owner): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'user_id' => $owner->id,
            'status_id' => PurchaseOrder::STATUS_SENT,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function entityIdsFromJob(ZipEntity $job): array
    {
        /** @var array<int, int> $ids */
        $ids = collect($job->entity_ids)->all();
        sort($ids);

        return $ids;
    }

}
