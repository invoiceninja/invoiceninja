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
use App\Factory\InvoiceItemFactory;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use ReflectionClass;
use Tests\MockAccountData;
use Tests\TestCase;

class QbInvoiceLookupImportTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->app['config']->set('services.quickbooks.client_id', null);
        QuickbooksService::$importing = [];
    }

    protected function tearDown(): void
    {
        QuickbooksService::$importing = [];
        Mockery::close();
        parent::tearDown();
    }

    public function test_find_invoice_returns_existing_row_by_qb_id(): void
    {
        $invoice = $this->makeLinkedInvoice('QB-FIND-1');
        $found = $this->invoke($this->makeQbInvoice(), 'findInvoice', ['QB-FIND-1', (string) $this->client->id]);

        $this->assertTrue($found->exists);
        $this->assertSame($invoice->id, $found->id);
    }

    public function test_find_invoice_returns_unsaved_factory_when_qb_id_is_unknown(): void
    {
        $found = $this->invoke($this->makeQbInvoice(), 'findInvoice', ['QB-MISSING', (string) $this->client->id]);

        $this->assertFalse($found->exists);
        $this->assertSame((int) $this->client->id, $found->client_id);
        $this->assertSame('QB-MISSING', $found->sync->qb_id);
    }

    public function test_qb_invoice_update_skips_line_items_when_balance_matches(): void
    {
        $line = InvoiceItemFactory::create();
        $line->quantity = 1;
        $line->cost = 40;
        $line->line_total = 40;

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 40,
            'amount' => 40,
            'line_items' => [$line],
        ]);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')->never();

        $qb_invoice = $this->makeQbInvoice();
        $this->setProperty($qb_invoice, 'invoice_repository', $repository);

        $this->invoke($qb_invoice, 'qbInvoiceUpdate', [[
            'balance' => 40,
            'public_notes' => 'updated',
            'line_items' => [(object) ['notes' => 'should not replace']],
        ], $invoice]);

        $invoice->refresh();
        $this->assertSame('updated', $invoice->public_notes);
        $this->assertSame(40.0, (float) $invoice->line_items[0]->cost);
    }

    public function test_qb_invoice_update_saves_through_repository_when_balance_differs(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 40,
            'amount' => 40,
        ]);

        $payload = ['balance' => 55, 'public_notes' => 'from-qb'];
        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')->once()->with($payload, Mockery::on(fn (Invoice $saved): bool => $saved->id === $invoice->id));

        $qb_invoice = $this->makeQbInvoice();
        $this->setProperty($qb_invoice, 'invoice_repository', $repository);

        $this->invoke($qb_invoice, 'qbInvoiceUpdate', [$payload, $invoice]);

        $this->addToAssertionCount(1);
    }

    public function test_import_to_ninja_skips_when_transformer_returns_false(): void
    {
        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')->once()->andReturn(false);

        $qb_invoice = $this->makeQbInvoice();
        $this->setProperty($qb_invoice, 'invoice_transformer', $transformer);

        $qb_invoice->importToNinja([(object) ['Id' => 'QB-SKIP']]);

        $this->assertSame(0, Invoice::query()->where('company_id', $this->company->id)->where('sync->qb_id', 'QB-SKIP')->count());
    }

    public function test_import_to_ninja_skips_create_when_docnumber_collides_with_linked_invoice(): void
    {
        $existing = $this->makeLinkedInvoice('QB-OWNER');
        $existing->number = 'INV-COLLIDE';
        $existing->saveQuietly();

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')->once()->andReturn([
            'id' => 'QB-NEW',
            'client_id' => $this->client->id,
            'number' => 'INV-COLLIDE',
            'balance' => 10,
            'payment_ids' => ['PAY-1'],
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setProperty($qb_invoice, 'invoice_transformer', $transformer);
        $qb_invoice->importToNinja([(object) ['Id' => 'QB-NEW', 'DocNumber' => 'INV-COLLIDE']]);

        $this->assertSame(0, Invoice::query()->where('company_id', $this->company->id)->where('sync->qb_id', 'QB-NEW')->count());
        $this->assertSame('QB-OWNER', $existing->fresh()->sync->qb_id);
    }

    public function test_sync_to_ninja_creates_invoice_and_marks_it_synced(): void
    {
        $line = InvoiceItemFactory::create();
        $line->quantity = 1;
        $line->cost = 25;
        $line->line_total = 25;

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')->once()->andReturn([
            'id' => 'QB-CREATE-1',
            'client_id' => $this->client->id,
            'number' => 'INV-CREATE-1',
            'date' => '2026-04-01',
            'due_date' => '2026-04-15',
            'balance' => 25,
            'amount' => 25,
            'line_items' => [$line],
            'payment_ids' => [],
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setProperty($qb_invoice, 'invoice_transformer', $transformer);
        $qb_invoice->syncToNinja([(object) ['Id' => 'QB-CREATE-1', 'SyncToken' => '4']]);

        $created = Invoice::query()->where('company_id', $this->company->id)->where('sync->qb_id', 'QB-CREATE-1')->first();
        $this->assertNotNull($created);
        $this->assertSame('INV-CREATE-1', $created->number);
        $this->assertSame('4', $created->sync->qb_sync_token);
    }

    public function test_mark_invoice_synced_and_push_failure_persist_on_sync(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->invoke($qb_invoice, 'markInvoicePushFailure', [$invoice, 'push failed']);
        $this->assertSame('push failed', $invoice->fresh()->sync->qb_status_message);

        $this->invoke($qb_invoice, 'markInvoiceSynced', [$invoice->fresh(), 'QB-OK', '7', true]);
        $synced = $invoice->fresh();
        $this->assertSame('QB-OK', $synced->sync->qb_id);
        $this->assertSame('7', $synced->sync->qb_sync_token);
        $this->assertSame('', $synced->sync->qb_status_message);
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

        return new QbInvoice(new QuickbooksService($this->company));
    }

    private function makeLinkedInvoice(string $qb_id): Invoice
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);
        $invoice->sync = new InvoiceSync(qb_id: $qb_id);
        $invoice->saveQuietly();

        return $invoice->fresh();
    }

    private function invoke(QbInvoice $qb_invoice, string $method, array $args = []): mixed
    {
        $method_ref = (new ReflectionClass($qb_invoice))->getMethod($method);
        $method_ref->setAccessible(true);

        return $method_ref->invokeArgs($qb_invoice, $args);
    }

    private function setProperty(QbInvoice $qb_invoice, string $property, mixed $value): void
    {
        $property_ref = (new ReflectionClass($qb_invoice))->getProperty($property);
        $property_ref->setAccessible(true);
        $property_ref->setValue($qb_invoice, $value);
    }
}
