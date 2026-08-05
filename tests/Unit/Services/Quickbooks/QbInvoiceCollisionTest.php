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

namespace Tests\Unit\Services\Quickbooks;

use App\DataMapper\InvoiceSync;
use App\DataMapper\QuickbooksSettings;
use App\Enum\InvoiceQbStatus;
use App\Enum\SyncDirection;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\SdkWrapper;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use ReflectionClass;
use Tests\MockAccountData;
use Tests\TestCase;

class QbInvoiceCollisionTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeQbInvoice(): QbInvoice
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);
        $this->company->save();
        $this->app['config']->set('services.quickbooks.client_id', null);

        $service = new QuickbooksService($this->company);

        return new QbInvoice($service);
    }

    private function invoke(QbInvoice $qb_invoice, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($qb_invoice);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($qb_invoice, $args);
    }

    private function setSdkWrapper(QbInvoice $qb_invoice, SdkWrapper $sdk): void
    {
        $service_reflection = new ReflectionClass($qb_invoice->service);
        $sdk_wrapper = $service_reflection->getProperty('sdk_wrapper');
        $sdk_wrapper->setAccessible(true);
        $sdk_wrapper->setValue($qb_invoice->service, $sdk);
    }

    private function setQbInvoiceProperty(QbInvoice $qb_invoice, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($qb_invoice);
        $reflected_property = $reflection->getProperty($property);
        $reflected_property->setAccessible(true);
        $reflected_property->setValue($qb_invoice, $value);
    }

    public function testFlagNumberCollisionSetsLinkableWhenAmountsMatch(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-COLLIDE-1',
            'amount' => 250.00,
            'balance' => 250.00,
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_record = (object) [
            'Id' => '999',
            'DocNumber' => 'INV-COLLIDE-1',
            'TotalAmt' => 250.00,
            'SyncToken' => '3',
        ];

        $this->invoke($qb_invoice, 'flagNumberCollision', [$invoice, $qb_record]);

        $invoice = $invoice->fresh();
        $this->assertSame('', $invoice->sync->qb_id);
        $this->assertSame(InvoiceQbStatus::Linkable->value, $invoice->sync->qb_status);
        $this->assertSame(
            'QuickBooks invoice #INV-COLLIDE-1 (Id 999) has the same number and total (250.00). Verify it is the same invoice before linking.',
            $invoice->sync->qb_status_message
        );
    }

    public function testFlagNumberCollisionSetsAmountMismatchWhenTotalsDiffer(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-COLLIDE-2',
            'amount' => 200.00,
            'balance' => 200.00,
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_record = (object) [
            'Id' => '888',
            'DocNumber' => 'INV-COLLIDE-2',
            'TotalAmt' => 250.00,
            'SyncToken' => '1',
        ];

        $this->invoke($qb_invoice, 'flagNumberCollision', [$invoice, $qb_record]);

        $invoice = $invoice->fresh();
        $this->assertSame(InvoiceQbStatus::DataMismatch->value, $invoice->sync->qb_status);
        $this->assertStringStartsWith('Invoice number #INV-COLLIDE-2 is already used', $invoice->sync->qb_status_message);
        $this->assertStringContainsString('250', $invoice->sync->qb_status_message);
        $this->assertStringContainsString('200', $invoice->sync->qb_status_message);
    }

    public function testFlagNumberCollisionIsNoOpWhenQbIdAlreadySet(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-LOCKED',
            'amount' => 100.00,
            'balance' => 100.00,
        ]);

        $sync = new InvoiceSync();
        $sync->markSynced('LOCKED-1', '1');
        $invoice->sync = $sync;
        $invoice->saveQuietly();

        $qb_invoice = $this->makeQbInvoice();
        $qb_record = (object) [
            'Id' => 'OTHER',
            'DocNumber' => 'INV-LOCKED',
            'TotalAmt' => 100.00,
        ];

        $this->invoke($qb_invoice, 'flagNumberCollision', [$invoice, $qb_record]);

        $invoice = $invoice->fresh();
        $this->assertSame('LOCKED-1', $invoice->sync->qb_id);
        $this->assertSame(InvoiceQbStatus::Synced->value, $invoice->sync->qb_status);
        $this->assertSame('', $invoice->sync->qb_status_message);
    }

    public function testHandlePullNumberCollisionSkipsCreateAndFlagsExisting(): void
    {
        $existing = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-PULL-1',
            'amount' => 75.50,
            'balance' => 75.50,
        ]);

        $before_count = Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('number', 'INV-PULL-1')
            ->count();

        $qb_invoice = $this->makeQbInvoice();
        $qb_record = (object) [
            'Id' => '555',
            'DocNumber' => 'INV-PULL-1',
            'TotalAmt' => 75.50,
        ];

        $skipped = $this->invoke($qb_invoice, 'handlePullNumberCollision', [
            [
                'id' => '555',
                'number' => 'INV-PULL-1',
                'client_id' => $this->client->id,
            ],
            $qb_record,
        ]);

        $this->assertTrue($skipped);

        $after_count = Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('number', 'INV-PULL-1')
            ->count();

        $this->assertSame($before_count, $after_count);
        $this->assertStringNotContainsString('_555', (string) $existing->fresh()->number);

        $existing = $existing->fresh();
        $this->assertSame(InvoiceQbStatus::Linkable->value, $existing->sync->qb_status);
        $this->assertSame('', $existing->sync->qb_id);
    }

    public function testHandlePullNumberCollisionIgnoresLinkedOwner(): void
    {
        $existing = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-OWNED',
            'amount' => 10.00,
            'balance' => 10.00,
        ]);

        $sync = new InvoiceSync();
        $sync->markSynced('OWNED-1');
        $existing->sync = $sync;
        $existing->saveQuietly();

        $qb_invoice = $this->makeQbInvoice();
        $skipped = $this->invoke($qb_invoice, 'handlePullNumberCollision', [
            [
                'id' => 'DIFFERENT',
                'number' => 'INV-OWNED',
                'client_id' => $this->client->id,
            ],
            (object) ['Id' => 'DIFFERENT', 'DocNumber' => 'INV-OWNED', 'TotalAmt' => 10.00],
        ]);

        $this->assertTrue($skipped);
        $existing = $existing->fresh();
        $this->assertSame('OWNED-1', $existing->sync->qb_id);
        $this->assertSame(InvoiceQbStatus::Synced->value, $existing->sync->qb_status);
    }

    public function testCheckBackfillsLinkedInvoiceStatusFromQuickbooksPayload(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-CHECK-1',
            'amount' => 125.00,
            'balance' => 125.00,
            'sync' => new InvoiceSync(
                qb_id: 'QB-CHECK-1',
                qb_status_message: 'QuickBooks rejected DisplayName.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_invoice->service->settings->invoice->direction = SyncDirection::PULL;
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-CHECK-1')
            ->andReturn((object) [
                'Id' => 'QB-CHECK-1',
                'DocNumber' => 'INV-CHECK-1',
                'TotalAmt' => 125.00,
                'SyncToken' => '4',
            ]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);

        $this->assertSame(InvoiceQbStatus::Synced->value, $checked_invoice->sync->qb_status);
        $this->assertSame('4', $checked_invoice->sync->qb_sync_token);
        $this->assertSame('QuickBooks rejected DisplayName.', $checked_invoice->sync->qb_status_message);

        $context = $qb_invoice->checkContext();
        $this->assertSame('synced', $context['outcome']);
        $this->assertTrue($context['linked']);
        $this->assertSame('QB-CHECK-1', $context['quickbooks']['id']);
        $this->assertTrue($context['comparison']['number']['matches']);
        $this->assertTrue($context['comparison']['total']['matches']);
        $this->assertSame([], $context['recommended_actions']);
    }

    public function testWebhookSyncUpdatesExistingInvoiceSyncToken(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-WEBHOOK-1',
            'sync' => new InvoiceSync(
                qb_id: 'QB-WEBHOOK-1',
                qb_sync_token: '2',
                qb_status: InvoiceQbStatus::Synced->value,
                qb_status_message: 'Previous failure',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_invoice->service->settings->invoice->direction = SyncDirection::PULL;
        $qb_record = (object) [
            'Id' => 'QB-WEBHOOK-1',
            'SyncToken' => '3',
        ];

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-WEBHOOK-1')
            ->andReturn($qb_record);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')
            ->once()
            ->with($qb_record, $qb_invoice->service)
            ->andReturn(['number' => 'INV-WEBHOOK-1']);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->with(['number' => 'INV-WEBHOOK-1'], Mockery::on(fn (Invoice $model): bool => $model->is($invoice)))
            ->andReturn($invoice);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $qb_invoice->sync('QB-WEBHOOK-1', now()->addMinute()->toIso8601String());

        $invoice = $invoice->fresh();
        $this->assertSame('QB-WEBHOOK-1', $invoice->sync->qb_id);
        $this->assertSame('3', $invoice->sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced->value, $invoice->sync->qb_status);
        $this->assertSame('Previous failure', $invoice->sync->qb_status_message);
    }

    public function testWebhookSyncPersistsZeroSyncToken(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-WEBHOOK-ZERO',
            'sync' => new InvoiceSync(
                qb_id: 'QB-WEBHOOK-ZERO',
                qb_sync_token: '9',
                qb_status: InvoiceQbStatus::Synced->value,
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_invoice->service->settings->invoice->direction = SyncDirection::PULL;
        $qb_record = (object) [
            'Id' => 'QB-WEBHOOK-ZERO',
            'SyncToken' => '0',
        ];

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-WEBHOOK-ZERO')
            ->andReturn($qb_record);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')
            ->once()
            ->with($qb_record, $qb_invoice->service)
            ->andReturn(['number' => 'INV-WEBHOOK-ZERO']);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->andReturn($invoice);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $qb_invoice->sync('QB-WEBHOOK-ZERO', now()->subMinute()->toIso8601String());

        $invoice = $invoice->fresh();
        $this->assertSame('0', $invoice->sync->qb_sync_token);
    }

    public function testPushFailurePersistsParsedQuickbooksFaultImmediately(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-PUSH-FAULT',
            'sync' => new InvoiceSync(
                qb_id: '1234',
                qb_sync_token: '1',
                qb_status: InvoiceQbStatus::Synced->value,
            ),
        ]);

        $client_sync = $this->client->sync ?? new \App\DataMapper\ClientSync();
        $client_sync->qb_id = 'QB-CUSTOMER';
        $this->client->sync = $client_sync;
        $this->client->saveQuietly();

        $qb_invoice = $this->makeQbInvoice();
        $existing_qb_invoice = (object) [
            'Id' => '1234',
            'SyncToken' => '2',
        ];
        $failure = <<<'ERROR'
Request is not made successful. Response Code:[400] with body: [<?xml version="1.0" encoding="UTF-8" standalone="yes"?><IntuitResponse xmlns="http://schema.intuit.com/finance/v3"><Fault type="ValidationFault"><Error code="2040" element="DisplayName"><Message>Invalid String. The String may contain unsupported or illegal chars</Message><Detail>Element contains invalid characters. Regencium: Physical Therapy and Performance - West Greater Houston (C)</Detail></Error></Fault></IntuitResponse>].
ERROR;

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', '1234')
            ->andReturn($existing_qb_invoice);
        $sdk->shouldReceive('update')
            ->once()
            ->andThrow(new \RuntimeException($failure));
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('ninjaToQb')
            ->once()
            ->with(Mockery::on(fn (Invoice $model): bool => $model->is($invoice)), $qb_invoice->service)
            ->andReturn(['Id' => '1234']);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        try {
            $qb_invoice->syncToForeign([$invoice]);
            $this->fail('Expected the QuickBooks push to fail.');
        } catch (\RuntimeException) {
            $invoice = $invoice->fresh();
            $this->assertSame(
                'DisplayName contains characters QuickBooks does not support (QB 2040). Edit the name and retry.',
                $invoice->sync->qb_status_message
            );
        }
    }

    public function testCheckRecordsDifferencesWithoutChangingInvoiceData(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-CHECK-2',
            'amount' => 125.00,
            'balance' => 125.00,
            'sync' => new InvoiceSync(qb_id: 'QB-CHECK-2'),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_invoice->service->settings->invoice->direction = SyncDirection::PULL;
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-CHECK-2')
            ->andReturn((object) [
                'Id' => 'QB-CHECK-2',
                'DocNumber' => 'QB-NUMBER',
                'TotalAmt' => 150.00,
                'SyncToken' => '5',
            ]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);

        $this->assertSame(InvoiceQbStatus::DataMismatch->value, $checked_invoice->sync->qb_status);
        $this->assertSame(
            'The linked QuickBooks invoice differs: its number is #QB-NUMBER instead of #INV-CHECK-2, and its total is 150.00 instead of 125.00.',
            $checked_invoice->sync->qb_status_message
        );
        $this->assertSame('INV-CHECK-2', $checked_invoice->number);
        $this->assertSame(125.00, (float) $checked_invoice->amount);

        $context = $qb_invoice->checkContext();
        $this->assertSame('data_mismatch', $context['outcome']);
        $this->assertFalse($context['comparison']['number']['matches']);
        $this->assertFalse($context['comparison']['total']['matches']);
        $this->assertSame(150.00, $context['quickbooks']['total']);
        $this->assertSame(
            ['verify_quickbooks_invoice', 'force_pull'],
            $context['recommended_actions']
        );
    }

    public function testUnlinkedCheckPreservesPushFailureWhenNumberIsAvailable(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-AVAILABLE',
            'sync' => new InvoiceSync(qb_status_message: 'Previous push failure.'),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $qb_invoice->service->settings->invoice->direction = SyncDirection::PUSH;
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-AVAILABLE'")
            ->andReturn([]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);

        $this->assertSame(InvoiceQbStatus::Syncable->value, $checked_invoice->sync->qb_status);
        $this->assertSame('Previous push failure.', $checked_invoice->sync->qb_status_message);

        $context = $qb_invoice->checkContext();
        $this->assertSame('syncable', $context['outcome']);
        $this->assertFalse($context['linked']);
        $this->assertNull($context['quickbooks']);
        $this->assertNull($context['comparison']);
        $this->assertSame(['force_push'], $context['recommended_actions']);
    }

    public function testUnlinkedCheckReturnsCandidateComparisonContext(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-CANDIDATE',
            'amount' => 100.00,
            'balance' => 100.00,
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-CANDIDATE'")
            ->andReturn((object) [
                'Id' => 'QB-CANDIDATE',
                'DocNumber' => 'INV-CANDIDATE',
                'TotalAmt' => 125.00,
                'Balance' => 25.00,
                'TxnStatus' => 'Open',
                'SyncToken' => '4',
            ]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);
        $context = $qb_invoice->checkContext();

        $this->assertSame(InvoiceQbStatus::DataMismatch->value, $checked_invoice->sync->qb_status);
        $this->assertSame('data_mismatch', $context['outcome']);
        $this->assertFalse($context['linked']);
        $this->assertSame('QB-CANDIDATE', $context['quickbooks']['id']);
        $this->assertTrue($context['comparison']['number']['matches']);
        $this->assertFalse($context['comparison']['total']['matches']);
        $this->assertSame(
            ['verify_quickbooks_invoice', 'change_invoice_number'],
            $context['recommended_actions']
        );
    }

    public function testLinkedCheckClearsResolvedDataMismatch(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-RESOLVED',
            'amount' => 175.00,
            'balance' => 175.00,
            'sync' => new InvoiceSync(
                qb_id: 'QB-RESOLVED',
                qb_status: InvoiceQbStatus::DataMismatch->value,
                qb_status_message: 'The linked QuickBooks invoice total differs.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-RESOLVED')
            ->andReturn((object) [
                'Id' => 'QB-RESOLVED',
                'DocNumber' => 'INV-RESOLVED',
                'TotalAmt' => 175.00,
                'SyncToken' => '7',
            ]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);

        $this->assertSame(InvoiceQbStatus::Synced->value, $checked_invoice->sync->qb_status);
        $this->assertSame('7', $checked_invoice->sync->qb_sync_token);
        $this->assertSame('', $checked_invoice->sync->qb_status_message);
    }

    public function testUnlinkedCheckClearsResolvedDataMismatchWhenNumberIsAvailable(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'INV-RESOLVED-AVAILABLE',
            'sync' => new InvoiceSync(
                qb_status: InvoiceQbStatus::DataMismatch->value,
                qb_status_message: 'A QuickBooks invoice previously used this number.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-RESOLVED-AVAILABLE'")
            ->andReturn([]);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $checked_invoice = $qb_invoice->check($invoice);

        $this->assertSame(InvoiceQbStatus::Syncable->value, $checked_invoice->sync->qb_status);
        $this->assertSame('', $checked_invoice->sync->qb_status_message);
        $this->assertSame([], $qb_invoice->checkContext()['recommended_actions']);
    }

    public function testDocNumberPreflightFailureAllowsCreateToProceed(): void
    {
        $qb_invoice = $this->makeQbInvoice();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-RETRY'")
            ->andThrow(new \RuntimeException('QuickBooks rate limit: capacity unavailable after wait'));

        $this->setSdkWrapper($qb_invoice, $sdk);

        $result = $this->invoke($qb_invoice, 'findQbInvoiceByDocNumber', ['INV-RETRY']);

        $this->assertNull($result);
    }
}
