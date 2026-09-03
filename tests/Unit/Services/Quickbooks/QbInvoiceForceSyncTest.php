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

use App\DataMapper\ClientSync;
use App\DataMapper\InvoiceSync;
use App\DataMapper\PaymentSync;
use App\DataMapper\QuickbooksSettings;
use App\Enum\InvoiceQbStatus;
use App\Enum\SyncDirection;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\SdkWrapper;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class QbInvoiceForceSyncTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        QuickbooksService::$importing = [];
    }

    protected function tearDown(): void
    {
        QuickbooksService::$importing = [];
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

    private function setInvoiceDirection(QbInvoice $qb_invoice, SyncDirection $direction): void
    {
        $qb_invoice->service->settings->invoice->direction = $direction;
    }

    private function linkClientToQuickbooks(string $qb_id): void
    {
        $sync = $this->client->sync ?? new ClientSync();
        $sync->qb_id = $qb_id;
        $this->client->sync = $sync;
        $this->client->saveQuietly();
        $this->client->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeInvoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'paid_to_date' => 0,
            'discount' => 0,
            'status_id' => Invoice::STATUS_SENT,
        ], $attributes));
    }

    private function assertImportingFlagCleared(): void
    {
        $this->assertArrayNotHasKey($this->company->id, QuickbooksService::$importing);
    }

    private function invoicePaymentableCount(Invoice $invoice): int
    {
        return Paymentable::withTrashed()
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->count();
    }

    /* ------------------------------------------------------------------ */
    /* forcePull                                                          */
    /* ------------------------------------------------------------------ */

    public function testForcePullRefusesUnlinkedInvoiceWithoutCallingQuickbooks(): void
    {
        $invoice = $this->makeInvoice(['number' => 'INV-PULL-UNLINKED']);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice is not linked to QuickBooks.');

        try {
            $qb_invoice->forcePull($invoice);
        } finally {
            $this->assertImportingFlagCleared();
        }
    }

    public function testForcePullRefusesWhenPullDirectionIsNotEnabled(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PULL-DISABLED',
            'sync' => new InvoiceSync(qb_id: 'QB-PULL-DISABLED'),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PUSH);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice pull is not enabled for this company.');

        $qb_invoice->forcePull($invoice);
    }

    public function testForcePullFailsWhenQuickbooksRecordIsMissing(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PULL-MISSING',
            'sync' => new InvoiceSync(qb_id: 'QB-PULL-MISSING'),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-PULL-MISSING')
            ->andReturn(null);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')->never();
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        try {
            $qb_invoice->forcePull($invoice);
            $this->fail('Expected force-pull to fail for a missing QuickBooks invoice.');
        } catch (RuntimeException $e) {
            $this->assertSame('QuickBooks invoice QB-PULL-MISSING was not found.', $e->getMessage());
        }

        $this->assertSame('QB-PULL-MISSING', $invoice->fresh()->sync->qb_id);
        $this->assertImportingFlagCleared();
    }

    public function testForcePullFailsWhenTransformerRejectsRecord(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PULL-UNTRANSFORMABLE',
            'sync' => new InvoiceSync(qb_id: 'QB-PULL-UNTRANSFORMABLE'),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $qb_record = (object) [
            'Id' => 'QB-PULL-UNTRANSFORMABLE',
            'DocNumber' => 'INV-PULL-UNTRANSFORMABLE',
            'TotalAmt' => 100.00,
            'SyncToken' => '2',
        ];

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-PULL-UNTRANSFORMABLE')
            ->andReturn($qb_record);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')
            ->once()
            ->with($qb_record, $qb_invoice->service)
            ->andReturn(false);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')->never();
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        try {
            $qb_invoice->forcePull($invoice);
            $this->fail('Expected force-pull to fail when the transformer rejects the record.');
        } catch (RuntimeException $e) {
            $this->assertSame('Unable to transform QuickBooks invoice for force-pull.', $e->getMessage());
        }

        $this->assertImportingFlagCleared();
    }

    public function testForcePullSavesQuickbooksDataAndPreservesPriorFailureMessage(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-FORCE-PULL',
            'sync' => new InvoiceSync(
                qb_id: 'QB-PULL-1',
                qb_status: InvoiceQbStatus::DataMismatch->value,
                qb_sync_token: '1',
                qb_status_message: 'The linked QuickBooks invoice total differs.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $qb_record = (object) [
            'Id' => 'QB-PULL-1',
            'DocNumber' => 'INV-FORCE-PULL',
            'TotalAmt' => 175.00,
            'SyncToken' => '9',
            'TxnStatus' => 'Open',
        ];

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-PULL-1')
            ->andReturn($qb_record);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')
            ->once()
            ->with($qb_record, $qb_invoice->service)
            ->andReturn([
                'id' => 'QB-PULL-1',
                'client_id' => $this->client->id,
                'number' => 'INV-FORCE-PULL',
                'amount' => 175.00,
                'balance' => 175.00,
                'payment_ids' => [],
            ]);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $saved_data = null;
        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->with(
                Mockery::on(function (array $data) use (&$saved_data): bool {
                    $saved_data = $data;

                    return true;
                }),
                Mockery::on(fn (Invoice $model): bool => $model->is($invoice))
            )
            ->andReturn($invoice);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $pulled = $qb_invoice->forcePull($invoice);

        $this->assertIsArray($saved_data);
        $this->assertArrayNotHasKey('payment_ids', $saved_data);
        $this->assertArrayNotHasKey('id', $saved_data);
        $this->assertSame(175.00, $saved_data['amount']);

        $this->assertSame('QB-PULL-1', $pulled->sync->qb_id);
        $this->assertSame('9', $pulled->sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced->value, $pulled->sync->qb_status);
        $this->assertSame(
            'The linked QuickBooks invoice total differs.',
            $pulled->sync->qb_status_message
        );
        $this->assertImportingFlagCleared();
    }

    public function testForcePullDeletesInvoiceWhenQuickbooksRecordIsVoided(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PULL-VOIDED',
            'sync' => new InvoiceSync(qb_id: 'QB-PULL-VOIDED', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $qb_record = (object) [
            'Id' => 'QB-PULL-VOIDED',
            'DocNumber' => 'INV-PULL-VOIDED',
            'TotalAmt' => 100.00,
            'TxnStatus' => 'Voided',
        ];

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->twice()
            ->with('Invoice', 'QB-PULL-VOIDED')
            ->andReturn($qb_record);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('qbToNinja')->never();
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('save')->never();
        $repository->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (Invoice $model): bool => $model->is($invoice)))
            ->andReturn($invoice);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $result = $qb_invoice->forcePull($invoice);

        $this->assertTrue($result->is($invoice));
        $this->assertImportingFlagCleared();
    }

    /* ------------------------------------------------------------------ */
    /* forcePush                                                          */
    /* ------------------------------------------------------------------ */

    public function testForcePushRefusesLinkableInvoice(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PUSH-LINKABLE',
            'sync' => new InvoiceSync(
                qb_status: InvoiceQbStatus::Linkable->value,
                qb_status_message: 'QuickBooks invoice #INV-PUSH-LINKABLE has the same number and total.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PUSH);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->never();
        $sdk->shouldReceive('add')->never();
        $sdk->shouldReceive('update')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Force-push is only available for syncable or synced invoices with a prior push failure.');

        $qb_invoice->forcePush($invoice);
    }

    public function testForcePushRefusesWhenNoPushFailureWasRecorded(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PUSH-CLEAN',
            'sync' => new InvoiceSync(
                qb_id: 'QB-PUSH-CLEAN',
                qb_status: InvoiceQbStatus::Synced->value,
                qb_sync_token: '3',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PUSH);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->never();
        $sdk->shouldReceive('update')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Force-push is only available after a recorded push failure.');

        $qb_invoice->forcePush($invoice);
    }

    public function testForcePushRefusesWhenPushDirectionIsNotEnabled(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PUSH-DISABLED',
            'sync' => new InvoiceSync(
                qb_status: InvoiceQbStatus::Syncable->value,
                qb_status_message: 'Unable to push to QuickBooks: client could not be created.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')->never();
        $sdk->shouldReceive('add')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice push is not enabled for this company.');

        $qb_invoice->forcePush($invoice);
    }

    public function testForcePushUpdatesLinkedInvoiceAndClearsFailureMessage(): void
    {
        $this->linkClientToQuickbooks('QB-CUSTOMER-PUSH');

        $invoice = $this->makeInvoice([
            'number' => 'INV-PUSH-RETRY',
            'sync' => new InvoiceSync(
                qb_id: '4321',
                qb_status: InvoiceQbStatus::Synced->value,
                qb_sync_token: '1',
                qb_status_message: 'DisplayName contains characters QuickBooks does not support (QB 2040). Edit the name and retry.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PUSH);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', '4321')
            ->andReturn((object) ['Id' => '4321', 'SyncToken' => '2']);
        $sdk->shouldReceive('add')->never();
        $sdk->shouldReceive('update')
            ->once()
            ->andReturn((object) ['Id' => '4321', 'SyncToken' => '3']);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('ninjaToQb')
            ->once()
            ->with(Mockery::on(fn (Invoice $model): bool => $model->is($invoice)), $qb_invoice->service)
            ->andReturn(['Id' => '4321']);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $pushed = $qb_invoice->forcePush($invoice);

        $this->assertSame('4321', $pushed->sync->qb_id);
        $this->assertSame('3', $pushed->sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced->value, $pushed->sync->qb_status);
        $this->assertSame('', $pushed->sync->qb_status_message);
    }

    public function testForcePushCreatesUnlinkedInvoiceWhenDocNumberIsAvailable(): void
    {
        $this->linkClientToQuickbooks('QB-CUSTOMER-CREATE');

        $invoice = $this->makeInvoice([
            'number' => 'INV-PUSH-CREATE',
            'sync' => new InvoiceSync(
                qb_status: InvoiceQbStatus::Syncable->value,
                qb_status_message: 'Unable to push to QuickBooks while preparing the invoice.',
            ),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::BIDIRECTIONAL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('query')
            ->once()
            ->with("select * from Invoice where DocNumber = 'INV-PUSH-CREATE'")
            ->andReturn([]);
        $sdk->shouldReceive('update')->never();
        $sdk->shouldReceive('add')
            ->once()
            ->andReturn((object) ['Id' => '9001', 'SyncToken' => '0']);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $transformer = Mockery::mock(InvoiceTransformer::class);
        $transformer->shouldReceive('ninjaToQb')
            ->once()
            ->with(Mockery::on(fn (Invoice $model): bool => $model->is($invoice)), $qb_invoice->service)
            ->andReturn(['DocNumber' => 'INV-PUSH-CREATE']);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_transformer', $transformer);

        $pushed = $qb_invoice->forcePush($invoice);

        $this->assertSame('9001', $pushed->sync->qb_id);
        $this->assertSame('0', $pushed->sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced->value, $pushed->sync->qb_status);
        $this->assertSame('', $pushed->sync->qb_status_message);
    }

    /* ------------------------------------------------------------------ */
    /* attachPayments                                                     */
    /* ------------------------------------------------------------------ */

    public function testAttachPaymentsIgnoresEmptyPaymentIdentifiers(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-PAY-EMPTY',
            'sync' => new InvoiceSync(qb_id: 'QB-INV-EMPTY', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $qb_invoice = $this->makeQbInvoice();

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')->never();
        $this->setSdkWrapper($qb_invoice, $sdk);

        $qb_invoice->attachPayments($invoice, ['', null, 0]);

        $this->assertSame(0, $this->invoicePaymentableCount($invoice));
        $this->assertSame(100.00, (float) $invoice->fresh()->balance);
    }

    public function testAttachPaymentsDoesNotDuplicateAnExistingPaymentLink(): void
    {
        $this->linkClientToQuickbooks('QB-CUSTOMER-PAY');

        $invoice = $this->makeInvoice([
            'number' => 'INV-PAY-DUPLICATE',
            'balance' => 60.00,
            'paid_to_date' => 40.00,
            'status_id' => Invoice::STATUS_PARTIAL,
            'sync' => new InvoiceSync(qb_id: 'QB-INV-DUP', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $payment = $this->makeSyncedPayment('QB-PAY-DUP', 'PAY-DUP-1', 40.00);

        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = 40.00;
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime('2026-01-15');
        $paymentable->updated_at = strtotime('2026-01-15');
        $paymentable->save();

        $payment_count_before = Payment::where('company_id', $this->company->id)->count();

        $qb_invoice = $this->makeQbInvoice();

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->twice()
            ->with('Payment', 'QB-PAY-DUP')
            ->andReturn($this->makeQbPaymentRecord('QB-PAY-DUP', 'QB-INV-DUP', 40.00));
        $this->setSdkWrapper($qb_invoice, $sdk);

        $qb_invoice->attachPayments($invoice, ['QB-PAY-DUP', 'QB-PAY-DUP']);

        $this->assertSame(1, $this->invoicePaymentableCount($invoice));
        $this->assertSame(
            $payment_count_before,
            Payment::where('company_id', $this->company->id)->count()
        );

        $invoice = $invoice->fresh();
        $this->assertSame(60.00, (float) $invoice->balance);
        $this->assertSame(40.00, (float) $invoice->paid_to_date);
    }

    public function testAttachPaymentsSkipsPaymentWithNoAmountAppliedToInvoice(): void
    {
        $this->linkClientToQuickbooks('QB-CUSTOMER-PAY');

        $invoice = $this->makeInvoice([
            'number' => 'INV-PAY-UNRELATED',
            'sync' => new InvoiceSync(qb_id: 'QB-INV-UNRELATED', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $this->makeSyncedPayment('QB-PAY-UNRELATED', 'PAY-UNRELATED-1', 40.00);

        $qb_invoice = $this->makeQbInvoice();

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Payment', 'QB-PAY-UNRELATED')
            ->andReturn($this->makeQbPaymentRecord('QB-PAY-UNRELATED', 'QB-SOME-OTHER-INVOICE', 40.00));
        $this->setSdkWrapper($qb_invoice, $sdk);

        $qb_invoice->attachPayments($invoice, ['QB-PAY-UNRELATED']);

        $this->assertSame(0, $this->invoicePaymentableCount($invoice));

        $invoice = $invoice->fresh();
        $this->assertSame(100.00, (float) $invoice->balance);
        $this->assertSame(0.00, (float) $invoice->paid_to_date);
    }

    public function testAttachPaymentsAppliesLinkedQuickbooksPaymentToInvoice(): void
    {
        $this->linkClientToQuickbooks('QB-CUSTOMER-PAY');

        $invoice = $this->makeInvoice([
            'number' => 'INV-PAY-APPLY',
            'sync' => new InvoiceSync(qb_id: 'QB-INV-APPLY', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $payment = $this->makeSyncedPayment('QB-PAY-APPLY', 'PAY-APPLY-1', 40.00);

        $qb_invoice = $this->makeQbInvoice();

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Payment', 'QB-PAY-APPLY')
            ->andReturn($this->makeQbPaymentRecord('QB-PAY-APPLY', 'QB-INV-APPLY', 40.00));
        $this->setSdkWrapper($qb_invoice, $sdk);

        $qb_invoice->attachPayments($invoice, ['QB-PAY-APPLY']);

        $this->assertSame(1, $this->invoicePaymentableCount($invoice));

        $paymentable = Paymentable::where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->first();

        $this->assertSame($payment->id, $paymentable->payment_id);
        $this->assertSame(40.00, (float) $paymentable->amount);

        $invoice = $invoice->fresh();
        $this->assertSame(60.00, (float) $invoice->balance);
        $this->assertSame(40.00, (float) $invoice->paid_to_date);
        $this->assertSame(Invoice::STATUS_PARTIAL, $invoice->status_id);
    }

    /* ------------------------------------------------------------------ */
    /* delete gating                                                      */
    /* ------------------------------------------------------------------ */

    public function testDeleteIsSkippedWhenPullDirectionIsNotEnabled(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-DELETE-BLOCKED',
            'sync' => new InvoiceSync(qb_id: 'QB-DELETE-BLOCKED', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PUSH);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-DELETE-BLOCKED')
            ->andReturn((object) ['Id' => 'QB-DELETE-BLOCKED', 'TxnStatus' => 'Voided']);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('delete')->never();
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $qb_invoice->delete('QB-DELETE-BLOCKED');

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice);
        $this->assertFalse((bool) $invoice->is_deleted);
        $this->assertImportingFlagCleared();
    }

    public function testDeleteRemovesLinkedInvoiceWhenPullDirectionIsEnabled(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-DELETE-ALLOWED',
            'sync' => new InvoiceSync(qb_id: 'QB-DELETE-ALLOWED', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-DELETE-ALLOWED')
            ->andReturn((object) ['Id' => 'QB-DELETE-ALLOWED', 'TxnStatus' => 'Voided']);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (Invoice $model): bool => $model->is($invoice)))
            ->andReturn($invoice);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $qb_invoice->delete('QB-DELETE-ALLOWED');

        $this->assertImportingFlagCleared();
    }

    public function testDeleteWithUnknownQuickbooksIdTouchesNoStoredInvoice(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-DELETE-UNKNOWN',
            'sync' => new InvoiceSync(qb_id: 'QB-DELETE-KNOWN', qb_status: InvoiceQbStatus::Synced->value),
        ]);

        $invoice_count_before = Invoice::withTrashed()->where('company_id', $this->company->id)->count();

        $qb_invoice = $this->makeQbInvoice();
        $this->setInvoiceDirection($qb_invoice, SyncDirection::PULL);

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('findById')
            ->once()
            ->with('Invoice', 'QB-DELETE-ORPHAN')
            ->andReturn((object) ['Id' => 'QB-DELETE-ORPHAN', 'TxnStatus' => 'Voided']);
        $this->setSdkWrapper($qb_invoice, $sdk);

        $deleted_models = [];
        $repository = Mockery::mock(InvoiceRepository::class);
        $repository->shouldReceive('delete')
            ->with(Mockery::on(function (Invoice $model) use (&$deleted_models): bool {
                $deleted_models[] = $model;

                return true;
            }))
            ->andReturn(null);
        $this->setQbInvoiceProperty($qb_invoice, 'invoice_repository', $repository);

        $qb_invoice->delete('QB-DELETE-ORPHAN');

        foreach ($deleted_models as $model) {
            $this->assertFalse($model->exists, 'An unknown QuickBooks id must not resolve to a stored invoice.');
        }

        $this->assertSame(
            $invoice_count_before,
            Invoice::withTrashed()->where('company_id', $this->company->id)->count()
        );

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice);
        $this->assertFalse((bool) $invoice->is_deleted);
    }

    /* ------------------------------------------------------------------ */
    /* fixtures                                                           */
    /* ------------------------------------------------------------------ */

    private function makeSyncedPayment(string $qb_id, string $number, float $amount): Payment
    {
        return Payment::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => $number,
            'amount' => $amount,
            'applied' => $amount,
            'date' => '2026-01-15',
            'status_id' => Payment::STATUS_COMPLETED,
            'sync' => new PaymentSync(['qb_id' => $qb_id]),
        ]);
    }

    private function makeQbPaymentRecord(string $qb_id, string $linked_invoice_qb_id, float $amount): object
    {
        return (object) [
            'Id' => $qb_id,
            'TxnDate' => '2026-01-15',
            'TotalAmt' => $amount,
            'UnappliedAmt' => 0,
            'CurrencyRef' => (object) ['value' => 'USD'],
            'CustomerRef' => (object) ['value' => 'QB-CUSTOMER-PAY'],
            'Line' => [
                (object) [
                    'Amount' => $amount,
                    'LinkedTxn' => (object) [
                        'TxnType' => 'Invoice',
                        'TxnId' => $linked_invoice_qb_id,
                    ],
                ],
            ],
        ];
    }
}
