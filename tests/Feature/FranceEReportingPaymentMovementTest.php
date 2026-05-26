<?php

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Repositories\PaymentRepository;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use Faker\Factory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceEReportingPaymentMovementTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->makeTestData();
        $this->enableFranceReporting();
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
        $this->assertSame('2026-10-10', $report->period->toDateString());
        $this->assertSame('initial', data_get($report->payment_request, 'fr_report_kind'));
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertCount(2, data_get($report->payment_request, 'source_event_ids'));
        $this->assertSame([(int) $report->id, (int) $report->id], $movements->map(fn (TransactionEvent $movement): int => (int) data_get($movement->payment_request, 'report_event_id'))->all());
        $this->assertSame('2026-10-02', $report->reporting_data->frReportEntry->b2cPayment->date);
        $this->assertSame('1000', $report->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->taxableAmount);
        $this->assertSame('200', $report->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->taxAmount);
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
        $initialSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-09-20');

        $this->assertTrue($initialSources->contains('id', $initialReport->id));
        $this->assertFalse($initialSources->contains(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT));

        $initialReport->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initialReport->save();

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
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        ))->handle();

        $correctiveReport = $this->reportEvents($invoice)
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE);

        $this->assertNotNull($correctiveReport);
        $this->assertSame('2026-10-20', $correctiveReport->period->toDateString());
        $this->assertSame(-200.0, (float) $correctiveReport->payment_applied);
        $this->assertSame((int) $initialReport->id, (int) data_get($correctiveReport->payment_request, 'previous_event_id'));
        $this->assertSame('2026-10-12', $correctiveReport->reporting_data->frReportEntry->b2cPayment->date);
        $this->assertStringStartsWith('-', (string) $correctiveReport->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->taxableAmount);

        $correctiveSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE, '2026-10-20');
        $initialSourcesForCorrectionPeriod = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-10-20');

        $this->assertTrue($correctiveSources->contains('id', $correctiveReport->id));
        $this->assertFalse($initialSourcesForCorrectionPeriod->contains('id', $correctiveReport->id));
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

        $this->assertSame(0, TransactionEvent::query()->where("invoice_id", $invoice->id)->count());
    }

    public function testItRecordsForeignBusinessPaymentsAsVatExcludedBiMonthlyReports(): void
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
            ->where("invoice_id", $invoice->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->firstOrFail();

        $report = TransactionEvent::query()
            ->where("invoice_id", $invoice->id)
            ->where("event_id", TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_PENDING)
            ->firstOrFail();

        $this->assertSame(RecordFranceEReportingPayment::KIND_MOVEMENT, data_get($movement->payment_request, "fr_kind"));
        $this->assertSame("2026-10-31", $report->period->toDateString());
        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertSame("initial", data_get($report->payment_request, "fr_report_kind"));
        $this->assertSame("2026-09-15", $report->reporting_data->frReportEntry->b2biPayment->paymentDate);
        $this->assertSame("1200", $report->reporting_data->frReportEntry->b2biPayment->taxSubtotals[0]->amountIncludingTax);

        $compiler = new FranceEReportCompiler();
        $vatExcludedSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED, "2026-10-31");
        $b2cSources = $compiler->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, "2026-10-31");

        $this->assertTrue($vatExcludedSources->contains("id", $report->id));
        $this->assertFalse($b2cSources->contains("id", $report->id));
    }

    public function testRefundBeforeSubmissionUpdatesThePendingPaymentReport(): void
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

        $initialReportId = $this->reportEvents($invoice)->firstOrFail()->id;

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

        $reports = $this->reportEvents($invoice);
        $report = $reports->firstOrFail();

        $this->assertSame(1, $reports->count());
        $this->assertSame((int) $initialReportId, (int) $report->id);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $report->payment_status);
        $this->assertSame(1000.0, (float) $report->payment_applied);
        $this->assertSame("2026-09-18", data_get($report->payment_request, "source_date"));
        $this->assertSame("initial", data_get($report->payment_request, "fr_report_kind"));
        $this->assertCount(2, data_get($report->payment_request, "source_event_ids"));
        $this->assertSame("2026-09-18", $report->reporting_data->frReportEntry->b2cPayment->date);
        $this->assertSame("833.33", $report->reporting_data->frReportEntry->b2cPayment->taxSubtotal[0]->taxableAmount);
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
        $this->assertSame("2026-10-20", $correctiveReport->period->toDateString());
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

        $this->assertSame("2026-10-10", $movement->period->toDateString());
        $this->assertSame("2026-10-10", $report->period->toDateString());
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

    public function testPaymentRepositoryDoesNotInvokeFranceRecorderForDomesticFrenchBusinessInvoices(): void
    {
        $invoice = $this->makeInvoice(clientCountry: "FR", classification: "business", date: "2026-09-01");

        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldNotReceive("recordMovement");
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

        $this->assertSame(0, TransactionEvent::query()->where("invoice_id", $invoice->id)->count());
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


    private function enableFranceReporting(string $schedule = 'ten_days'): void
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
        $settings->email = $this->faker->safeEmail();

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeInvoice(string $clientCountry, string $classification, string $date): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();
        $client = $this->makeClient($country, $classification, $clientCountry);
        $item = $this->makeLineItem();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-PAYMENT-REPORT-'.$clientCountry.'-'.$classification,
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
            'line_items' => [$item],
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
            'email' => $this->faker->safeEmail(),
        ]);

        $client->setRelation('company', $this->company);
        $client->setRelation('contacts', collect([$contact]));
        $client->setRelation('country', $country);

        return $client;
    }

    private function makeLineItem(): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 500;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->product_key = 'CONSULTING';
        $item->notes = 'Consulting services';

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

        return $paymentable;
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
}
