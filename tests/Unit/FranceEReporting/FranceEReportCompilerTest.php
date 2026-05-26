<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FranceEReportCompilerTest extends TestCase
{
    public function testItCompilesB2CTransactionAndPaymentFragmentsIntoAStorecoveReportPayload(): void
    {
        $company = $this->company();
        $issuedAt = CarbonImmutable::parse('2025-10-01 12:12:12 +0100');
        $events = [
            $this->event(1, TransactionEvent::FR_B2C_TRANSACTION, $this->b2cTransactionPayload()),
            $this->event(2, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPaymentPayload()),
        ];

        $report = (new FranceEReportCompiler())->compileFromEvents(
            company: $company,
            submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            periodEnd: '2025-09-30',
            events: $events,
            issuedAt: $issuedAt,
            documentId: 'REPORT31_015',
        );
        $payload = (new FranceEReportPayloadBuilder())->build($company, $report);
        $artifactPath = base_path('tests/artifacts/fr_report_compiled_b2c_storecove_payload.json');

        $this->writeJsonArtifact($artifactPath, $payload);

        $this->assertSame('fr_e_report', $payload['document']['documentType']);
        $this->assertSame('REPORT31_015', $payload['document']['frEReport']['documentId']);
        $this->assertSame('IN', $payload['document']['frEReport']['typeCode']);
        $this->assertSame('2025-09-01 - 2025-09-30', $payload['document']['frEReport']['transactionReport']['period']);
        $this->assertEquals([$this->b2cTransactionPayload()], $payload['document']['frEReport']['transactionReport']['b2cTransactions']);
        $this->assertSame([], $payload['document']['frEReport']['transactionReport']['b2biInvoices']);
        $this->assertEquals([$this->b2cPaymentPayload()], $payload['document']['frEReport']['paymentReport']['b2cPayments']);
        $this->assertSame([], $payload['document']['frEReport']['paymentReport']['b2biPayments']);
        $this->assertArrayNotHasKey('frReportEntry', $payload['document']['frEReport']);
        $this->assertFileExists($artifactPath);
    }

    public function testItCompilesVatExcludedTransactionAndPaymentFragmentsIntoAStorecoveReportPayload(): void
    {
        $company = $this->company();
        $issuedAt = CarbonImmutable::parse('2025-11-10 09:00:00 +0100');
        $events = [
            $this->event(1, TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoicePayload()),
            $this->event(2, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPaymentPayload()),
        ];

        $payload = (new FranceEReportPayloadBuilder())->build(
            $company,
            (new FranceEReportCompiler())->compileFromEvents(
                company: $company,
                submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
                periodEnd: '2025-10-31',
                events: $events,
                issuedAt: $issuedAt,
                documentId: 'REPORT-VAT-EXCLUDED-001',
            ),
        );

        $this->assertSame('2025-09-01 - 2025-10-31', $payload['document']['frEReport']['transactionReport']['period']);
        $this->assertEquals([$this->b2biInvoicePayload()], $payload['document']['frEReport']['transactionReport']['b2biInvoices']);
        $this->assertEquals([$this->b2biPaymentPayload()], $payload['document']['frEReport']['paymentReport']['b2biPayments']);
        $this->assertSame([], $payload['document']['frEReport']['transactionReport']['b2cTransactions']);
        $this->assertSame([], $payload['document']['frEReport']['paymentReport']['b2cPayments']);
    }

    public function testCorrectiveSubmissionsAreRectificativePaymentReports(): void
    {
        $company = $this->company();
        $issuedAt = CarbonImmutable::parse('2025-10-02 09:00:00 +0100');

        $report = (new FranceEReportCompiler())->compileFromEvents(
            company: $company,
            submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE,
            periodEnd: '2025-09-30',
            events: [$this->event(1, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPaymentPayload())],
            issuedAt: $issuedAt,
            documentId: 'REPORT-CORRECTIVE-001',
        );

        $this->assertSame('RE', $report->typeCode);
        $this->assertNull($report->transactionReport);
        $this->assertNotNull($report->paymentReport);
    }

    private function company(): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            'id' => 42,
            'company_key' => 'fr-report-company-key',
            'legal_entity_id' => -1,
        ], true);

        $settings = CompanySettings::defaults();
        $settings->name = 'LEVENDEURC3';
        $settings->id_number = '35202215400000';
        $settings->vat_number = 'FR99352022154';
        $settings->france_reporting_schedule = 'monthly';
        $settings->country_id = '73';

        $company->settings = $settings;

        return $company;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function event(int $id, int $eventId, array $payload): TransactionEvent
    {
        $event = new TransactionEvent();
        $event->setRawAttributes([
            'id' => $id,
            'event_id' => $eventId,
            'period' => '2025-09-30',
            'reporting_data' => json_encode($payload, JSON_THROW_ON_ERROR),
        ], true);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function b2biInvoicePayload(): array
    {
        return [
            'invoiceNumber' => 'S1F1_REPORT2025_001',
            'issueDate' => '2025-09-01',
            'dueDate' => '2025-09-30',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => 12000,
            'taxSubtotals' => [
                [
                    'taxCategory' => 'standard',
                    'percentage' => 20,
                    'taxableAmount' => 10000,
                    'taxAmount' => 2000,
                    'country' => 'FR',
                ],
            ],
            'accountingSupplierParty' => [
                'party' => [
                    'companyName' => 'LEVENDEURC3',
                    'address' => [
                        'country' => 'FR',
                    ],
                ],
                'publicIdentifiers' => [
                    [
                        'scheme' => 'FR:SIRENE',
                        'id' => '352022154',
                    ],
                ],
            ],
            'accountingCustomerParty' => [
                'party' => [
                    'companyName' => 'METACORTEX',
                    'address' => [
                        'country' => 'IT',
                    ],
                ],
                'publicIdentifiers' => [
                    [
                        'scheme' => 'IT:VAT',
                        'id' => 'IT00987654321',
                    ],
                ],
            ],
            'invoiceLines' => [
                [
                    'description' => 'Bien 1',
                    'amountExcludingVat' => 10000,
                    'tax' => [
                        'percentage' => 20,
                        'category' => 'standard',
                        'country' => 'FR',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2cTransactionPayload(): array
    {
        return [
            'date' => '2025-09-22',
            'currency' => 'EUR',
            'vatPaymentOption' => 'customer',
            'category' => 'TLB1',
            'amountExcludingVat' => 10000,
            'amountIncludingVat' => 12000,
            'transactionsCount' => 100,
            'taxSubtotals' => [
                [
                    'category' => 'standard',
                    'percentage' => 20,
                    'taxableAmount' => 10000,
                    'taxAmount' => 2000,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2biPaymentPayload(): array
    {
        return [
            'invoiceNumber' => 'S2F3_REPORT2025_xxx',
            'issueDate' => '2025-09-03',
            'paymentDate' => '2025-09-22',
            'taxSubtotals' => [
                [
                    'percentage' => 20,
                    'category' => 'standard',
                    'currency' => 'EUR',
                    'country' => 'FR',
                    'amountIncludingTax' => 30000,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2cPaymentPayload(): array
    {
        return [
            'date' => '2025-09-25',
            'taxSubtotal' => [
                [
                    'category' => 'standard',
                    'percentage' => 20,
                    'taxableAmount' => 10000,
                    'taxAmount' => 2000,
                    'currency' => 'EUR',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function writeJsonArtifact(string $path, array $artifact): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}