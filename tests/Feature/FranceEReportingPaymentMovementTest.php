<?php

namespace Tests\Feature;

use App\Console\Kernel as ConsoleKernel;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Repositories\PaymentRepository;
use App\Jobs\Cron\FranceEReportingCron;
use App\Jobs\EDocument\EInvoicePullDocs;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Jobs\EDocument\UpdateFranceEReportSubmissionStatus;
use App\Services\EDocument\Jobs\SendEDocument;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Observers\PaymentObserver;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateReconciler;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FrancePaymentReportingMutationGuard;
use App\Services\EDocument\Standards\France\FranceSubmissionClaim;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;
use Modules\Admin\Jobs\Storecove\DocumentSubmission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceEReportingPaymentMovementTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if(!class_exists(Modules\Admin\Jobs\Storecove\DocumentSubmission::class)){
            $this->markTestSkipped('Storecove Document Submission is not configured.');
        }
        
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-18 12:00:00', 'Europe/Paris'));

        $this->faker = Factory::create();
        $this->makeTestData();
        $this->enableFranceReporting();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testItDefersPartialMovementsAndPromotesAFullPaymentAggregate(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');

        $paymentOne = $this->makePayment($invoice->client, '2026-09-15', '400');
        $paymentableOne = $this->makePaymentable($paymentOne, $invoice, '400', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '400');

        (new RecordFranceEReportingPayment(
            $paymentOne->id,
            $this->company->db,
            $invoice->id,
            $paymentableOne->id,
            '400',
            '2026-09-15',
        ))->handle();

        $this->assertSame(1, $this->movementEvents($invoice)->count());
        $this->assertSame(0, $this->reportEvents($invoice)->count());

        $paymentTwo = $this->makePayment($invoice->client, '2026-10-02', '800');
        $paymentableTwo = $this->makePaymentable($paymentTwo, $invoice, '800', '2026-10-02');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $paymentTwo->id,
            $this->company->db,
            $invoice->id,
            $paymentableTwo->id,
            '800',
            '2026-10-02',
        ))->handle();

        $movements = $this->movementEvents($invoice);
        $report = $this->reportEvents($invoice)->first();

        $this->assertSame(2, $movements->count());
        $this->assertNotNull($report);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $report->payment_status);
        $this->assertSame('2026-10-31', $report->period->toDateString());
        $this->assertSame('initial', data_get($report->payment_request, 'fr_report_kind'));
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertCount(2, data_get($report->payment_request, 'source_event_ids'));
        $this->assertSame([(int) $report->id, (int) $report->id], $movements->map(fn (TransactionEvent $movement): int => (int) data_get($movement->payment_request, 'report_event_id'))->all());
        $this->assertSame('2026-10-02', $report->reporting_data->frReportEntry->b2cPayment->date);
        $this->assertSame(1200, $report->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->amount);
    }

    #[DataProvider('unsupportedB2CSupplyTypes')]
    public function testItDoesNotRecordB2CPaymentsForUnsupportedSupplyTypes(array $type_ids): void
    {
        $line_items = collect($type_ids)
            ->map(fn (int $type_id, int $index): object => $this->makeLineItemWithTax(
                'ITEM-'.$index,
                500,
                'VAT',
                20,
                count($type_ids) === 1 ? 2 : 1,
                $type_id,
            ))
            ->all();
        $invoice = $this->makeInvoice(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-01',
            lineItems: $line_items,
        );
        $amount = (string) $invoice->amount;
        $payment = $this->makePayment($invoice->client, '2026-09-15', $amount);
        $paymentable = $this->makePaymentable($payment, $invoice, $amount, '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, $amount);

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            $amount,
            '2026-09-15',
        ))->handle();

        $this->assertFalse(TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_PAYMENT)
            ->exists());
    }

    public static function unsupportedB2CSupplyTypes(): array
    {
        return [
            'goods' => [[Product::PRODUCT_TYPE_PHYSICAL]],
            'mixed goods and services' => [[Product::PRODUCT_TYPE_PHYSICAL, Product::PRODUCT_TYPE_SERVICE]],
            'unknown' => [[3]],
        ];
    }

    public function testPendingB2CPaymentIsNotSubmittedAfterSupplyChangesFromServicesToGoods(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $report = $this->reportEvents($invoice)->firstOrFail();
        $lineItems = $invoice->line_items;
        $lineItems[0]->type_id = Product::PRODUCT_TYPE_PHYSICAL;
        $invoice->line_items = $lineItems;
        $invoice->save();
        $invoice = $invoice->fresh();

        $compiler = new FranceEReportCompiler();

        $this->assertFalse($compiler
            ->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, $report->period->toDateString())
            ->contains('id', $report->id));

        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFranceEReport(
            $this->company->id,
            TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            $report->period->toDateString(),
            $this->company->db,
        ))->handle($storecove, $compiler, new FranceEReportPayloadBuilder());

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $payment->id,
            '2026-09-15',
            '2026-09-15',
            [$paymentable->id],
        );

        $this->assertNull($report->fresh());
        $this->assertNull(data_get($this->movementEvents($invoice)->firstOrFail()->payment_request, 'report_event_id'));
    }

    public function testQueuedAppliedMovementDoesNotUseSerializedDateAfterPaymentableIsRemoved(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            999999999,
            '1200',
            '2026-09-15',
        ))->handle();

        $this->assertFalse(TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('event_id', [TransactionEvent::FR_B2C_PAYMENT, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT])
            ->exists());
    }

    public function testRefundAfterSubmittedPaymentCreatesCorrectivePaymentEvent(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $initialReport = $this->reportEvents($invoice)->firstOrFail();
        $compiler = new FranceEReportCompiler();
        $initialSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-09-30');

        $this->assertTrue($initialSources->contains('id', $initialReport->id));
        $this->assertFalse($initialSources->contains(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT));

        $initialReport->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initialReport->save();

        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 200;
        $payment->save();

        $invoice = $this->setInvoicePaymentState($invoice, '1000');

        $refund_job = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-200',
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refunded:200',
        );
        $refund_job->handle();
        $refund_job->handle();

        $correctiveReport = $this->reportEvents($invoice)
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE);

        $this->assertNotNull($correctiveReport);
        $this->assertSame('2026-10-31', $correctiveReport->period->toDateString());
        $this->assertSame(-200.0, (float) $correctiveReport->payment_applied);
        $this->assertSame((int) $initialReport->id, (int) data_get($correctiveReport->payment_request, 'previous_event_id'));
        $this->assertSame('2026-10-12', $correctiveReport->reporting_data->frReportEntry->b2cPayment->date);
        $this->assertSame(-200, $correctiveReport->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->amount);

        $correctiveSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE, '2026-10-31');
        $initialSourcesForCorrectionPeriod = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-10-31');

        $this->assertTrue($correctiveSources->contains('id', $correctiveReport->id));
        $this->assertFalse($initialSourcesForCorrectionPeriod->contains('id', $correctiveReport->id));

        $correctiveReport->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $correctiveReport->save();
        $refund_job->handle();

        $this->assertCount(2, $this->movementEvents($invoice));
        $this->assertCount(2, $this->reportEvents($invoice));
    }

    public function testTwoEqualSameDayRefundsRemainDistinctWhileEachJobIsIdempotent(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $initial_report = $this->reportEvents($invoice)->firstOrFail();
        $initial_report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_report->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 100;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, '1100');
        $first_refund = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-100',
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refunded:100',
        );
        $first_refund->handle();
        $first_refund->handle();

        $payment->refunded = 200;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, '1000');
        $second_refund = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-100',
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refunded:200',
        );
        $second_refund->handle();
        $second_refund->handle();

        $corrective = $this->reportEvents($invoice)
            ->firstOrFail(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE);

        $this->assertSame(-200.0, (float) $corrective->payment_applied);
        $this->assertCount(2, data_get($corrective->payment_request, 'source_event_ids'));
        $this->assertCount(3, $this->movementEvents($invoice));
        $this->assertCount(2, $this->reportEvents($invoice));
    }

    public function testASecondRefundAfterACorrectiveSubmissionCreatesANewCorrectionChainLink(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $initial_report = $this->reportEvents($invoice)->firstOrFail();
        $initial_report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_report->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 100;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, '1100');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-100',
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refunded:100',
        ))->handle();

        $first_corrective = $this->reportEvents($invoice)
            ->firstOrFail(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE);
        $first_corrective->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $first_corrective->save();
        $payment->refunded = 150;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, '1050');
        $second_refund = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-50',
            '2026-11-04',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refunded:150',
        );
        $second_refund->handle();
        $second_refund->handle();

        $correctives = $this->reportEvents($invoice)
            ->filter(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE)
            ->values();
        $second_corrective = $correctives->last();

        $this->assertCount(2, $correctives);
        $this->assertSame(-50.0, (float) $second_corrective->payment_applied);
        $this->assertSame('2026-11-30', $second_corrective->period->toDateString());
        $this->assertSame((int) $first_corrective->id, (int) data_get($second_corrective->payment_request, 'previous_event_id'));
        $this->assertCount(1, data_get($second_corrective->payment_request, 'source_event_ids'));
        $this->assertCount(3, $this->reportEvents($invoice));
        $this->assertCount(3, $this->movementEvents($invoice));
    }

    public function testOnePaymentAcrossTwoB2CInvoicesCreatesIndependentCorrectiveReports(): void
    {
        $invoice_one = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $invoice_two = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01', client: $invoice_one->client);
        $payment = $this->makePayment($invoice_one->client, '2026-09-15', '2400');
        $paymentable_one = $this->makePaymentable($payment, $invoice_one, '1200', '2026-09-15');
        $paymentable_two = $this->makePaymentable($payment, $invoice_two, '1200', '2026-09-15');
        $invoice_one = $this->setInvoicePaymentState($invoice_one, '1200');
        $invoice_two = $this->setInvoicePaymentState($invoice_two, '1200');

        (new RecordFranceEReportingPayment($payment->id, $this->company->db))->handle();

        $initial_one = $this->reportEvents($invoice_one)->firstOrFail();
        $initial_two = $this->reportEvents($invoice_two)->firstOrFail();
        $initial_one->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_one->save();
        $initial_two->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_two->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 300;
        $payment->save();
        $paymentable_one->refunded = 100;
        $paymentable_one->save();
        $paymentable_two->refunded = 200;
        $paymentable_two->save();
        $invoice_one = $this->setInvoicePaymentState($invoice_one, '1100');
        $invoice_two = $this->setInvoicePaymentState($invoice_two, '1000');

        foreach ([
            [$invoice_one, $paymentable_one, '-100', 'refund:invoice-one'],
            [$invoice_two, $paymentable_two, '-200', 'refund:invoice-two'],
        ] as [$invoice, $paymentable, $amount, $mutation_key]) {
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                $amount,
                '2026-10-12',
                FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
                $mutation_key,
            ))->handle();
        }

        $corrective_one = $this->reportEvents($invoice_one)->last();
        $corrective_two = $this->reportEvents($invoice_two)->last();

        $this->assertSame(-100.0, (float) $corrective_one->payment_applied);
        $this->assertSame(-200.0, (float) $corrective_two->payment_applied);
        $this->assertSame((int) $initial_one->id, (int) data_get($corrective_one->payment_request, 'previous_event_id'));
        $this->assertSame((int) $initial_two->id, (int) data_get($corrective_two->payment_request, 'previous_event_id'));
        $this->assertSame($paymentable_one->id, data_get($this->movementEvents($invoice_one)->last()->payment_request, 'paymentable_id'));
        $this->assertSame($paymentable_two->id, data_get($this->movementEvents($invoice_two)->last()->payment_request, 'paymentable_id'));
        $this->assertCount(2, $this->reportEvents($invoice_one));
        $this->assertCount(2, $this->reportEvents($invoice_two));
    }

    public function testItDoesNotRecordDomesticFrenchBusinessPaymentMovements(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $this->assertSame(0, TransactionEvent::query()->where('invoice_id', $invoice->id)->count());
    }

    public function testItRecordsForeignBusinessPaymentsAsVatExcludedMonthlyReports(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "DE", classification: "business", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $movement = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->firstOrFail();

        $report = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_PENDING)
            ->firstOrFail();

        $this->assertSame(RecordFranceEReportingPayment::KIND_MOVEMENT, data_get($movement->payment_request, "fr_kind"));
        $this->assertSame("2026-09-30", $report->period->toDateString());
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertSame("initial", data_get($report->payment_request, "fr_report_kind"));
        $this->assertSame("2026-09-15", $report->reporting_data->frReportEntry->b2biPayment->paymentDate);
        $this->assertSame(1200, $report->reporting_data->frReportEntry->b2biPayment->taxSubtotals[0]->amountIncludingTax);

        $compiler = new FranceEReportCompiler();
        $vatExcludedSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED, "2026-09-30");
        $b2cSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, "2026-09-30");

        $this->assertTrue($vatExcludedSources->contains("id", $report->id));
        $this->assertFalse($b2cSources->contains("id", $report->id));
    }

    public function testB2BIPaymentReportAllocatesThePaymentAcrossTaxSubtotals(): void
    {
        $invoice = $this->makeInvoice(
            clientCountry: "DE",
            classification: "business",
            date: "2026-09-01",
            lineItems: [
                $this->makeLineItemWithTax("CONSULTING-20", 100, "VAT20", 20),
                $this->makeLineItemWithTax("CONSULTING-10", 100, "VAT10", 10),
            ],
        );
        $payment = $this->makePayment($invoice->client, "2026-09-15", "230");
        $paymentable = $this->makePaymentable($payment, $invoice, "230", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "230");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "230",
            "2026-09-15",
        ))->handle();

        $report = TransactionEvent::query()
            ->where("invoice_id", $invoice->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_PENDING)
            ->firstOrFail();

        $taxSubtotals = $report->reporting_data->frReportEntry->b2biPayment->taxSubtotals;
        $amountsIncludingTax = collect($taxSubtotals)
            ->map(fn (object $taxSubtotal): string => (string) $taxSubtotal->amountIncludingTax)
            ->all();
        $amountIncludingTaxTotal = collect($taxSubtotals)
            ->sum(fn (object $taxSubtotal): float => (float) $taxSubtotal->amountIncludingTax);

        $this->assertCount(2, $taxSubtotals);
        $this->assertSame(["120", "110"], $amountsIncludingTax);
        $this->assertSame(230.0, $amountIncludingTaxTotal);
        $this->assertSame((float) $payment->amount, $amountIncludingTaxTotal);
    }

    public function testRefundBeforeSubmissionDefersPaymentReportingUntilTheInvoiceIsPaidInFullAgain(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $this->assertCount(1, $this->reportEvents($invoice));

        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 200;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, "1000");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "-200",
            "2026-09-18",
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        ))->handle();

        $this->assertCount(0, $this->reportEvents($invoice));
        $this->assertCount(2, $this->movementEvents($invoice));
        $this->assertSame(
            [null, null],
            $this->movementEvents($invoice)
                ->map(fn (TransactionEvent $movement): mixed => data_get($movement->payment_request, 'report_event_id'))
                ->all(),
        );

        $restoring_payment = $this->makePayment($invoice->client, '2026-09-20', '200');
        $restoring_paymentable = $this->makePaymentable($restoring_payment, $invoice, '200', '2026-09-20');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $restoring_payment->id,
            $this->company->db,
            $invoice->id,
            $restoring_paymentable->id,
            '200',
            '2026-09-20',
        ))->handle();

        $report = $this->reportEvents($invoice)->firstOrFail();

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $report->payment_status);
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertSame('2026-09-20', data_get($report->payment_request, 'source_date'));
        $this->assertCount(3, data_get($report->payment_request, 'source_event_ids'));
    }

    public function testRefundBeforeSubmissionThatNetsToZeroRemovesThePendingReport(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $this->assertSame(1, $this->reportEvents($invoice)->count());

        $payment->status_id = Payment::STATUS_REFUNDED;
        $payment->refunded = 1200;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, "0");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "-1200",
            "2026-09-18",
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        ))->handle();

        $movements = $this->movementEvents($invoice);

        $this->assertSame(0, $this->reportEvents($invoice)->count());
        $this->assertSame(2, $movements->count());
        $this->assertSame([null, null], $movements->map(fn (TransactionEvent $movement): mixed => data_get($movement->payment_request, "report_event_id"))->all());
    }

    public function testForeignRefundBeforeSubmissionAlsoDefersPaymentReportingUntilPaidInFull(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'DE', classification: 'business', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 200;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, '1000');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-200',
            '2026-09-18',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        ))->handle();

        $events = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->get();

        $this->assertCount(2, $events);
        $this->assertTrue($events->every(
            fn (TransactionEvent $event): bool => $event->payment_status === TransactionEvent::FR_REPORTING_STATUS_DEFERRED
                && is_null(data_get($event->payment_request, 'report_event_id')),
        ));
    }

    public function testSubmissionBoundaryExcludesAStaleInitialPaymentReportButKeepsCorrectivesEligible(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        $report = $this->reportEvents($invoice)->firstOrFail();
        $invoice = $this->setInvoicePaymentState($invoice, '1000');
        $compiler = new FranceEReportCompiler();

        $this->assertFalse($compiler
            ->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-09-30')
            ->contains('id', $report->id));

        $request = $report->payment_request;
        $request['fr_report_kind'] = RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE;
        $report->payment_request = $request;
        $report->save();

        $this->assertTrue($compiler
            ->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE, '2026-09-30')
            ->contains('id', $report->id));
    }

    public function testDeletedPaymentAfterSubmittedPaymentCreatesCorrectivePaymentEvent(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $initialReport = $this->reportEvents($invoice)->firstOrFail();
        $initialReport->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initialReport->save();

        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, "0");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "-1200",
            "2026-10-12",
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
        ))->handle();

        $correctiveReport = $this->reportEvents($invoice)
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, "fr_report_kind") === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE);

        $this->assertNotNull($correctiveReport);
        $this->assertSame("2026-10-31", $correctiveReport->period->toDateString());
        $this->assertSame(-1200.0, (float) $correctiveReport->payment_applied);
        $this->assertSame((int) $initialReport->id, (int) data_get($correctiveReport->payment_request, "previous_event_id"));
        $this->assertSame(FrancePaymentApplicationRecorder::MOVEMENT_DELETED, data_get($this->movementEvents($invoice)->last()->payment_request, "movement_type"));
    }

    public function testRerunningThePaymentRecorderIsIdempotent(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        $job = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        );

        $job->handle();
        $job->handle();

        $this->assertSame(1, $this->movementEvents($invoice)->count());
        $this->assertSame(1, $this->reportEvents($invoice)->count());
        $this->assertCount(1, data_get($this->reportEvents($invoice)->first()->payment_request, "source_event_ids"));
    }

    public function testPaymentRequestPayloadsOnlyContainRequiredHydrationKeys(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $movementRequest = $this->movementEvents($invoice)->firstOrFail()->payment_request;
        $reportRequest = $this->reportEvents($invoice)->firstOrFail()->payment_request;

        $this->assertSame([
            "fr_kind",
            "source_date",
            "paymentable_id",
            "movement_type",
            "movement_amount",
            "snapshot_hash",
            "report_event_id",
        ], array_keys($movementRequest));

        $this->assertSame([
            "fr_kind",
            "fr_report_kind",
            "source_date",
            "source_event_ids",
            "previous_event_id",
        ], array_keys($reportRequest));
    }

    public function testSinglePaymentAppliedAfterTheInvoicePeriodReportsInThePaymentPeriod(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-10-02", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-10-02");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-10-02",
        ))->handle();

        $movement = $this->movementEvents($invoice)->firstOrFail();
        $report = $this->reportEvents($invoice)->firstOrFail();

        $this->assertSame("2026-10-31", $movement->period->toDateString());
        $this->assertSame("2026-10-31", $report->period->toDateString());
        $this->assertSame("2026-10-02", data_get($report->payment_request, "source_date"));
        $this->assertSame("2026-10-02", $report->reporting_data->frReportEntry->b2cPayment->date);
    }

    public function testDeletedPaymentBeforeSubmissionThatNetsToZeroRemovesThePendingReport(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, "0");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "-1200",
            "2026-09-18",
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
        ))->handle();

        $movements = $this->movementEvents($invoice);

        $this->assertSame(0, $this->reportEvents($invoice)->count());
        $this->assertSame(2, $movements->count());
        $this->assertSame(FrancePaymentApplicationRecorder::MOVEMENT_DELETED, data_get($movements->last()->payment_request, "movement_type"));
        $this->assertSame([null, null], $movements->map(fn (TransactionEvent $movement): mixed => data_get($movement->payment_request, "report_event_id"))->all());
    }

    public function testCreditAppliedMovementsCanPromoteAFullPaymentReport(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
            FrancePaymentApplicationRecorder::MOVEMENT_CREDIT_APPLIED,
        ))->handle();

        $movement = $this->movementEvents($invoice)->firstOrFail();
        $report = $this->reportEvents($invoice)->firstOrFail();

        $this->assertSame(FrancePaymentApplicationRecorder::MOVEMENT_CREDIT_APPLIED, data_get($movement->payment_request, "movement_type"));
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertSame((int) $report->id, (int) data_get($movement->payment_request, "report_event_id"));
    }

    public function testNegativeMovementBeforeAnyReportStaysDeferredUntilTheInvoiceIsFullyPaid(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "400");
        $paymentable = $this->makePaymentable($payment, $invoice, "400", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "400");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "400",
            "2026-09-15",
        ))->handle();

        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 100;
        $payment->save();
        $invoice = $this->setInvoicePaymentState($invoice, "300");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "-100",
            "2026-09-18",
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        ))->handle();

        $movements = $this->movementEvents($invoice);

        $this->assertSame(2, $movements->count());
        $this->assertSame(0, $this->reportEvents($invoice)->count());
        $this->assertSame([null, null], $movements->map(fn (TransactionEvent $movement): mixed => data_get($movement->payment_request, "report_event_id"))->all());
    }

    public function testPaymentRepositoryInvokesFranceRecorderForDomesticFrenchBusinessInvoices(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");

        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldReceive("recordMovement")->once();
        $this->app->instance(FrancePaymentApplicationRecorder::class, $recorder);

        app(PaymentRepository::class)->save([
            "amount" => 1200,
            "client_id" => $invoice->client_id,
            "date" => "2026-09-15",
            "invoices" => [
                [
                    "invoice_id" => $invoice->id,
                    "amount" => 1200,
                ],
            ],
        ], PaymentFactory::create($this->company->id, $this->user->id, $invoice->client_id));

        $this->assertSame(0, TransactionEvent::query()->where('invoice_id', $invoice->id)->count());
    }

    public function testPaymentRepositoryContinuesWhenFranceRecorderFails(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");

        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldReceive("recordMovement")
            ->once()
            ->andThrow(new \RuntimeException("France recorder failed"));
        $this->app->instance(FrancePaymentApplicationRecorder::class, $recorder);

        $payment = app(PaymentRepository::class)->save([
            "amount" => 1200,
            "client_id" => $invoice->client_id,
            "date" => "2026-09-15",
            "invoices" => [
                [
                    "invoice_id" => $invoice->id,
                    "amount" => 1200,
                ],
            ],
        ], PaymentFactory::create($this->company->id, $this->user->id, $invoice->client_id));

        $this->assertNotNull($payment->id);
        $this->assertSame(1200.0, (float) $payment->fresh()->applied);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance);
    }

    public function testDomesticFrenchBusinessFullPaymentCreatesPaymentReceivedNotificationEvent(): void
    {
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);

        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);

        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $events = $this->paymentNotificationEvents($invoice);
        $event = $events->first();

        $this->assertTrue($invoice->client->reportableFrTransaction());
        $this->assertSame(1, $events->count());
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $event->payment_status);
        $this->assertSame(RecordFranceEReportingPayment::KIND_PAYMENT_RECEIVED_NOTIFICATION, data_get($event->payment_request, "fr_kind"));
        $this->assertSame("original-storecove-guid", data_get($event->payment_request, "original_document_guid"));
        $this->assertSame(0, TransactionEvent::query()->where('invoice_id', $invoice->id)->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)->count());

        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
    }

    public function testPaymentReportingPathIsResolvedOnceForEveryInvoiceOnAPayment(): void
    {
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);

        $invoiceOne = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoiceTwo = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01", client: $invoiceOne->client);
        $invoiceOne->backup->guid = "original-storecove-guid-one";
        $invoiceTwo->backup->guid = "original-storecove-guid-two";
        $invoiceOne->save();
        $invoiceTwo->save();

        $payment = $this->makePayment($invoiceOne->client, "2026-09-15", "2400");
        $this->makePaymentable($payment, $invoiceOne, "1200", "2026-09-15");
        $this->makePaymentable($payment, $invoiceTwo, "1200", "2026-09-15");
        $invoiceOne = $this->setInvoicePaymentState($invoiceOne, "1200");
        $invoiceTwo = $this->setInvoicePaymentState($invoiceTwo, "1200");

        (new RecordFranceEReportingPayment($payment->id, $this->company->db))->handle();

        $this->assertSame(1, $this->paymentNotificationEvents($invoiceOne)->count());
        $this->assertSame(1, $this->paymentNotificationEvents($invoiceTwo)->count());
        $this->assertSame(2, TransactionEvent::query()
            ->where("payment_id", $payment->id)
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->count());
        $this->assertSame(0, TransactionEvent::query()
            ->where("payment_id", $payment->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->count());

        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
    }

    public function testDomesticFrenchBusinessPartialPaymentDoesNotCreatePaymentReceivedNotification(): void
    {
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);

        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);

        $payment = $this->makePayment($invoice->client, "2026-09-15", "400");
        $paymentable = $this->makePaymentable($payment, $invoice, "400", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "400");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "400",
            "2026-09-15",
        ))->handle();

        $this->assertSame(0, $this->paymentNotificationEvents($invoice)->count());
        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
    }

    public function testDomesticFrenchBusinessPaymentWithoutStorecoveGuidDoesNotCreateNotification(): void
    {
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);

        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $this->assertSame(0, $this->paymentNotificationEvents($invoice)->count());
        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
    }

    public function testStorecoveEnabledFrenchB2BInvoicePaymentNotificationRunsThroughDailyCron(): void
    {
        $this->travelTo(CarbonImmutable::parse("2026-09-15 09:00:00", "Europe/Paris"));

        try {
            config(["ninja.environment" => "selfhost", "ninja.hosted_ninja_url" => "https://hosted.test"]);
            $this->enableFranceStorecoveEInvoicing();

            if ((int) $this->company->legal_entity_id !== $this->franceStorecoveLegalEntityId()) {
                $this->markTestSkipped("FR Storecove legal entity is not configured.");
            }

            $invoice = $this->makeStorecoveFrenchBusinessInvoice("2026-09-15");
            $submittedInvoicePayload = [];

            Http::preventStrayRequests();
            Http::fake([
                "https://hosted.test/api/einvoice/submission" => function ($request) use (&$submittedInvoicePayload) {
                    $submittedInvoicePayload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

                    return Http::response(["guid" => "original-storecove-guid"], 200, ["X-EINVOICE-QUOTA" => "99"]);
                },
            ]);

            (new SendEDocument(Invoice::class, $invoice->id, $this->company->db))->handle($this->storecoveForFrenchDiscovery());

            $invoice = $invoice->fresh();

            $this->assertSame("original-storecove-guid", $invoice->backup->guid);
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $this->assertSame($this->franceStorecoveLegalEntityId(), $submittedInvoicePayload["legal_entity_id"]);
            $this->assertSame("invoice", $submittedInvoicePayload["document"]["document_type"]);
            $this->assertSame([["application" => "fr-dgfip", "settings" => ["enabled" => true]]], $submittedInvoicePayload["routing"]["networks"]);

            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();

            $event = $this->paymentNotificationEvents($invoice)->firstOrFail();

            $this->travelTo(CarbonImmutable::parse("2026-09-15 22:00:00", "Europe/Paris"));
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFrancePaymentReceivedNotification::class, function (SubmitFrancePaymentReceivedNotification $job) use ($event): bool {
                return (int) $this->jobProperty($job, "transactionEventId") === (int) $event->id;
            });

            $notificationPayload = [];
            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
            $proxy->shouldReceive("submitDocument")
                ->once()
                ->with(\Mockery::on(function (array $payload) use (&$notificationPayload): bool {
                    $notificationPayload = $payload;

                    return true;
                }))
                ->andReturn(["guid" => "notification-storecove-guid"]);
            $storecove->proxy = $proxy;

            (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

            $this->assertSame("original-storecove-guid", $notificationPayload["forDocumentSubmissionGuid"]);
            $this->assertSame("payment_received_notification", $notificationPayload["document"]["documentType"]);
            $this->assertSame("auto", $notificationPayload["document"]["paymentReceivedNotification"]["mode"]);
            $this->assertArrayNotHasKey("legal_entity_id", $notificationPayload);
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->fresh()->payment_status);
        } finally {
            $this->travelBack();
        }
    }

    public function testPendingFrenchB2BPaymentIsCapturedWhenItLaterCompletes(): void
    {
        $this->travelTo(CarbonImmutable::parse("2026-09-15 09:00:00", "Europe/Paris"));

        try {
            config(["ninja.environment" => "selfhost", "ninja.hosted_ninja_url" => "https://hosted.test"]);
            $this->enableFranceStorecoveEInvoicing();

            if ((int) $this->company->legal_entity_id !== $this->franceStorecoveLegalEntityId()) {
                $this->markTestSkipped("FR Storecove legal entity is not configured.");
            }

            $invoice = $this->makeStorecoveFrenchBusinessInvoice("2026-09-15");
            $invoice->backup->guid = "original-storecove-guid";
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);

            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $payment->status_id = Payment::STATUS_PENDING;
            $payment->saveQuietly();
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();

            $this->assertSame(0, $this->paymentNotificationEvents($invoice)->count());

            $recordedMovement = [];
            $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
            $recorder->shouldReceive("recordMovement")
                ->once()
                ->withAnyArgs()
                ->andReturnUsing(function (...$arguments) use (&$recordedMovement): void {
                    $recordedMovement = $arguments;
                });

            $this->app->instance(FrancePaymentApplicationRecorder::class, $recorder);

            $this->travelTo(CarbonImmutable::parse("2026-09-16 10:00:00", "Europe/Paris"));
            $paymentForObserver = $payment->fresh();
            $paymentForObserver->syncOriginal();
            $paymentForObserver->status_id = Payment::STATUS_COMPLETED;

            app(PaymentObserver::class)->updated($paymentForObserver);
            $this->assertSame((int) $payment->id, (int) $recordedMovement[0]->id);
            $this->assertSame(Payment::STATUS_COMPLETED, (int) $recordedMovement[0]->status_id);
            $this->assertSame((int) $invoice->id, (int) $recordedMovement[1]->id);
            $this->assertSame((int) $paymentable->id, (int) $recordedMovement[2]->id);
            $this->assertSame(1200.0, (float) $recordedMovement[3]);

            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->saveQuietly();

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();

            $event = $this->paymentNotificationEvents($invoice)->firstOrFail();

            $this->travelTo(CarbonImmutable::parse("2026-09-16 22:00:00", "Europe/Paris"));
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFrancePaymentReceivedNotification::class, function (SubmitFrancePaymentReceivedNotification $job) use ($event): bool {
                return (int) $this->jobProperty($job, "transactionEventId") === (int) $event->id;
            });
        } finally {
            $this->travelBack();
        }
    }

    public function testFranceEReportingCronDispatchesPendingDailyPaymentReceivedNotifications(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));
        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
            $invoice->backup->guid = "original-storecove-guid";
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();
            Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
            (new FranceEReportingCron())->handle();
            $events = $this->paymentNotificationEvents($invoice);
            $this->assertSame(1, $events->count());
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $events->first()->payment_status);
            Bus::assertDispatched(SubmitFrancePaymentReceivedNotification::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
    public function testFranceEReportingCronDoesNotCreatePaymentNotificationForPendingPayment(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));
        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
            $invoice->backup->guid = "original-storecove-guid";
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $payment->status_id = Payment::STATUS_PENDING;
            $payment->save();
            $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");
            (new FranceEReportingCron())->handle();
            $this->assertSame(0, $this->paymentNotificationEvents($invoice)->count());
            Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
    public function testFranceEReportingCronDispatchesEligiblePaymentReportsFromGroupedTransactionEvents(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-10-08 22:00:00", "Europe/Paris"));
        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();
            $this->assertSame(1, $this->reportEvents($invoice)->count());
            Bus::assertNotDispatched(SubmitFranceEReport::class);
            (new FranceEReportingCron())->handle();
            Bus::assertDispatched(SubmitFranceEReport::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportingCronDispatchesB2CTransactionOnlyReportsInDueWindow(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));

        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-05");
            $this->createFranceReportSourceEvent(
                invoice: $invoice,
                eventId: TransactionEvent::FR_B2C_TRANSACTION,
                period: "2026-09-10",
                reportingData: $this->b2cTransactionReportPayload("2026-09-05"),
            );

            (new FranceEReportingCron())->handle();

            Bus::assertDispatchedTimes(SubmitFranceEReport::class, 1);
            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                return (int) $this->jobProperty($job, "companyId") === (int) $this->company->id
                    && (int) $this->jobProperty($job, "submissionEventId") === TransactionEvent::FR_REPORT_SUBMISSION_B2C
                    && $this->jobProperty($job, "periodEnd") === "2026-09-10";
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportingCronDispatchesVatExcludedTransactionOnlyReportsInDueWindow(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-11-08 22:00:00", "Europe/Paris"));

        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "DE", classification: "business", date: "2026-09-05");
            $this->createFranceReportSourceEvent(
                invoice: $invoice,
                eventId: TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                period: "2026-10-31",
                reportingData: $this->b2biInvoiceReportPayload($invoice, "2026-09-05"),
            );

            (new FranceEReportingCron())->handle();

            Bus::assertDispatchedTimes(SubmitFranceEReport::class, 1);
            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                return (int) $this->jobProperty($job, "companyId") === (int) $this->company->id
                    && (int) $this->jobProperty($job, "submissionEventId") === TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED
                    && $this->jobProperty($job, "periodEnd") === "2026-10-31";
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportingCronDispatchesOneCombinedB2CReportForTransactionAndPaymentSources(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-10-08 22:00:00", "Europe/Paris"));

        try {
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-25");
            $transactionEvent = $this->createFranceReportSourceEvent(
                invoice: $invoice,
                eventId: TransactionEvent::FR_B2C_TRANSACTION,
                period: "2026-09-30",
                reportingData: $this->b2cTransactionReportPayload("2026-09-25"),
            );
            $payment = $this->makePayment($invoice->client, "2026-09-25", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-25");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-25",
            ))->handle();

            $paymentEvent = $this->reportEvents($invoice)->firstOrFail();

            Bus::fake();
            (new FranceEReportingCron())->handle();

            Bus::assertDispatchedTimes(SubmitFranceEReport::class, 1);
            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                return (int) $this->jobProperty($job, "submissionEventId") === TransactionEvent::FR_REPORT_SUBMISSION_B2C
                    && $this->jobProperty($job, "periodEnd") === "2026-09-30";
            });

            $submittedPayload = [];
            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
            $proxy->shouldReceive("submitDocument")
                ->once()
                ->with(\Mockery::on(function (array $payload) use (&$submittedPayload): bool {
                    $submittedPayload = $payload;

                    return true;
                }))
                ->andReturn(["guid" => "combined-report-storecove-guid"]);
            $storecove->proxy = $proxy;

            (new SubmitFranceEReport(
                $this->company->id,
                TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                "2026-09-30",
                $this->company->db,
            ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

            $frEReport = $submittedPayload["document"]["frEReport"];
            $this->assertCount(1, $frEReport["transactionReport"]["b2cTransactions"]);
            $this->assertCount(1, $frEReport["paymentReport"]["b2cPayments"]);
            $this->assertSame('2026-09-21 - 2026-09-30', $frEReport["transactionReport"]["period"]);
            $this->assertSame('2026-09-01 - 2026-09-30', $frEReport["paymentReport"]["period"]);
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $transactionEvent->fresh()->payment_status);
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $paymentEvent->fresh()->payment_status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportingCronSkipsPaymentReportsWhenNoReportingPeriodIsDueToday(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-28 22:00:00", "Europe/Paris"));
        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();
            $this->assertSame(1, $this->reportEvents($invoice)->count());
            (new FranceEReportingCron())->handle();
            Bus::assertNotDispatched(SubmitFranceEReport::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportingCronDispatchesAnOverdueInitialReportWithoutARecoveryCutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-12-15 22:00:00", "Europe/Paris"));

        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-05");
            $this->createFranceReportSourceEvent(
                invoice: $invoice,
                eventId: TransactionEvent::FR_B2C_TRANSACTION,
                period: "2026-09-10",
                reportingData: $this->b2cTransactionReportPayload("2026-09-05"),
            );

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                return (int) $this->jobProperty($job, "submissionEventId") === TransactionEvent::FR_REPORT_SUBMISSION_B2C
                    && $this->jobProperty($job, "periodEnd") === "2026-09-10";
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testSuccessfulPaymentNotificationDeletesSupersededFailedRowsForInvoice(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);

        $paymentOne = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentableOne = $this->makePaymentable($paymentOne, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $paymentOne->id,
            $this->company->db,
            $invoice->id,
            $paymentableOne->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $failedEvent = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $paymentOne->status_id = Payment::STATUS_CANCELLED;
        $paymentOne->save();

        (new SubmitFrancePaymentReceivedNotification($failedEvent->id, $this->company->db))->handle(new Storecove());

        $failedEvent = $failedEvent->fresh();
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $failedEvent->payment_status);
        $this->assertNotNull(data_get($failedEvent->payment_request, "skip_reason"));

        $paymentTwo = $this->makePayment($invoice->client, "2026-09-16", "1200");
        $paymentableTwo = $this->makePaymentable($paymentTwo, $invoice, "1200", "2026-09-16");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $paymentTwo->id,
            $this->company->db,
            $invoice->id,
            $paymentableTwo->id,
            "1200",
            "2026-09-16",
        ))->handle();

        $submittedEvent = $this->paymentNotificationEvents($invoice)->where("id", "!=", $failedEvent->id)->firstOrFail();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
        $proxy->shouldReceive("submitDocument")->once()->andReturn(["guid" => "notification-storecove-guid"]);
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($submittedEvent->id, $this->company->db))->handle($storecove);

        $this->assertNull(TransactionEvent::query()->find($failedEvent->id));
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $submittedEvent->fresh()->payment_status);
        $this->assertSame(1, $this->paymentNotificationEvents($invoice)->count());
    }

    public function testSuccessfulOldDocumentNotificationDoesNotDeleteANewDocumentNotification(): void
    {
        [$invoice, $payment, $paymentable] = $this->makeDomesticBusinessPaymentNotification();
        $oldEvent = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive('setCompany')->once()->andReturnSelf();
        $proxy->shouldReceive('submitDocument')->once()->andReturnUsing(function () use ($invoice, $oldEvent): array {
            $currentInvoice = $invoice->fresh();
            $currentInvoice->backup->guid = 'replacement-storecove-guid';
            $currentInvoice->save();
            $replacementEvent = $oldEvent->replicate();
            $request = $replacementEvent->payment_request;
            $request['original_document_guid'] = 'replacement-storecove-guid';
            $request['idempotency_guid'] = 'replacement-notification-idempotency-guid';
            $replacementEvent->payment_request = $request;
            $replacementEvent->save();

            return ['guid' => 'old-notification-storecove-guid'];
        });
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($oldEvent->id, $this->company->db))->handle($storecove);

        $events = $this->paymentNotificationEvents($invoice);
        $newEvent = $events->first(fn (TransactionEvent $event): bool => $event->id !== $oldEvent->id);

        $this->assertCount(2, $events);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $oldEvent->fresh()->payment_status);
        $this->assertNotNull($newEvent);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $newEvent->payment_status);
        $this->assertSame('replacement-storecove-guid', data_get($newEvent->payment_request, 'original_document_guid'));
    }

    public function testPaymentReceivedNotificationSubmissionUsesOriginalStorecoveGuid(): void
    {
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);

        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);

        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $event = $this->paymentNotificationEvents($invoice)->first();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);

        $proxy->shouldReceive("setCompany")
            ->once()
            ->andReturnSelf();

        $proxy->shouldReceive("submitDocument")
            ->once()
            ->with(\Mockery::on(function (array $payload) use ($event): bool {
                return $payload["forDocumentSubmissionGuid"] === "original-storecove-guid"
                    && $payload["idempotencyGuid"] === data_get($event->payment_request, "idempotency_guid")
                    && $payload["document"]["documentType"] === "payment_received_notification"
                    && $payload["document"]["paymentReceivedNotification"]["mode"] === "auto"
                    && $payload["tenant_id"] === $this->company->company_key
                    && $payload["account_key"] === $this->company->account->key
                    && $payload["e_invoicing_token"] === $this->company->account->e_invoicing_token
                    && ! array_key_exists("legal_entity_id", $payload);
            }))
            ->andReturn(["guid" => "notification-storecove-guid"]);

        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $event = $event->fresh();

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->payment_status);
        $this->assertSame("notification-storecove-guid", data_get($event->payment_request, "guid"));
    }


    public function testPaymentReceivedNotificationWaitsForClearedOriginalInvoiceAndRemainsRetryable(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive("setCompany");
        $proxy->shouldNotReceive("submitDocument");
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $event = $event->fresh();
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
        $this->assertSame("Original Storecove document has not cleared yet.", data_get($event->payment_request, "error.message"));
        $this->assertNull(data_get($event->payment_request, "skip_reason"));

        $this->markOriginalInvoiceCleared($invoice);
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
        $proxy->shouldReceive("submitDocument")->once()->andReturn(["guid" => "notification-storecove-guid"]);
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->fresh()->payment_status);
    }

    public function testStorecoveInvoiceStatusIsPersistedForPaymentNotificationEligibility(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();

        $method = new ReflectionMethod(EInvoicePullDocs::class, "recordDocumentStatus");
        $method->setAccessible(true);
        $method->invoke(new EInvoicePullDocs(), $invoice, "cleared");

        $invoice = $invoice->fresh();

        $this->assertSame("cleared", $invoice->backup->e_invoice_status);
        $this->assertNotNull($invoice->backup->e_invoice_cleared_at);
    }

    public function testHostedStorecoveStatusPersistenceMatchesTheSelfHostedPullPath(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "hosted-original-storecove-guid";
        $invoice->save();

        $method = new ReflectionMethod(DocumentSubmission::class, "recordDocumentStatus");
        $method->setAccessible(true);
        $job = new DocumentSubmission([]);
        $method->invoke($job, $invoice, "cleared");

        $invoice = $invoice->fresh();
        $clearedAt = $invoice->backup->e_invoice_cleared_at;

        $this->assertSame("cleared", $invoice->backup->e_invoice_status);
        $this->assertNotNull($clearedAt);

        $method->invoke($job, $invoice, "accepted");

        $invoice = $invoice->fresh();
        $this->assertSame("accepted", $invoice->backup->e_invoice_status);
        $this->assertSame($clearedAt, $invoice->backup->e_invoice_cleared_at);
    }

    public function testPaymentReceivedNotificationRemainsEligibleAfterStorecoveStatusAdvancesBeyondCleared(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();

        $method = new ReflectionMethod(EInvoicePullDocs::class, "recordDocumentStatus");
        $method->setAccessible(true);
        $method->invoke(new EInvoicePullDocs(), $invoice, "cleared");
        $method->invoke(new EInvoicePullDocs(), $invoice->fresh(), "accepted");

        $invoice = $invoice->fresh();
        $this->assertSame("accepted", $invoice->backup->e_invoice_status);
        $this->assertNotNull($invoice->backup->e_invoice_cleared_at);

        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
        $proxy->shouldReceive("submitDocument")->once()->andReturn(["guid" => "notification-storecove-guid"]);
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->fresh()->payment_status);
    }

    public function testFranceEReportingCronIsScheduledDailyAtTenPmParisTime(): void
    {
        $schedule = new Schedule();
        $method = new ReflectionMethod(ConsoleKernel::class, "schedule");
        $method->setAccessible(true);
        $method->invoke(app(ConsoleKernel::class), $schedule);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => $event->description === "france-e-reporting-job");

        $this->assertNotNull($event);
        $this->assertSame("0 22 * * *", $event->expression);
        $this->assertSame("Europe/Paris", $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function testFranceEReportingCronDispatchesMonthlyPaymentReportsAfterTheFormerRecoveryCutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-10-18 22:00:00", "Europe/Paris"));

        try {
            Bus::fake();
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFranceEReport::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceEReportSubmissionSuccessCreatesOnlySubmittedAuditRow(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));

        try {
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();

            $sourceEvent = $this->reportEvents($invoice)->firstOrFail();
            $expectedIdempotencyGuid = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                implode("|", [
                    "fr-e-report",
                    (string) $this->company->company_key,
                    (string) $this->company->id,
                    (string) TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                    "2026-09-30",
                    (string) $sourceEvent->id,
                ]),
            )->toString();

            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
            $proxy->shouldReceive("submitDocument")
                ->once()
                ->with(\Mockery::on(function (array $payload) use ($expectedIdempotencyGuid): bool {
                    return $payload["document"]["documentType"] === "fr_e_report"
                        && $payload["idempotencyGuid"] === $expectedIdempotencyGuid
                        && $payload["tenant_id"] === $this->company->company_key
                        && $payload["account_key"] === $this->company->account->key;
                }))
                ->andReturn(["guid" => "report-storecove-guid"]);
            $storecove->proxy = $proxy;

            (new SubmitFranceEReport(
                $this->company->id,
                TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                "2026-09-30",
                $this->company->db,
            ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

            $submissions = TransactionEvent::query()
                ->where("company_id", $this->company->id)
                ->where("event_id", TransactionEvent::FR_REPORT_SUBMISSION_B2C)
                ->whereDate("period", "2026-09-30")
                ->get();

            $this->assertSame(1, $submissions->count());

            $submission = $submissions->firstOrFail();

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $submission->payment_status);
            $this->assertSame("report-storecove-guid", data_get($submission->payment_request, "guid"));
            $this->assertSame($expectedIdempotencyGuid, data_get($submission->payment_request, "idempotency_guid"));
            $this->assertSame([(int) $sourceEvent->id], data_get($submission->payment_request, "source_event_ids"));
            $this->assertNotNull(data_get($submission->payment_request, "generated_at"));
            $this->assertNotNull(data_get($submission->payment_request, "attempted_at"));
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $sourceEvent->fresh()->payment_status);
            $this->assertFalse($submissions->contains(fn (TransactionEvent $event): bool => $event->payment_status === 2));
            $this->assertFalse(TransactionEvent::query()->whereKey($sourceEvent->id)->where("payment_status", 2)->exists());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testQueuedFranceEReportReturnsWhenFranceReportingIsDisabledBeforeSubmission(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));

        try {
            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();

            $sourceEvent = $this->reportEvents($invoice)->firstOrFail();
            $settings = $this->company->settings;
            $settings->france_reporting_enabled = false;
            $this->company->settings = $settings;
            $this->company->save();

            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldNotReceive("setCompany");
            $proxy->shouldNotReceive("submitDocument");
            $storecove->proxy = $proxy;

            (new SubmitFranceEReport(
                $this->company->id,
                TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                "2026-09-30",
                $this->company->db,
            ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

            $this->assertFalse(TransactionEvent::query()
                ->where("company_id", $this->company->id)
                ->where("event_id", TransactionEvent::FR_REPORT_SUBMISSION_B2C)
                ->whereDate("period", "2026-09-30")
                ->exists());
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $sourceEvent->fresh()->payment_status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testQueuedFranceEReportRemainsPendingWhenTheAccountIsFlagged(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-05');
        $sourceEvent = $this->createFranceReportSourceEvent(
            invoice: $invoice,
            eventId: TransactionEvent::FR_B2C_TRANSACTION,
            period: '2026-09-10',
            reportingData: $this->b2cTransactionReportPayload('2026-09-05'),
        );
        $account = $this->company->account;
        $account->is_flagged = true;
        $account->save();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFranceEReport(
            $this->company->id,
            TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            '2026-09-10',
            $this->company->db,
        ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $sourceEvent->fresh()->payment_status);
        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', TransactionEvent::FR_REPORT_SUBMISSION_B2C)
            ->exists());
    }

    public function testFranceEReportStorecoveExceptionCreatesFailedAuditAndRemainsRetryable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-10-08 22:00:00", "Europe/Paris"));

        try {
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
            $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-05",
            ))->handle();

            $sourceEvent = $this->reportEvents($invoice)->firstOrFail();
            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
            $proxy->shouldReceive("submitDocument")->once()->andThrow(new \RuntimeException("Storecove unavailable"));
            $storecove->proxy = $proxy;

            (new SubmitFranceEReport(
                $this->company->id,
                TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                "2026-09-30",
                $this->company->db,
            ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

            $submission = TransactionEvent::query()
                ->where("company_id", $this->company->id)
                ->where("event_id", TransactionEvent::FR_REPORT_SUBMISSION_B2C)
                ->whereDate("period", "2026-09-30")
                ->firstOrFail();

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $submission->payment_status);
            $this->assertSame("Storecove unavailable", data_get($submission->payment_request, "error.message"));
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $sourceEvent->fresh()->payment_status);
            $this->assertFalse(TransactionEvent::query()
                ->where("company_id", $this->company->id)
                ->where("event_id", TransactionEvent::FR_REPORT_SUBMISSION_B2C)
                ->where("payment_status", 2)
                ->exists());
            $this->assertFalse(TransactionEvent::query()->whereKey($sourceEvent->id)->where("payment_status", 2)->exists());

            Bus::fake();

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                return (int) $this->jobProperty($job, "companyId") === (int) $this->company->id
                    && (int) $this->jobProperty($job, "submissionEventId") === TransactionEvent::FR_REPORT_SUBMISSION_B2C
                    && $this->jobProperty($job, "periodEnd") === "2026-09-30";
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFranceSubmissionWebhookAtomicallyPropagatesStatusAndRetainsHistory(): void
    {
        config(['ninja.db.multi_db_enabled' => false]);
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-05');
        $sourceOne = $this->createFranceReportSourceEvent(
            invoice: $invoice,
            eventId: TransactionEvent::FR_B2C_TRANSACTION,
            period: '2026-09-10',
            reportingData: $this->b2cTransactionReportPayload('2026-09-05'),
        );
        $sourceTwo = $sourceOne->replicate();
        $sourceTwo->event_id = TransactionEvent::FR_B2C_PAYMENT;
        $sourceTwo->save();
        $otherCompany = \App\Models\Company::factory()->create(['account_id' => $this->account->id]);
        $otherCompanySource = $sourceOne->replicate();
        $otherCompanySource->company_id = $otherCompany->id;
        $otherCompanySource->save();
        $submission = $sourceOne->replicate();
        $submission->event_id = TransactionEvent::FR_REPORT_SUBMISSION_B2C;
        $submission->payment_request = [
            'guid' => 'fr-report-webhook-guid',
            'source_event_ids' => [$sourceOne->id, $sourceTwo->id, $otherCompanySource->id],
        ];
        $submission->save();

        (new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'fr-report-webhook-guid',
            'event' => 'rejected',
            'event_group' => 'delivery',
        ]))->handle();

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $submission->fresh()->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $sourceOne->fresh()->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $sourceTwo->fresh()->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $otherCompanySource->fresh()->payment_status);

        (new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'fr-report-webhook-guid',
            'event' => 'accepted',
            'event_group' => 'delivery',
        ]))->handle();

        $submission = $submission->fresh();
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $submission->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $sourceOne->fresh()->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $sourceTwo->fresh()->payment_status);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $otherCompanySource->fresh()->payment_status);
        $this->assertSame('accepted', data_get($submission->payment_request, 'last_event'));
        $this->assertSame(['rejected', 'accepted'], collect(data_get($submission->payment_request, 'events'))
            ->pluck('event')
            ->all());
    }

    public function testPeriodicReportClaimDefersApplicationDateReconciliationDuringTransport(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-01');
        $payment = $this->makePayment($invoice->client, '2026-09-05', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-05');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-05',
        ))->handle();

        $sourceEvent = $this->reportEvents($invoice)->firstOrFail();
        $reconciliationWasDeferred = false;
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive('setCompany')->once()->andReturnSelf();
        $proxy->shouldReceive('submitDocument')->once()->andReturnUsing(function () use (
            $invoice,
            $payment,
            $paymentable,
            &$reconciliationWasDeferred,
        ): array {
            \DB::table('paymentables')
                ->where('id', $paymentable->id)
                ->update(['created_at' => '2026-10-02 00:00:00']);

            try {
                app(FrancePaymentApplicationDateReconciler::class)->reconcile(
                    $invoice->id,
                    $payment->id,
                    '2026-09-05',
                    '2026-10-02',
                    [$paymentable->id],
                );
            } catch (\RuntimeException $exception) {
                $reconciliationWasDeferred = $exception->getMessage() === 'France payment reporting is currently being submitted.';
            }

            return ['guid' => 'claimed-report-storecove-guid'];
        });
        $storecove->proxy = $proxy;

        (new SubmitFranceEReport(
            $this->company->id,
            TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            '2026-09-30',
            $this->company->db,
        ))->handle($storecove, new FranceEReportCompiler(), new FranceEReportPayloadBuilder());

        $sourceEvent = $sourceEvent->fresh();
        $this->assertTrue($reconciliationWasDeferred);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $sourceEvent->payment_status);
        $this->assertSame('2026-09-05', data_get($sourceEvent->payment_request, 'source_date'));
        $this->assertNull(data_get($sourceEvent->payment_request, FranceSubmissionClaim::TOKEN));
        $this->assertNull(data_get($sourceEvent->payment_request, FranceSubmissionClaim::EXPIRES_AT));
    }

    public function testActiveNotificationClaimBlocksMutationsButSubmittedReportingAllowsRefunds(): void
    {
        [$invoice, $payment] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $token = app(FranceSubmissionClaim::class)->claim([$event->id]);
        $this->assertNotNull($token);
        $guard = app(FrancePaymentReportingMutationGuard::class);

        try {
            $guard->assertUserDeletionAllowed($payment);
            $this->fail('An active Storecove submission must block payment deletion.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }

        try {
            $guard->assertInvoiceDeletionAllowed($invoice);
            $this->fail('An active Storecove submission must block invoice deletion.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        }

        try {
            $guard->assertRefundAllowed($payment);
            $this->fail('An active Storecove submission must block payment refunds.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('id', $exception->errors());
        } finally {
            app(FranceSubmissionClaim::class)->release([$event->id], $token);
        }

        $event->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $event->save();

        $payment->refund([
            'id' => $payment->id,
            'amount' => 100,
            'invoices' => [[
                'invoice_id' => $invoice->id,
                'amount' => 100,
            ]],
            'date' => '2026-09-18',
            'gateway_refund' => false,
        ]);

        $this->assertSame(100.0, (float) $payment->fresh()->refunded);
    }

    public function testExpiredSubmissionClaimCanBeReacquired(): void
    {
        [$invoice] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $claims = app(FranceSubmissionClaim::class);
        $firstToken = $claims->claim([$event->id]);
        $this->assertNotNull($firstToken);
        $event = $event->fresh();
        $request = $event->payment_request;
        $request[FranceSubmissionClaim::EXPIRES_AT] = now()->subMinute()->toIso8601String();
        $event->payment_request = $request;
        $event->save();

        $secondToken = $claims->claim([$event->id]);

        $this->assertNotNull($secondToken);
        $this->assertNotSame($firstToken, $secondToken);
        $claims->release([$event->id], $secondToken);
        $this->assertNull(data_get($event->fresh()->payment_request, FranceSubmissionClaim::TOKEN));
    }

    public function testFranceEReportingCronRetriesFailedAndLegacyAttemptsAndSkipsOnlySubmittedAttempts(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-10-08 22:00:00", "Europe/Paris"));

        try {
            config(["ninja.db.multi_db_enabled" => false]);

            $makeAttempt = function (int $submissionStatus, int $sourceStatus): TransactionEvent {
                $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
                $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
                $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
                $invoice = $this->setInvoicePaymentState($invoice, "1200");

                (new RecordFranceEReportingPayment(
                    $payment->id,
                    $this->company->db,
                    $invoice->id,
                    $paymentable->id,
                    "1200",
                    "2026-09-05",
                ))->handle();

                $sourceEvent = $this->reportEvents($invoice)->firstOrFail();
                $sourceEvent->payment_status = $sourceStatus;
                $sourceEvent->save();

                TransactionEvent::create([
                    "company_id" => $this->company->id,
                    "client_id" => $sourceEvent->client_id,
                    "invoice_id" => $sourceEvent->invoice_id,
                    "payment_id" => $sourceEvent->payment_id,
                    "credit_id" => $sourceEvent->credit_id,
                    "event_id" => TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                    "timestamp" => now()->timestamp,
                    "period" => "2026-09-30",
                    "payment_status" => $submissionStatus,
                    "payment_request" => ["source_event_ids" => [$sourceEvent->id]],
                ]);

                return $sourceEvent;
            };

            $makeAttempt(TransactionEvent::FR_REPORTING_STATUS_FAILED, TransactionEvent::FR_REPORTING_STATUS_FAILED);

            Bus::fake();
            (new FranceEReportingCron())->handle();
            Bus::assertDispatched(SubmitFranceEReport::class);

            $makeAttempt(2, TransactionEvent::FR_REPORTING_STATUS_PENDING);

            Bus::fake();
            (new FranceEReportingCron())->handle();
            Bus::assertDispatched(SubmitFranceEReport::class);

            $makeAttempt(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, TransactionEvent::FR_REPORTING_STATUS_PENDING);

            Bus::fake();
            (new FranceEReportingCron())->handle();
            Bus::assertNotDispatched(SubmitFranceEReport::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testSubmitFranceEReportPayloadBuildFailureDoesNotCreateSubmissionAttempt(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "individual", date: "2026-09-01");
        $payment = $this->makePayment($invoice->client, "2026-09-05", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-05");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-05",
        ))->handle();

        $sourceReport = $this->reportEvents($invoice)->firstOrFail();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive("setCompany");
        $proxy->shouldNotReceive("submitDocument");
        $storecove->proxy = $proxy;
        $payloadBuilder = \Mockery::mock(FranceEReportPayloadBuilder::class);
        $payloadBuilder->shouldReceive("build")->once()->andThrow(new \RuntimeException("Payload unavailable"));

        try {
            (new SubmitFranceEReport(
                $this->company->id,
                TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                "2026-09-30",
                $this->company->db,
            ))->handle($storecove, new FranceEReportCompiler(), $payloadBuilder);

            $this->fail("Payload exception was not thrown.");
        } catch (\RuntimeException $exception) {
            $this->assertSame("Payload unavailable", $exception->getMessage());
        }

        $this->assertFalse(TransactionEvent::query()
            ->where("company_id", $this->company->id)
            ->where("event_id", TransactionEvent::FR_REPORT_SUBMISSION_B2C)
            ->where("period", "2026-09-30")
            ->exists());
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $sourceReport->fresh()->payment_status);
    }

    public function testFailedPaymentReceivedNotificationWithoutSkipReasonIsRetriedByCron(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));

        try {
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
            $invoice->backup->guid = "original-storecove-guid";
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();

            $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive("setCompany")->once()->andReturnSelf();
            $proxy->shouldReceive("submitDocument")->once()->andThrow(new \RuntimeException("Storecove unavailable"));
            $storecove->proxy = $proxy;

            (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

            $event = $event->fresh();

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
            $this->assertSame("Storecove unavailable", data_get($event->payment_request, "error.message"));
            $this->assertNull(data_get($event->payment_request, "skip_reason"));

            Bus::fake();

            (new FranceEReportingCron())->handle();

            Bus::assertDispatched(SubmitFrancePaymentReceivedNotification::class, function (SubmitFrancePaymentReceivedNotification $job) use ($event): bool {
                return (int) $this->jobProperty($job, "transactionEventId") === (int) $event->id;
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testSkippedPaymentReceivedNotificationIsNotRetriedByCron(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-09-18 22:00:00", "Europe/Paris"));

        try {
            config(["ninja.db.multi_db_enabled" => false]);

            $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
            $invoice->backup->guid = "original-storecove-guid";
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
            $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
            $invoice = $this->setInvoicePaymentState($invoice, "1200");

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                "1200",
                "2026-09-15",
            ))->handle();

            $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
            $payment->status_id = Payment::STATUS_CANCELLED;
            $payment->save();

            (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle(new Storecove());

            $event = $event->fresh();

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
            $this->assertNotNull(data_get($event->payment_request, "skip_reason"));

            Bus::fake();

            (new FranceEReportingCron())->handle();

            Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testFutureIneligiblePaymentNotificationIsDeferredAndCanSubmitWhenEligibleOnItsApplicationDate(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 22:00:00', 'Europe/Paris'));

        try {
            config(['ninja.db.multi_db_enabled' => false]);
            $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'business', date: '2026-09-01');
            $invoice->backup->guid = 'original-storecove-guid';
            $invoice->save();
            $invoice = $this->markOriginalInvoiceCleared($invoice);
            $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
            $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
            $invoice = $this->setInvoicePaymentState($invoice, '1200');

            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                '1200',
                '2026-09-15',
            ))->handle();

            $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
            $payment->status_id = Payment::STATUS_CANCELLED;
            $payment->save();
            Bus::fake();

            (new FranceEReportingCron())->handle();

            Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);

            (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle(new Storecove());

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $event->fresh()->payment_status);
            $this->assertNull(data_get($event->fresh()->payment_request, 'skip_reason'));

            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 09:00:00', 'Europe/Paris'));
            $storecove = new Storecove();
            $proxy = \Mockery::mock(StorecoveProxy::class);
            $proxy->shouldReceive('setCompany')->once()->andReturnSelf();
            $proxy->shouldReceive('submitDocument')->once()->andReturn(['guid' => 'notification-storecove-guid']);
            $storecove->proxy = $proxy;

            (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->fresh()->payment_status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testPaymentNotificationIsTerminalizedWhenTheOriginalDocumentGuidChanges(): void
    {
        [$invoice, $payment, $paymentable] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $invoice->backup->guid = 'replacement-storecove-guid';
        $invoice->save();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->fresh()->payment_status);
        $this->assertNotNull(data_get($event->fresh()->payment_request, 'skip_reason'));
    }

    public function testQueuedPaymentNotificationRemainsPendingWhenTheAccountIsFlagged(): void
    {
        [$invoice] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $account = $this->company->account;
        $account->is_flagged = true;
        $account->save();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $event->fresh()->payment_status);
        $this->assertNull(data_get($event->fresh()->payment_request, 'skip_reason'));
    }

    public function testSubmittedPaymentNotificationPreventsDuplicateEventsForTheSameDocument(): void
    {
        [$invoice] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $originalPaymentId = $event->payment_id;
        $originalPaymentableId = data_get($event->payment_request, 'paymentable_id');
        $originalSourceDate = data_get($event->payment_request, 'source_date');
        $originalIdempotencyGuid = data_get($event->payment_request, 'idempotency_guid');
        $event->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $event->save();
        $replacementPayment = $this->makePayment($invoice->client, '2026-09-16', '1200');
        $replacementPaymentable = $this->makePaymentable($replacementPayment, $invoice, '1200', '2026-09-16');

        (new RecordFranceEReportingPayment(
            $replacementPayment->id,
            $this->company->db,
            $invoice->id,
            $replacementPaymentable->id,
            '1200',
            '2026-09-16',
        ))->handle();

        $this->assertCount(1, $this->paymentNotificationEvents($invoice));
        $submitted = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $this->assertSame($event->id, $submitted->id);
        $this->assertSame($originalPaymentId, $submitted->payment_id);
        $this->assertSame($originalPaymentableId, data_get($submitted->payment_request, 'paymentable_id'));
        $this->assertSame($originalSourceDate, data_get($submitted->payment_request, 'source_date'));
        $this->assertSame($originalIdempotencyGuid, data_get($submitted->payment_request, 'idempotency_guid'));
    }

    public function testDelayedPartialPaymentJobDoesNotCreateASecondDocumentNotification(): void
    {
        config(['ninja.db.multi_db_enabled' => false]);
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'business', date: '2026-09-01');
        $invoice->backup->guid = 'original-storecove-guid';
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);
        $partialPayment = $this->makePayment($invoice->client, '2026-09-15', '400');
        $partialPaymentable = $this->makePaymentable($partialPayment, $invoice, '400', '2026-09-15');
        $finalPayment = $this->makePayment($invoice->client, '2026-09-20', '800');
        $finalPaymentable = $this->makePaymentable($finalPayment, $invoice, '800', '2026-09-20');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $partialPayment->id,
            $this->company->db,
            $invoice->id,
            $partialPaymentable->id,
            '400',
            '2026-09-15',
        ))->handle();

        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $idempotencyGuid = data_get($event->payment_request, 'idempotency_guid');

        $this->assertCount(1, $this->paymentNotificationEvents($invoice));
        $this->assertSame($finalPayment->id, $event->payment_id);
        $this->assertSame($finalPaymentable->id, data_get($event->payment_request, 'paymentable_id'));
        $this->assertSame('2026-09-20', data_get($event->payment_request, 'source_date'));

        Bus::fake();
        (new FranceEReportingCron())->handle();
        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);

        (new RecordFranceEReportingPayment(
            $finalPayment->id,
            $this->company->db,
            $invoice->id,
            $finalPaymentable->id,
            '800',
            '2026-09-20',
        ))->handle();

        $events = $this->paymentNotificationEvents($invoice);

        $this->assertCount(1, $events);
        $this->assertSame($event->id, $events->first()->id);
        $this->assertSame($idempotencyGuid, data_get($events->first()->payment_request, 'idempotency_guid'));
    }

    public function testPaymentReceivedNotificationSubmissionSkipsWhenPaymentIsNoLongerCompleted(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");
        $invoice->backup->guid = "original-storecove-guid";
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);
        $payment = $this->makePayment($invoice->client, "2026-09-15", "1200");
        $paymentable = $this->makePaymentable($payment, $invoice, "1200", "2026-09-15");
        $invoice = $this->setInvoicePaymentState($invoice, "1200");

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            "1200",
            "2026-09-15",
        ))->handle();

        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $payment->status_id = Payment::STATUS_PENDING;
        $payment->save();

        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive("submitDocument");
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $event = $event->fresh();

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
        $this->assertSame("Payment received notification is no longer eligible.", data_get($event->payment_request, "skip_reason"));
    }

    public function testPaymentNotificationIsTerminalizedWhenThePaymentApplicationIsSoftDeleted(): void
    {
        [$invoice, $payment, $paymentable] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $paymentable->deleteQuietly();

        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->fresh()->payment_status);
        $this->assertNotNull(data_get($event->fresh()->payment_request, 'skip_reason'));
    }

    #[DataProvider('archivableNotificationDependencies')]
    public function testArchivedPaymentOrInvoiceRemainsEligibleForNotification(string $dependency): void
    {
        [$invoice, $payment] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        match ($dependency) {
            'payment' => $payment->deleteQuietly(),
            'invoice' => $invoice->deleteQuietly(),
        };
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive('setCompany')->once()->andReturnSelf();
        $proxy->shouldReceive('submitDocument')->once()->andReturn(['guid' => 'archived-entity-notification-guid']);
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $event->fresh()->payment_status);
        $this->assertNull(data_get($event->fresh()->payment_request, 'skip_reason'));
    }

    #[DataProvider('archivableNotificationDependencies')]
    public function testTrulyDeletedPaymentOrInvoiceIsTerminalized(string $dependency): void
    {
        [$invoice, $payment] = $this->makeDomesticBusinessPaymentNotification();
        $event = $this->paymentNotificationEvents($invoice)->firstOrFail();
        $entity = $dependency === 'payment' ? $payment : $invoice;
        $entity->is_deleted = true;
        $entity->saveQuietly();
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldNotReceive('setCompany');
        $proxy->shouldNotReceive('submitDocument');
        $storecove->proxy = $proxy;

        (new SubmitFrancePaymentReceivedNotification($event->id, $this->company->db))->handle($storecove);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->fresh()->payment_status);
        $this->assertNotNull(data_get($event->fresh()->payment_request, 'skip_reason'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function archivableNotificationDependencies(): array
    {
        return [
            'payment' => ['payment'],
            'invoice' => ['invoice'],
        ];
    }

    public function testKnownPaymentApplicationListeningPointsAreStillWiredToTheFranceRecorder(): void
    {
        $paths = [
            "app/Repositories/PaymentRepository.php",
            "app/Services/Credit/ApplyPayment.php",
            "app/Services/Invoice/MarkPaid.php",
            "app/Services/Invoice/AutoBillInvoice.php",
            "app/Services/Invoice/ApplyPaymentAmount.php",
            "app/Services/Payment/DeletePaymentV2.php",
            "app/Services/Payment/RefundPayment.php",
            "app/Services/Payment/UpdateInvoicePayment.php",
            "app/Services/Payment/PaymentService.php",
            "app/Observers/PaymentObserver.php",
            "app/Jobs/Bank/MatchBankTransactions.php",
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents(base_path($path));

            $this->assertStringContainsString("FrancePaymentApplicationRecorder::class", $contents, "{$path} no longer resolves the France recorder.");
            $this->assertStringContainsString("recordMovement(", $contents, "{$path} no longer records France payment movements.");
            $this->assertStringContainsString("reportableFrTransaction()", $contents, "{$path} no longer performs the primary France reporting gate before invocation.");
            $this->assertStringContainsString("catch (\\Throwable", $contents, "{$path} no longer isolates France recorder failures from the payment path.");
        }
    }

    private function enableFranceStorecoveEInvoicing(): void
    {
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->vat_number = "FR84345678911";
        $settings->id_number = "12345678900012";
        $settings->classification = "business";
        $settings->email = uniqid('testuser') . '@gmail.com';
        $settings->currency_id = "3";
        $settings->e_invoice_type = "PEPPOL";

        $taxData = $this->company->tax_data ?: new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = "FR";
        $taxData->acts_as_sender = true;

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = $this->franceStorecoveLegalEntityId();
        $this->company->e_invoice = $this->francePeppolEInvoiceSettings();
        $this->company->save();

        $account = $this->company->account;
        $account->e_invoice_quota = 100;
        $account->is_flagged = false;
        $account->save();

        $this->company = $this->company->fresh();
    }

    private function franceStorecoveLegalEntityId(): int
    {
        return 1003223;
    }

    private function francePeppolEInvoiceSettings(): \stdClass
    {
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $financialInstitutionBranch = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $financialInstitutionBranch->ID = "BNPAFRPPXXX";

        $payeeFinancialAccount = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = "FR7630006000011234567890189";
        $payeeFinancialAccount->ID = $id;
        $payeeFinancialAccount->Name = "FR-PAYEE";
        $payeeFinancialAccount->FinancialInstitutionBranch = $financialInstitutionBranch;

        $paymentMeans = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $paymentMeans->PayeeFinancialAccount = $payeeFinancialAccount;
        $paymentMeansCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $paymentMeansCode->value = "30";
        $paymentMeans->PaymentMeansCode = $paymentMeansCode;
        $einvoice->PaymentMeans[] = $paymentMeans;

        $settings = new \stdClass();
        $settings->Invoice = $einvoice;

        return $settings;
    }

    private function makeStorecoveFrenchBusinessInvoice(string $date): Invoice
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: $date);
        $client = $invoice->client;
        $client->id_number = "123456987";
        $client->vat_number = "FR82345678911";
        $client->has_valid_vat_number = true;
        $client->save();

        $client->setRelation("company", $this->company);
        $invoice->setRelation("client", $client);
        $invoice->setRelation("company", $this->company);

        return $invoice->fresh();
    }

    private function storecoveForFrenchDiscovery(): Storecove
    {
        $storecove = new Storecove();
        $proxy = \Mockery::mock(StorecoveProxy::class);
        $proxy->shouldReceive("setCompany")->andReturnSelf();
        $proxy->shouldReceive("discovery")->andReturn(true);
        $storecove->proxy = $proxy;

        return $storecove;
    }


    private function enableFranceReporting(string $schedule = 'ten_day'): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = $schedule;
        $settings->currency_id = '3';
        $settings->vat_number = 'FR12345678901';
        $settings->id_number = '12345678900012';
        $settings->e_invoice_type = 'PEPPOL';
        $settings->email = uniqid('testuser') . '@gmail.com';

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeInvoice(string $clientCountry, string $classification, string $date, ?Client $client = null, ?array $lineItems = null): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();
        $client ??= $this->makeClient($country, $classification, $clientCountry);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-PAYMENT-REPORT-'.$clientCountry.'-'.$classification.'-'.uniqid(),
            'date' => $date,
            'due_date' => '2026-10-15',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'status_id' => Invoice::STATUS_SENT,
            'line_items' => $lineItems ?? [$this->makeLineItem()],
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $this->company);
        $invoice->save();

        return $invoice;
    }

    private function makeClient(Country $country, string $classification, string $clientCountry): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'has_valid_vat_number' => false,
            'vat_number' => $clientCountry === 'DE' ? 'DE173755434' : '',
            'name' => 'France Reporting Payment Client',
            'address1' => '987654321',
            'address2' => 'METACORTEX',
            'city' => 'Scala Ritiro',
            'postal_code' => '98152',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'is_primary' => true,
            'send_email' => true,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $client->setRelation('company', $this->company);
        $client->setRelation('contacts', collect([$contact]));
        $client->setRelation('country', $country);

        return $client;
    }

    private function makeLineItem(): object
    {
        return $this->makeLineItemWithTax('CONSULTING', 500, 'VAT', 20, 2);
    }

    private function makeLineItemWithTax(
        string $productKey,
        int|float $cost,
        string $taxName,
        int|float $taxRate,
        int|float $quantity = 1,
        int $typeId = Product::PRODUCT_TYPE_SERVICE,
    ): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->tax_name1 = $taxName;
        $item->tax_rate1 = $taxRate;
        $item->tax_id = (string) Product::PRODUCT_TYPE_OVERRIDE_TAX;
        $item->product_key = $productKey;
        $item->notes = 'Consulting services';
        $item->type_id = (string) $typeId;

        return $item;
    }

    private function makePayment(Client $client, string $date, string $amount): Payment
    {
        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => $amount,
            'applied' => $amount,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $date,
            'currency_id' => 3,
        ]);

        $payment->setRelation('client', $client);
        $payment->setRelation('company', $this->company);

        return $payment;
    }

    private function makePaymentable(Payment $payment, Invoice $invoice, string $amount, string $date): Paymentable
    {
        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = $amount;
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime($date);
        $paymentable->updated_at = strtotime($date);
        $paymentable->save();

        return Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->latest('id')
            ->firstOrFail();
    }

    private function setInvoicePaymentState(Invoice $invoice, string $paidToDate): Invoice
    {
        $invoice->paid_to_date = $paidToDate;
        $invoice->balance = round((float) $invoice->amount - (float) $paidToDate, 2);
        $invoice->status_id = $invoice->balance <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIAL;
        $invoice->save();

        return $invoice->fresh();
    }

    private function movementEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_PAYMENT)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->orderBy('id')
            ->get();
    }

    private function reportEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_PAYMENT)
            ->whereIn('payment_status', [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->orderBy('id')
            ->get();
    }

    private function paymentNotificationEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: Invoice, 1: Payment, 2: Paymentable}
     */
    private function makeDomesticBusinessPaymentNotification(): array
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'business', date: '2026-09-01');
        $invoice->backup->guid = 'original-storecove-guid';
        $invoice->save();
        $invoice = $this->markOriginalInvoiceCleared($invoice);
        $payment = $this->makePayment($invoice->client, '2026-09-15', '1200');
        $paymentable = $this->makePaymentable($payment, $invoice, '1200', '2026-09-15');
        $invoice = $this->setInvoicePaymentState($invoice, '1200');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '1200',
            '2026-09-15',
        ))->handle();

        return [$invoice, $payment, $paymentable];
    }

    private function markOriginalInvoiceCleared(Invoice $invoice): Invoice
    {
        $invoice->backup->e_invoice_status = "cleared";
        $invoice->backup->e_invoice_cleared_at = now()->toIso8601String();
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * @param array<string, mixed> $reportingData
     */
    private function createFranceReportSourceEvent(Invoice $invoice, int $eventId, string $period, array $reportingData): TransactionEvent
    {
        return TransactionEvent::create([
            "company_id" => $this->company->id,
            "client_id" => $invoice->client_id,
            "invoice_id" => $invoice->id,
            "payment_id" => 0,
            "credit_id" => 0,
            "client_balance" => $invoice->client->balance ?? 0,
            "client_paid_to_date" => $invoice->client->paid_to_date ?? 0,
            "client_credit_balance" => $invoice->client->credit_balance ?? 0,
            "invoice_balance" => $invoice->balance ?? 0,
            "invoice_amount" => $invoice->amount ?? 0,
            "invoice_partial" => $invoice->partial ?? 0,
            "invoice_paid_to_date" => $invoice->paid_to_date ?? 0,
            "invoice_status" => $invoice->status_id,
            "event_id" => $eventId,
            "timestamp" => now()->timestamp,
            "period" => $period,
            "payment_status" => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            "reporting_data" => $reportingData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function b2cTransactionReportPayload(string $date): array
    {
        return [
            "date" => $date,
            "category" => "TLB1",
            "currency" => "EUR",
            "amountExcludingVat" => "1000",
            "amountIncludingVat" => "1200",
            "transactionsCount" => 1,
            "vatPaymentOption" => "customer",
            "taxSubtotals" => [
                [
                    "category" => "standard",
                    "percentage" => "20",
                    "taxableAmount" => "1000",
                    "taxAmount" => "200",
                    "currency" => "EUR",
                    "country" => "FR",
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2biInvoiceReportPayload(Invoice $invoice, string $date): array
    {
        return [
            "invoiceNumber" => (string) $invoice->number,
            "issueDate" => $date,
            "documentCurrency" => "EUR",
            "amountIncludingVat" => "1200",
            "taxSubtotals" => [
                [
                    "category" => "standard",
                    "percentage" => "20",
                    "taxableAmount" => "1000",
                    "taxAmount" => "200",
                    "currency" => "EUR",
                    "country" => "FR",
                ],
            ],
            "invoiceLines" => [
                [
                    "description" => "Consulting services",
                    "amountExcludingVat" => "1000",
                    "tax" => [
                        "percentage" => "20",
                        "category" => "standard",
                        "country" => "FR",
                    ],
                ],
            ],
        ];
    }

    private function jobProperty(object $job, string $property): mixed
    {
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($job);
    }

}
