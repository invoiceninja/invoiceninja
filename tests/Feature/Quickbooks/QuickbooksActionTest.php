<?php

namespace Tests\Feature\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use ReflectionClass;
use Tests\MockAccountData;
use Tests\TestCase;

class QuickbooksActionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
        ]);
        $this->company->save();
    }

    public function testActionValidatesEntityIdAndAction(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'id' => $invoice->hashed_id,
                'action' => 'invalid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entity', 'action']);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'entity' => 'payment',
                'id' => $invoice->hashed_id,
                'action' => 'force_pull',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entity']);
    }

    public function testAdminCanDispatchSelectedEntitiesForSync(): void
    {
        Bus::fake();

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/sync', [
                'client' => true,
                'product' => false,
                'invoice' => true,
            ])
            ->assertNoContent();

        Bus::assertDispatched(QuickbooksImport::class, function (QuickbooksImport $job): bool {
            $reflection = new ReflectionClass($job);
            $syncable = $reflection->getProperty('syncable')->getValue($job);

            return $job->company_id === $this->company->id
                && $job->db === $this->company->db
                && $syncable === ['Customer', 'Invoice'];
        });
    }

    public function testNonAdminCannotDispatchSync(): void
    {
        Bus::fake();

        $this->user->companies()->updateExistingPivot($this->company->id, [
            'is_owner' => false,
            'is_admin' => false,
        ]);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/sync', [
                'client' => true,
                'product' => true,
                'invoice' => true,
            ])
            ->assertUnauthorized();

        Bus::assertNotDispatched(QuickbooksImport::class);
    }

    public function testNonAdminWithInvoiceEditPermissionQueuesActionAfterResponse(): void
    {
        Bus::fake();

        $this->user->companies()->updateExistingPivot($this->company->id, [
            'is_owner' => false,
            'is_admin' => false,
            'permissions' => json_encode(['edit_invoice']),
        ]);

        $other_user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'qb-action-editor-' . uniqid() . '@gmail.com',
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $other_user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'entity' => 'invoice',
                'id' => $invoice->hashed_id,
                'action' => 'force_pull',
            ])
            ->assertNoContent();

        Bus::assertDispatchedAfterResponse(CallQueuedClosure::class);
    }

    public function testUserWithoutInvoiceEditPermissionCannotDispatchAction(): void
    {
        $this->user->companies()->updateExistingPivot($this->company->id, [
            'is_owner' => false,
            'is_admin' => false,
            'permissions' => json_encode(['view_invoice']),
        ]);

        $other_user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'qb-action-denied-' . uniqid() . '@gmail.com',
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $other_user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'entity' => 'invoice',
                'id' => $invoice->hashed_id,
                'action' => 'force_pull',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function testForceLinkAlsoReturnsNoContentAndQueuesAfterResponse(): void
    {
        Bus::fake();

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'entity' => 'invoice',
                'id' => $invoice->hashed_id,
                'action' => 'force_link',
            ])
            ->assertNoContent();

        Bus::assertDispatchedAfterResponse(CallQueuedClosure::class);
    }

    public function testCheckRecordRunsSynchronouslyWithoutQueueingAnAction(): void
    {
        Bus::fake();

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);
        $invoice->number = '';
        $invoice->saveQuietly();

        $this->withApiHeaders()
            ->postJson('/api/v1/quickbooks/action', [
                'entity' => 'invoice',
                'id' => $invoice->hashed_id,
                'action' => 'check_record',
            ])
            ->assertOk()
            ->assertJsonPath('meta.quickbooks_check.linked', false)
            ->assertJsonPath('meta.quickbooks_check.quickbooks', null);

        Bus::assertNotDispatched(CallQueuedClosure::class);
    }

    private function withApiHeaders(): self
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }
}
