<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Models\Country;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Generates the final JSON request bodies passed to Storecove and assesses
 * them against Storecove's public France F10 JSON contract.
 *
 * Covered Storecove scenarios:
 * - 3.1: initial transaction reports;
 * - 3.2: initial payment reports;
 * - 3.3: rectificative payment reports.
 *
 * The two mixed-source artifacts intentionally exercise the current compiler
 * behaviour when transaction and payment rows share a submission bucket. They
 * are expected to expose rule G6.29 rather than silently treating that shape
 * as a valid Storecove request.
 */
class FranceEReportArtifactAssessmentTest extends TestCase
{
    private const ARTIFACT_DIRECTORY = 'tests/artifacts/france_e_reporting';

    private const STORECOVE_SPEC_URL = 'https://www.storecove.com/docs/#_openapi_frereport';

    private const STORECOVE_FRANCE_URL = 'https://www.storecove.com/docs/#_f10_e_reporting_documenttype_and_frereport_e_report';

    protected function setUp(): void
    {
        parent::setUp();

        $france = new Country();
        $france->setRawAttributes([
            'id' => 73,
            'iso_3166_2' => 'FR',
            'name' => 'France',
        ], true);

        app()->instance('countries', collect([$france]));
    }

    public function testItGeneratesAndAssessesEveryApplicableStorecoveF10ReportAcrossMultipleScenarios(): void
    {
        $company = $this->company();
        $issuedAt = CarbonImmutable::parse('2025-10-01 12:12:12 +0100');
        $compiler = new FranceEReportCompiler();
        $builder = new FranceEReportPayloadBuilder();

        $scenarios = [
            'f10_31_b2c_transactions' => [
                'storecoveScenario' => '3.1 initial transaction report',
                'scope' => 'B2C',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT31-B2C-001',
                    events: [
                        $this->event(1, TransactionEvent::FR_B2C_TRANSACTION, $this->b2cGoodsTransaction()),
                        $this->event(2, TransactionEvent::FR_B2C_TRANSACTION, $this->b2cServicesTransaction()),
                    ],
                ),
            ],
            'f10_31_vat_excluded_transactions' => [
                'storecoveScenario' => '3.1 initial transaction report',
                'scope' => 'VAT-excluded B2BI',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT31-B2BI-01',
                    events: [
                        $this->event(3, TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoice()),
                        $this->event(4, TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biCredit()),
                    ],
                ),
            ],
            'f10_32_b2c_payments' => [
                'storecoveScenario' => '3.2 initial payment report',
                'scope' => 'B2C',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT32-B2C-001',
                    events: [
                        $this->event(5, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPayment('2025-09-16', 12000, 20)),
                        $this->event(6, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPayment('2025-09-22', 6050, 10)),
                    ],
                ),
            ],
            'f10_32_vat_excluded_payments' => [
                'storecoveScenario' => '3.2 initial payment report',
                'scope' => 'VAT-excluded B2BI',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT32-B2BI-01',
                    events: [
                        $this->event(7, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPayment('S1F1_REPORT2025_001', '2025-09-16', 12000, 20)),
                        $this->event(8, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPayment('S1F2_REPORT2025_001', '2025-09-22', 6050, 10)),
                    ],
                ),
            ],
            'f10_33_b2c_rectificative_payments' => [
                'storecoveScenario' => '3.3 rectificative payment report',
                'scope' => 'B2C',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT33-B2C-001',
                    events: [
                        $this->event(9, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPayment('2025-09-16', 11800, 20)),
                        $this->event(10, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPayment('2025-09-22', 6000, 10)),
                    ],
                ),
            ],
            'f10_33_vat_excluded_rectificative_payments' => [
                'storecoveScenario' => '3.3 rectificative payment report',
                'scope' => 'VAT-excluded B2BI',
                'expectedValid' => true,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE,
                    issuedAt: $issuedAt,
                    documentId: 'REPORT33-B2BI-01',
                    events: [
                        $this->event(11, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPayment('S1F1_REPORT2025_001', '2025-09-16', 11800, 20)),
                        $this->event(12, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPayment('S1F2_REPORT2025_001', '2025-09-22', 6000, 10)),
                    ],
                ),
            ],
            'current_mixed_b2c_submission' => [
                'storecoveScenario' => 'current mixed transaction/payment bucket',
                'scope' => 'B2C',
                'expectedValid' => false,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_B2C,
                    issuedAt: $issuedAt,
                    documentId: 'MIXED-B2C-001',
                    events: [
                        $this->event(13, TransactionEvent::FR_B2C_TRANSACTION, $this->b2cGoodsTransaction()),
                        $this->event(14, TransactionEvent::FR_B2C_PAYMENT, $this->b2cPayment('2025-09-22', 12000, 20)),
                    ],
                ),
            ],
            'current_mixed_vat_excluded_submission' => [
                'storecoveScenario' => 'current mixed transaction/payment bucket',
                'scope' => 'VAT-excluded B2BI',
                'expectedValid' => false,
                'payload' => $this->compilePayload(
                    compiler: $compiler,
                    builder: $builder,
                    company: $company,
                    submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
                    issuedAt: $issuedAt,
                    documentId: 'MIXED-B2BI-001',
                    events: [
                        $this->event(15, TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoice()),
                        $this->event(16, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPayment('S1F1_REPORT2025_001', '2025-09-22', 12000, 20)),
                    ],
                ),
            ],
        ];

        $assessment = [
            'assessedAgainst' => [
                'storecoveF10JsonContract' => self::STORECOVE_SPEC_URL,
                'storecoveFranceF10Rules' => self::STORECOVE_FRANCE_URL,
                'contractSnapshotDate' => '2026-08-08',
            ],
            'summary' => [
                'artifacts' => count($scenarios),
                'pass' => 0,
                'passWithWarnings' => 0,
                'fail' => 0,
            ],
            'artifacts' => [],
        ];

        foreach ($scenarios as $name => $scenario) {
            $payload = $scenario['payload'];
            $result = $this->assessStorecoveF10Payload($payload);
            $artifactFilename = $name.'.json';

            $this->writeJsonArtifact($artifactFilename, $payload);

            $assessment['summary'][$this->summaryKey($result['status'])]++;
            $assessment['artifacts'][] = [
                'file' => $artifactFilename,
                'storecoveScenario' => $scenario['storecoveScenario'],
                'scope' => $scenario['scope'],
                ...$result,
            ];

            $this->assertSame(
                $scenario['expectedValid'],
                $result['status'] !== 'fail',
                $artifactFilename,
            );
            $this->assertFileExists(base_path(self::ARTIFACT_DIRECTORY.'/'.$artifactFilename));
            $this->assertSame(
                $payload,
                json_decode(
                    file_get_contents(base_path(self::ARTIFACT_DIRECTORY.'/'.$artifactFilename)),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );
        }

        $this->writeJsonArtifact('assessment.json', $assessment);

        $this->assertSame(8, $assessment['summary']['artifacts']);
        $this->assertSame(6, $assessment['summary']['pass'] + $assessment['summary']['passWithWarnings']);
        $this->assertSame(2, $assessment['summary']['fail']);
        $this->assertSame(
            ['[G6.29] Exactly one of transactionReport or paymentReport must be present.'],
            $assessment['artifacts'][6]['errors'],
        );
        $this->assertSame(
            ['[G6.29] Exactly one of transactionReport or paymentReport must be present.'],
            $assessment['artifacts'][7]['errors'],
        );
    }

    /**
     * @param array<int, TransactionEvent> $events
     * @return array<string, mixed>
     */
    private function compilePayload(
        FranceEReportCompiler $compiler,
        FranceEReportPayloadBuilder $builder,
        Company $company,
        int $submissionEventId,
        CarbonImmutable $issuedAt,
        string $documentId,
        array $events,
    ): array {
        $report = $compiler->compileFromEvents(
            company: $company,
            submissionEventId: $submissionEventId,
            periodEnd: '2025-09-30',
            events: $events,
            issuedAt: $issuedAt,
            documentId: $documentId,
        );

        return $builder->build($company, $report);
    }

    private function company(): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            'id' => 42,
            'company_key' => 'fr-report-artifact-company',
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
    private function b2biInvoice(): array
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
            'accountingSupplierParty' => $this->supplierParty(),
            'accountingCustomerParty' => $this->customerParty(),
            'invoiceLines' => [
                [
                    'description' => 'Cross-border consulting services',
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
    private function b2biCredit(): array
    {
        return [
            'invoiceNumber' => 'S1F2_CREDIT2025_01',
            'issueDate' => '2025-09-18',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => -6050,
            'taxSubtotals' => [
                [
                    'taxCategory' => 'standard',
                    'percentage' => 10,
                    'taxableAmount' => -5500,
                    'taxAmount' => -550,
                    'country' => 'FR',
                ],
            ],
            'accountingSupplierParty' => $this->supplierParty(),
            'accountingCustomerParty' => $this->customerParty(),
            'invoiceLines' => [
                [
                    'description' => 'Cross-border service credit',
                    'amountExcludingVat' => -5500,
                    'tax' => [
                        'percentage' => 10,
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
    private function supplierParty(): array
    {
        return [
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
                [
                    'scheme' => 'FR:VAT',
                    'id' => 'FR99352022154',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerParty(): array
    {
        return [
            'party' => [
                'companyName' => 'METACORTEX',
                'address' => [
                    'street1' => '987654321',
                    'street2' => 'METACORTEX',
                    'zip' => '98152',
                    'city' => 'Scala Ritiro',
                    'country' => 'IT',
                ],
            ],
            'publicIdentifiers' => [
                [
                    'scheme' => 'IT:VAT',
                    'id' => 'IT00987654321',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2cGoodsTransaction(): array
    {
        return [
            'date' => '2025-09-16',
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
    private function b2cServicesTransaction(): array
    {
        return [
            'date' => '2025-09-22',
            'currency' => 'EUR',
            'vatPaymentOption' => 'supplier',
            'category' => 'TPS1',
            'amountExcludingVat' => 5500,
            'amountIncludingVat' => 6050,
            'transactionsCount' => 25,
            'taxSubtotals' => [
                [
                    'category' => 'standard',
                    'percentage' => 10,
                    'taxableAmount' => 5500,
                    'taxAmount' => 550,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2biPayment(string $invoiceNumber, string $paymentDate, int $amount, int $percentage): array
    {
        return [
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => '2025-09-03',
            'paymentDate' => $paymentDate,
            'paymentMeansCode' => '30',
            'taxSubtotals' => [
                [
                    'percentage' => $percentage,
                    'category' => 'standard',
                    'currency' => 'EUR',
                    'country' => 'FR',
                    'amountIncludingTax' => $amount,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function b2cPayment(string $date, int $amount, int $percentage): array
    {
        return [
            'date' => $date,
            'taxSubtotal' => [
                [
                    'category' => 'standard',
                    'percentage' => $percentage,
                    'country' => 'FR',
                    'currency' => 'EUR',
                    'amount' => $amount,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, errors: array<int, string>, warnings: array<int, string>}
     */
    private function assessStorecoveF10Payload(array $payload): array
    {
        $errors = [];
        $warnings = [];

        if (! is_int($payload['legalEntityId'] ?? null)) {
            $errors[] = 'legalEntityId must be an integer.';
        }

        if (data_get($payload, 'document.documentType') !== 'fr_e_report') {
            $errors[] = 'document.documentType must be fr_e_report.';
        }

        $report = data_get($payload, 'document.frEReport');

        if (! is_array($report)) {
            return [
                'status' => 'fail',
                'errors' => [...$errors, 'document.frEReport is required.'],
                'warnings' => $warnings,
            ];
        }

        foreach (['typeCode', 'documentId', 'issueDate', 'issueTime', 'timeZone', 'declarantParty'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $report)) {
                $errors[] = "frEReport.{$requiredKey} is required.";
            }
        }

        if (! in_array($report['typeCode'] ?? null, ['IN', 'RE'], true)) {
            $errors[] = 'frEReport.typeCode must be IN or RE.';
        }

        if (! is_string($report['documentId'] ?? null) || trim($report['documentId']) === '') {
            $errors[] = 'frEReport.documentId must be a non-empty string.';
        } elseif (mb_strlen($report['documentId']) > 20) {
            $errors[] = 'frEReport.documentId exceeds the current PPF 20-character limit.';
        }

        $this->assessDate($report['issueDate'] ?? null, 'frEReport.issueDate', $errors);

        if (! is_string($report['issueTime'] ?? null)
            || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $report['issueTime'])) {
            $errors[] = 'frEReport.issueTime must use valid HH:MM:SS format.';
        }

        if (! is_string($report['timeZone'] ?? null)
            || ! preg_match('/^[+-](?:0\d|1[0-4])[0-5]\d$/', $report['timeZone'])) {
            $errors[] = 'frEReport.timeZone must use a valid +zzzz or -zzzz offset.';
        }

        $identifiers = data_get($report, 'declarantParty.publicIdentifiers');

        if (! is_array($identifiers) || $identifiers === [] || ! array_is_list($identifiers)) {
            $errors[] = 'frEReport.declarantParty.publicIdentifiers requires a non-empty list.';
        } else {
            foreach ($identifiers as $index => $identifier) {
                if (! is_array($identifier)
                    || ! is_string($identifier['scheme'] ?? null)
                    || ! is_string($identifier['id'] ?? null)
                    || trim($identifier['id']) === '') {
                    $errors[] = "frEReport.declarantParty.publicIdentifiers.{$index} requires scheme and id.";
                }
            }
        }

        $sectionCount = (int) array_key_exists('transactionReport', $report)
            + (int) array_key_exists('paymentReport', $report);

        if ($sectionCount !== 1) {
            $errors[] = '[G6.29] Exactly one of transactionReport or paymentReport must be present.';
        }

        if (isset($report['transactionReport']) && is_array($report['transactionReport'])) {
            $this->assessTransactionReport($report['transactionReport'], $errors);
        }

        if (isset($report['paymentReport']) && is_array($report['paymentReport'])) {
            $this->assessPaymentReport($report['paymentReport'], $errors);
        }

        if (($report['typeCode'] ?? null) === 'RE' && ! array_key_exists('paymentReport', $report)) {
            $errors[] = 'Storecove F10 scenario 3.3 requires a rectificative paymentReport.';
        }

        if (array_key_exists('schemaVersion', $report)) {
            $warnings[] = 'frEReport.schemaVersion is an Invoice Ninja storage field and is not part of Storecove\'s documented F10 JSON model.';
        }

        if (is_array(data_get($report, 'declarantParty.party.address'))) {
            $warnings[] = 'frEReport.declarantParty.party.address is not part of Storecove\'s specialized frEReportParty model; only companyName is documented.';
        }

        return [
            'status' => $errors !== [] ? 'fail' : ($warnings !== [] ? 'pass_with_warnings' : 'pass'),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<int, string> $errors
     */
    private function assessTransactionReport(array $report, array &$errors): void
    {
        $this->assessPeriod($report['period'] ?? null, 'transactionReport.period', $errors);

        $invoices = $this->listValue($report, 'b2biInvoices', 'transactionReport', $errors);
        $transactions = $this->listValue($report, 'b2cTransactions', 'transactionReport', $errors);

        if ($invoices === [] && $transactions === []) {
            $errors[] = 'transactionReport requires at least one b2biInvoices or b2cTransactions row.';
        }

        foreach ($invoices as $index => $invoice) {
            if (! is_array($invoice)) {
                $errors[] = "transactionReport.b2biInvoices.{$index} must be an object.";
                continue;
            }

            foreach (['invoiceNumber', 'issueDate', 'documentCurrency', 'amountIncludingVat'] as $key) {
                if (! array_key_exists($key, $invoice)) {
                    $errors[] = "transactionReport.b2biInvoices.{$index}.{$key} is required by the generated report contract.";
                }
            }

            $this->assessDate($invoice['issueDate'] ?? null, "transactionReport.b2biInvoices.{$index}.issueDate", $errors);

            if (isset($invoice['dueDate'])) {
                $this->assessDate($invoice['dueDate'], "transactionReport.b2biInvoices.{$index}.dueDate", $errors);
            }

            if (! $this->isCurrency($invoice['documentCurrency'] ?? null)) {
                $errors[] = "transactionReport.b2biInvoices.{$index}.documentCurrency must use a three-letter currency code.";
            }

            if (! is_numeric($invoice['amountIncludingVat'] ?? null)) {
                $errors[] = "transactionReport.b2biInvoices.{$index}.amountIncludingVat must be numeric.";
            }

            foreach (['accountingSupplierParty', 'accountingCustomerParty'] as $partyKey) {
                if (! is_array($invoice[$partyKey] ?? null)
                    || ! is_array(data_get($invoice, "{$partyKey}.publicIdentifiers"))
                    || data_get($invoice, "{$partyKey}.publicIdentifiers") === []) {
                    $errors[] = "transactionReport.b2biInvoices.{$index}.{$partyKey}.publicIdentifiers requires a non-empty list.";
                }
            }

            if (! is_array($invoice['invoiceLines'] ?? null) || $invoice['invoiceLines'] === []) {
                $errors[] = "transactionReport.b2biInvoices.{$index}.invoiceLines requires at least one row.";
            }

            $this->assessTaxSubtotals(
                $invoice['taxSubtotals'] ?? null,
                "transactionReport.b2biInvoices.{$index}.taxSubtotals",
                'invoice',
                $errors,
            );
        }

        foreach ($transactions as $index => $transaction) {
            if (! is_array($transaction)) {
                $errors[] = "transactionReport.b2cTransactions.{$index} must be an object.";
                continue;
            }

            foreach (['date', 'currency', 'category', 'amountExcludingVat', 'amountIncludingVat', 'transactionsCount'] as $key) {
                if (! array_key_exists($key, $transaction)) {
                    $errors[] = "transactionReport.b2cTransactions.{$index}.{$key} is required by the generated report contract.";
                }
            }

            $this->assessDate($transaction['date'] ?? null, "transactionReport.b2cTransactions.{$index}.date", $errors);

            if (! $this->isCurrency($transaction['currency'] ?? null)) {
                $errors[] = "transactionReport.b2cTransactions.{$index}.currency must use a three-letter currency code.";
            }

            if (! is_int($transaction['transactionsCount'] ?? null) || $transaction['transactionsCount'] < 0) {
                $errors[] = "transactionReport.b2cTransactions.{$index}.transactionsCount must be an integer greater than or equal to zero.";
            }

            $this->assessTaxSubtotals(
                $transaction['taxSubtotals'] ?? null,
                "transactionReport.b2cTransactions.{$index}.taxSubtotals",
                'transaction',
                $errors,
            );
        }
    }

    /**
     * @param array<string, mixed> $report
     * @param array<int, string> $errors
     */
    private function assessPaymentReport(array $report, array &$errors): void
    {
        $this->assessPeriod($report['period'] ?? null, 'paymentReport.period', $errors);

        $b2biPayments = $this->listValue($report, 'b2biPayments', 'paymentReport', $errors);
        $b2cPayments = $this->listValue($report, 'b2cPayments', 'paymentReport', $errors);

        if ($b2biPayments === [] && $b2cPayments === []) {
            $errors[] = 'paymentReport requires at least one b2biPayments or b2cPayments row.';
        }

        foreach ($b2biPayments as $index => $payment) {
            if (! is_array($payment)) {
                $errors[] = "paymentReport.b2biPayments.{$index} must be an object.";
                continue;
            }

            if (! is_string($payment['invoiceNumber'] ?? null) || trim($payment['invoiceNumber']) === '') {
                $errors[] = "paymentReport.b2biPayments.{$index}.invoiceNumber is required.";
            }

            $this->assessDate($payment['paymentDate'] ?? null, "paymentReport.b2biPayments.{$index}.paymentDate", $errors);

            if (isset($payment['issueDate'])) {
                $this->assessDate($payment['issueDate'], "paymentReport.b2biPayments.{$index}.issueDate", $errors);
            }

            $hasAmount = is_numeric($payment['amount'] ?? null) && $this->isCurrency($payment['currency'] ?? null);
            $hasTaxSubtotals = is_array($payment['taxSubtotals'] ?? null) && $payment['taxSubtotals'] !== [];

            if (! $hasAmount && ! $hasTaxSubtotals) {
                $errors[] = "paymentReport.b2biPayments.{$index} requires amount/currency or taxSubtotals.";
            }

            if ($hasTaxSubtotals) {
                $this->assessTaxSubtotals(
                    $payment['taxSubtotals'],
                    "paymentReport.b2biPayments.{$index}.taxSubtotals",
                    'b2bi_payment',
                    $errors,
                );
            }
        }

        foreach ($b2cPayments as $index => $payment) {
            if (! is_array($payment)) {
                $errors[] = "paymentReport.b2cPayments.{$index} must be an object.";
                continue;
            }

            $this->assessDate($payment['date'] ?? null, "paymentReport.b2cPayments.{$index}.date", $errors);
            $this->assessTaxSubtotals(
                $payment['taxSubtotal'] ?? null,
                "paymentReport.b2cPayments.{$index}.taxSubtotal",
                'b2c_payment',
                $errors,
            );

            $unexpectedKeys = array_diff(array_keys($payment), ['date', 'taxSubtotal']);

            if ($unexpectedKeys !== []) {
                $errors[] = "paymentReport.b2cPayments.{$index} contains transaction-only or ignored fields: ".implode(', ', $unexpectedKeys).'.';
            }
        }
    }

    /**
     * @param mixed $subtotals
     * @param array<int, string> $errors
     */
    private function assessTaxSubtotals(mixed $subtotals, string $path, string $kind, array &$errors): void
    {
        if (! is_array($subtotals) || ! array_is_list($subtotals) || $subtotals === []) {
            $errors[] = "{$path} requires a non-empty list.";
            return;
        }

        foreach ($subtotals as $index => $subtotal) {
            if (! is_array($subtotal) || ! is_numeric($subtotal['percentage'] ?? null)) {
                $errors[] = "{$path}.{$index}.percentage must be numeric.";
                continue;
            }

            if ($kind === 'invoice' && ! is_string($subtotal['taxCategory'] ?? null)) {
                $errors[] = "{$path}.{$index}.taxCategory is required for B2BI invoices.";
            }

            if ($kind !== 'invoice' && ! is_string($subtotal['category'] ?? null)) {
                $errors[] = "{$path}.{$index}.category is required.";
            }

            if (in_array($kind, ['invoice', 'transaction'], true)
                && (! is_numeric($subtotal['taxableAmount'] ?? null) || ! is_numeric($subtotal['taxAmount'] ?? null))) {
                $errors[] = "{$path}.{$index} requires numeric taxableAmount and taxAmount.";
            }

            if ($kind === 'b2bi_payment'
                && ! is_numeric($subtotal['amountIncludingTax'] ?? null)) {
                $errors[] = "{$path}.{$index}.amountIncludingTax must be numeric.";
            }

            if ($kind === 'b2c_payment') {
                if (! is_numeric($subtotal['amount'] ?? null)) {
                    $errors[] = "{$path}.{$index}.amount must be numeric.";
                }

                if (! $this->isCurrency($subtotal['currency'] ?? null)) {
                    $errors[] = "{$path}.{$index}.currency must use a three-letter currency code.";
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $report
     * @param array<int, string> $errors
     * @return array<int, mixed>
     */
    private function listValue(array $report, string $key, string $path, array &$errors): array
    {
        $value = $report[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            $errors[] = "{$path}.{$key} must be a list.";
            return [];
        }

        return $value;
    }

    /**
     * @param array<int, string> $errors
     */
    private function assessPeriod(mixed $value, string $path, array &$errors): void
    {
        if (! is_string($value)
            || ! preg_match('/^(\d{4}-\d{2}-\d{2}) - (\d{4}-\d{2}-\d{2})$/', $value, $matches)) {
            $errors[] = "{$path} must use YYYY-MM-DD - YYYY-MM-DD format.";
            return;
        }

        $this->assessDate($matches[1], $path.'.start', $errors);
        $this->assessDate($matches[2], $path.'.end', $errors);

        if ($matches[1] >= $matches[2]) {
            $errors[] = "[G6.25] {$path} end date must be strictly after its start date.";
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function assessDate(mixed $value, string $path, array &$errors): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)
            || ! checkdate((int) ($matches[2] ?? 0), (int) ($matches[3] ?? 0), (int) ($matches[1] ?? 0))) {
            $errors[] = "{$path} must use valid YYYY-MM-DD format.";
        }
    }

    private function isCurrency(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1;
    }

    private function summaryKey(string $status): string
    {
        return match ($status) {
            'pass' => 'pass',
            'pass_with_warnings' => 'passWithWarnings',
            default => 'fail',
        };
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function writeJsonArtifact(string $filename, array $artifact): void
    {
        $directory = base_path(self::ARTIFACT_DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $directory.'/'.$filename,
            json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}
