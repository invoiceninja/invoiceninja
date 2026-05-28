<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Models\Company;
use App\Models\TransactionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SubmitFranceEReportTest extends TestCase
{
    public function testSubmitJobStillCreatesACompiledSubmissionBeforeCallingStorecove(): void
    {
        $job = new SubmitFranceEReport(
            companyId: 1,
            submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            periodEnd: "2026-09-10",
            db: "db-test",
        );
        $method = new ReflectionMethod($job, "createSubmissionEvent");
        $method->setAccessible(true);
        $company = new Company();
        $company->setRawAttributes(["id" => 1], true);
        $submission = null;

        DB::connection()->pretend(function () use ($method, $job, $company, &$submission): void {
            $submission = $method->invoke(
                $job,
                $company,
                $this->report(),
                [101],
                CarbonImmutable::parse("2026-09-10 12:00:00", "Europe/Paris"),
            );
        });

        $source = file_get_contents(app_path("Jobs/EDocument/SubmitFranceEReport.php"));

        $this->assertInstanceOf(TransactionEvent::class, $submission);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_COMPILED, $submission->payment_status);
        $this->assertSame([101], data_get($submission->payment_request, "source_event_ids"));
        $this->assertStringContainsString(
            "'payment_status' => TransactionEvent::FR_REPORTING_STATUS_COMPILED",
            $source,
        );
        $this->assertMatchesRegularExpression(
            '/\$submission = \$this->createSubmissionEvent\(.*?\$payload = \$payloadBuilder->build\(.*?\$response = \$storecove->proxy/s',
            $source,
        );
        $this->assertSame(2, TransactionEvent::FR_REPORTING_STATUS_COMPILED);
    }

    private function report(): FRReportData
    {
        return FRReportData::initialPaymentReport(
            documentId: "REPORT-001",
            issueDate: "2026-09-10",
            issueTime: "12:00:00",
            timeZone: "Europe/Paris",
            paymentReport: new PaymentReportData(
                period: "2026-09-01 - 2026-09-10",
                b2cPayments: [
                    new B2CPaymentData(
                        date: "2026-09-05",
                        taxSubtotal: [
                            new TaxSubtotalData(
                                percentage: 20,
                                category: "standard",
                                taxableAmount: "1000",
                                taxAmount: "200",
                                currency: "EUR",
                            ),
                        ],
                    ),
                ],
            ),
        );
    }
}
