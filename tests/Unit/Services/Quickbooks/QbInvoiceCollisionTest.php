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
use App\Models\Invoice;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\SdkWrapper;
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
        $this->assertStringContainsString('INV-COLLIDE-1', $invoice->sync->qb_status_message);
        $this->assertStringContainsString('999', $invoice->sync->qb_status_message);
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
        $this->assertSame(InvoiceQbStatus::AmountMismatch->value, $invoice->sync->qb_status);
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

    public function testDocNumberPreflightFailureAllowsCreateToProceed(): void
    {
        $qb_invoice = $this->makeQbInvoice();
        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-RETRY'")
            ->andThrow(new \RuntimeException('QuickBooks rate limit: capacity unavailable after wait'));

        $service_reflection = new ReflectionClass($qb_invoice->service);
        $sdk_wrapper = $service_reflection->getProperty('sdk_wrapper');
        $sdk_wrapper->setAccessible(true);
        $sdk_wrapper->setValue($qb_invoice->service, $sdk);

        $result = $this->invoke($qb_invoice, 'findQbInvoiceByDocNumber', ['INV-RETRY']);

        $this->assertNull($result);
    }
}
