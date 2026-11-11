<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\Models\Company;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportContext;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Compares exact Storecove HTTP request bytes with reviewed format artifacts.
 *
 * Normal test runs are read-only. Set UPDATE_STORECOVE_ARTIFACTS=1 explicitly
 * to regenerate the candidate corpus after a deliberate contract review.
 */
class FranceEReportArtifactAssessmentTest extends TestCase
{
    private const ARTIFACT_DIRECTORY = 'tests/artifacts/france_e_reporting/storecove_format';

    private const FR_B2C_TRANSACTION = 1;

    private const FR_B2C_PAYMENT = 2;

    private const FR_VAT_EXCLUDED_TRANSACTION = 3;

    private const FR_VAT_EXCLUDED_PAYMENT = 4;

    private const IDEMPOTENCY_GUIDS = [
        'le_a_transaction_b2bi_in' => '083acb33-a86f-4f20-89ad-dc5ce1849ed4',
        'le_a_transaction_b2c_in' => '8ad64127-8ca3-4d80-ad51-41d4f88e1d04',
        'le_a_transaction_combined_in' => 'a2b13948-b991-453c-aa65-411753d6a0ba',
        'le_b_transaction_combined_in' => 'c82fc1e0-6559-4c12-ab90-f6efed812be5',
        'le_a_transaction_b2bi_tax_categories_in' => '9635c307-ec49-4a85-9149-6418780c86ab',
        'le_a_transaction_b2c_categories_in' => 'd990f2db-21ab-4b0e-99a9-39da0e20e303',
        'le_a_payment_b2bi_in' => '31ad725b-6890-46b1-a67f-189845c145d1',
        'le_a_payment_b2c_in' => '63ecfbc1-adc8-4d34-b3da-fe210fb5c24a',
        'le_a_payment_combined_in' => 'c87e5366-877a-4e8c-8714-bb4c65e9eacd',
        'le_b_payment_combined_in' => '541405bd-62da-43f5-8eeb-e2d03993baf0',
        'le_a_payment_b2bi_means_in' => '5612e4f0-a205-4419-8d20-95beedf22cd2',
        'le_a_payment_b2bi_re' => 'c40b74bc-a8f0-4cc3-859b-2904a450191f',
        'le_a_payment_b2c_re' => '0c2642af-b40f-4423-8069-57da569629a1',
        'le_a_payment_combined_re' => '23ab3b38-c69a-4c37-9ad4-478c28aa0ac8',
    ];

    public function testExactHttpBodiesMatchReviewedPerLegalEntityArtifacts(): void
    {
        $companyA = $this->company(42, 100042, '552100554', 'Seller A');
        $companyB = $this->company(43, 100043, '732829320', 'Seller B');
        $transactionContextA = $this->context($companyA, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $transactionContextB = $this->context($companyB, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $paymentContextA = $this->context($companyA, '2026-01-01', '2026-01-31', '2026-02-01 09:00:00 Europe/Paris');
        $paymentContextB = $this->context($companyB, '2026-01-01', '2026-01-31', '2026-02-01 09:00:00 Europe/Paris');

        $scenarios = [
            'le_a_transaction_b2bi_in' => $this->scenario(
                $companyA,
                $transactionContextA,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-A-TXN-B2BI-IN',
                $this->transactionEvents($companyA, 'A', 'b2bi'),
            ),
            'le_a_transaction_b2c_in' => $this->scenario(
                $companyA,
                $transactionContextA,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-A-TXN-B2C-IN',
                $this->transactionEvents($companyA, 'A', 'b2c'),
            ),
            'le_a_transaction_combined_in' => $this->scenario(
                $companyA,
                $transactionContextA,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-A-TXN-COMBINED-IN',
                $this->transactionEvents($companyA, 'A', 'combined'),
            ),
            'le_b_transaction_combined_in' => $this->scenario(
                $companyB,
                $transactionContextB,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-B-TXN-COMBINED-IN',
                $this->transactionEvents($companyB, 'B', 'combined'),
            ),
            'le_a_transaction_b2bi_tax_categories_in' => $this->scenario(
                $companyA,
                $transactionContextA,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-A-TXN-TAX-CATEGORIES-IN',
                [$this->event(
                    $companyA,
                    20,
                    self::FR_VAT_EXCLUDED_TRANSACTION,
                    '2026-09-10',
                    $this->b2biTaxCategoryInvoice('A', (string) $companyA->settings->id_number),
                )],
            ),
            'le_a_transaction_b2c_categories_in' => $this->scenario(
                $companyA,
                $transactionContextA,
                FranceEReportVariant::TransactionInitial,
                'FR-F10-A-TXN-B2C-CATEGORIES-IN',
                $this->b2cCategoryEvents($companyA),
            ),
            'le_a_payment_b2bi_in' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentInitial,
                'FR-F10-A-PAY-B2BI-IN',
                $this->paymentEvents($companyA, 'A', 'b2bi'),
            ),
            'le_a_payment_b2c_in' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentInitial,
                'FR-F10-A-PAY-B2C-IN',
                $this->paymentEvents($companyA, 'A', 'b2c'),
            ),
            'le_a_payment_combined_in' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentInitial,
                'FR-F10-A-PAY-COMBINED-IN',
                $this->paymentEvents($companyA, 'A', 'combined'),
            ),
            'le_b_payment_combined_in' => $this->scenario(
                $companyB,
                $paymentContextB,
                FranceEReportVariant::PaymentInitial,
                'FR-F10-B-PAY-COMBINED-IN',
                $this->paymentEvents($companyB, 'B', 'combined'),
            ),
            'le_a_payment_b2bi_means_in' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentInitial,
                'FR-F10-A-PAY-MEANS-IN',
                [
                    $this->event($companyA, 30, self::FR_VAT_EXCLUDED_PAYMENT, '2026-01-31', $this->b2biPayment('A', '30', 'MEANS-30')),
                    $this->event($companyA, 31, self::FR_VAT_EXCLUDED_PAYMENT, '2026-01-31', $this->b2biPayment('A', '48', 'MEANS-48')),
                ],
            ),
            'le_a_payment_b2bi_re' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentRectificative,
                'FR-F10-A-PAY-B2BI-RE',
                $this->paymentEvents($companyA, 'A', 'b2bi'),
            ),
            'le_a_payment_b2c_re' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentRectificative,
                'FR-F10-A-PAY-B2C-RE',
                $this->paymentEvents($companyA, 'A', 'b2c'),
            ),
            'le_a_payment_combined_re' => $this->scenario(
                $companyA,
                $paymentContextA,
                FranceEReportVariant::PaymentRectificative,
                'FR-F10-A-PAY-COMBINED-RE',
                $this->paymentEvents($companyA, 'A', 'combined'),
            ),
        ];

        $manifest = [
            'label' => 'STORECOVE HTTP FORMAT CANDIDATES - LOCAL ONLY, NOT SUBMITTED',
            'generatedAt' => '2026-08-09T00:00:00Z',
            'storecoveContract' => 'https://www.storecove.com/docs/',
            'storecoveDocsLastUpdated' => '2026-08-07T15:11:31Z',
            'storecoveOpenApi' => 'https://api.storecove.com/api/v2/openapi.json',
            'storecoveOpenApiVersion' => '2.0.1',
            'storecoveOpenApiSha256' => '7f72642d80e61a13fa09d5c8f427c3ce7e5914900d89f8f2f4c91102a0f7c4c9',
            'storecoveFlux10Validator' => 'Schematron v1.0 (July 2026)',
            'aifeRulesVersion' => '3.2',
            'aifeRulesPackage' => 'https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/specification_externes_b2b/specifications-externes-v3.2.zip',
            'aifeRulesPackageSha256' => 'cd8f6e817e37f329e6f62a35aa131b78a51379bec953445b774fa8adbaaa3862',
            'artifactUpdateCommand' => 'UPDATE_STORECOVE_ARTIFACTS=1 php vendor/bin/phpunit tests/Unit/FranceEReporting/FranceEReportArtifactAssessmentTest.php',
            'qualificationEnvironment' => 'No Storecove sandbox is available for this integration.',
            'releaseRule' => 'UNPROVED until Storecove supplies exact generated F10 XML for these payloads or an authorized real-data production canary produces it, followed by pinned AIFE and scenario-value validation.',
            'artifacts' => [],
        ];

        foreach ($scenarios as $name => $scenario) {
            $payload = $this->compilePayload(
                $scenario['company'],
                $scenario['context'],
                $scenario['variant'],
                $scenario['documentId'],
                $scenario['events'],
                self::IDEMPOTENCY_GUIDS[$name],
            );

            $this->assertStorecoveShape($payload, $scenario['company'], $scenario['variant']);
            $httpBody = $this->exactFinalHttpBody($scenario['company'], $payload);
            $decodedBody = json_decode($httpBody, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($payload, $decodedBody);

            $otherSentinel = $scenario['company']->id === $companyA->id ? 'B-SENTINEL' : 'A-SENTINEL';
            $otherSiren = $scenario['company']->id === $companyA->id ? '732829320' : '552100554';
            $this->assertStringNotContainsString($otherSentinel, $httpBody);
            $this->assertStringNotContainsString($otherSiren, $httpBody);
            $this->assertMapperSpecificScenario($name, $decodedBody);

            $filename = $name.'.request.json';
            $this->assertOrUpdateArtifact($filename, $httpBody);
            $report = $payload['document']['frEReport'];
            $section = $report['transactionReport'] ?? $report['paymentReport'];
            $manifest['artifacts'][] = [
                'file' => $filename,
                'sha256' => hash('sha256', $httpBody),
                'legalEntityId' => $payload['legalEntityId'],
                'siren' => $report['declarantParty']['publicIdentifiers'][0]['id'],
                'variant' => $scenario['variant']->value,
                'reportKind' => $scenario['variant']->isTransaction() ? 'transactionReport' : 'paymentReport',
                'period' => $section['period'],
                'b2biRows' => count($section[$scenario['variant']->isTransaction() ? 'b2biInvoices' : 'b2biPayments'] ?? []),
                'b2cRows' => count($section[$scenario['variant']->isTransaction() ? 'b2cTransactions' : 'b2cPayments'] ?? []),
                'exactStorecoveHttpBody' => 'PASS',
                'storecoveQualification' => 'NOT_AVAILABLE_PRE_PRODUCTION',
                'storecoveGeneratedF10Xml' => 'REQUIRED_FROM_VENDOR_OR_AUTHORIZED_REAL_DATA_CANARY',
                'aifeValidation' => 'REQUIRED_BEFORE_BROAD_ENABLEMENT',
                'releaseStatus' => 'UNPROVED',
            ];
        }

        $manifestBytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $this->assertOrUpdateArtifact('manifest.json', $manifestBytes);
        $this->assertContains(100042, array_column($manifest['artifacts'], 'legalEntityId'));
        $this->assertContains(100043, array_column($manifest['artifacts'], 'legalEntityId'));
        $this->assertSame(['UNPROVED'], array_values(array_unique(array_column($manifest['artifacts'], 'releaseStatus'))));
    }

    /**
     * @param array<int, FRReportEntryData> $events
     * @return array{company: Company, context: FranceEReportContext, variant: FranceEReportVariant, documentId: string, events: array<int, FRReportEntryData>}
     */
    private function scenario(
        Company $company,
        FranceEReportContext $context,
        FranceEReportVariant $variant,
        string $documentId,
        array $events,
    ): array {
        return compact('company', 'context', 'variant', 'documentId', 'events');
    }

    /**
     * @param array<int, FRReportEntryData> $events
     * @return array<string, mixed>
     */
    private function compilePayload(
        Company $company,
        FranceEReportContext $context,
        FranceEReportVariant $variant,
        string $documentId,
        array $events,
        string $idempotencyGuid,
    ): array {
        $report = (new FranceEReportCompiler())->compileVariantFromEntries(
            $company,
            $variant,
            $context,
            $events,
            $documentId,
        );

        return (new FranceEReportPayloadBuilder())->build($company, $context, $report, $idempotencyGuid);
    }

    /** @param array<string, mixed> $payload */
    private function assertStorecoveShape(array $payload, Company $company, FranceEReportVariant $variant): void
    {
        $this->assertSame(['legalEntityId', 'idempotencyGuid', 'document'], array_keys($payload));
        $this->assertSame((int) $company->legal_entity_id, $payload['legalEntityId']);
        $this->assertSame('fr_e_report', $payload['document']['documentType']);
        $this->assertSame(['documentType', 'frEReport'], array_keys($payload['document']));

        $report = $payload['document']['frEReport'];
        $this->assertArrayNotHasKey('schemaVersion', $report);
        $this->assertArrayNotHasKey('routing', $payload);
        $this->assertArrayNotHasKey('role', $report['declarantParty']);
        $this->assertSame(['companyName' => (string) $company->settings->name], $report['declarantParty']['party']);
        $this->assertSame(
            $variant->isTransaction() ? ['transactionReport'] : ['paymentReport'],
            array_values(array_intersect(['transactionReport', 'paymentReport'], array_keys($report))),
        );

        $section = $variant->isTransaction() ? $report['transactionReport'] : $report['paymentReport'];
        $b2biKey = $variant->isTransaction() ? 'b2biInvoices' : 'b2biPayments';
        $b2cKey = $variant->isTransaction() ? 'b2cTransactions' : 'b2cPayments';
        $this->assertTrue(isset($section[$b2biKey]) || isset($section[$b2cKey]));
        $this->assertNotSame([], $section[$b2biKey] ?? null);
        $this->assertNotSame([], $section[$b2cKey] ?? null);

        if (isset($section[$b2biKey])) {
            $expectedKeys = $variant->isTransaction()
                ? ['taxCategory', 'percentage', 'taxableAmount', 'taxAmount', 'country']
                : ['percentage', 'category', 'currency', 'country', 'amountIncludingTax'];
            $this->assertSame($expectedKeys, array_keys($section[$b2biKey][0]['taxSubtotals'][0]));
        }

        if (isset($section[$b2cKey])) {
            $subtotalKey = $variant->isTransaction() ? 'taxSubtotals' : 'taxSubtotal';
            $expectedKeys = $variant->isTransaction()
                ? ['category', 'percentage', 'taxableAmount', 'taxAmount']
                : ['category', 'percentage', 'country', 'currency', 'amount'];
            $this->assertSame($expectedKeys, array_keys($section[$b2cKey][0][$subtotalKey][0]));
        }
    }

    /** @param array<string, mixed> $payload */
    private function exactFinalHttpBody(Company $company, array $payload): string
    {
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'format-test-key',
        ]);
        $capturedRequest = null;
        Http::fake(function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return Http::response(['guid' => 'storecove-guid'], 200);
        });

        $storecove = new Storecove();
        $response = $storecove->proxy->setCompany($company)->submitDocument([
            ...$payload,
            'legal_entity_id' => $payload['legalEntityId'],
            'tenant_id' => 'internal-tenant',
            'account_key' => 'internal-account',
            'e_invoicing_token' => 'internal-token',
        ]);

        $this->assertSame(['guid' => 'storecove-guid'], $response);
        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest->method());
        $this->assertSame('https://api.storecove.com/api/v2/document_submissions', $capturedRequest->url());

        return $capturedRequest->body();
    }

    private function company(int $companyId, int $legalEntityId, string $siren, string $name): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            'id' => $companyId,
            'company_key' => 'fr-report-company-'.$companyId,
            'legal_entity_id' => $legalEntityId,
        ], true);
        $settings = CompanySettings::defaults();
        $settings->name = $name;
        $settings->id_number = $siren;
        $settings->vat_number = 'FR'.$siren;
        $company->settings = $settings;

        return $company;
    }

    private function context(Company $company, string $start, string $end, string $issuedAt): FranceEReportContext
    {
        return new FranceEReportContext(
            companyId: (int) $company->id,
            legalEntityId: (int) $company->legal_entity_id,
            periodStart: $start,
            periodEnd: $end,
            issuedAt: CarbonImmutable::parse($issuedAt),
        );
    }

    /** @return array<int, FRReportEntryData> */
    private function transactionEvents(Company $company, string $sentinel, string $composition): array
    {
        $events = [];

        if (in_array($composition, ['b2bi', 'combined'], true)) {
            $events[] = $this->event($company, 1, self::FR_VAT_EXCLUDED_TRANSACTION, '2026-09-10', $this->b2biInvoice($sentinel, (string) $company->settings->id_number));
        }

        if (in_array($composition, ['b2c', 'combined'], true)) {
            $events[] = $this->event($company, 2, self::FR_B2C_TRANSACTION, '2026-09-10', $this->b2cTransaction($sentinel));
        }

        return $events;
    }

    /** @return array<int, FRReportEntryData> */
    private function paymentEvents(Company $company, string $sentinel, string $composition): array
    {
        $events = [];

        if (in_array($composition, ['b2bi', 'combined'], true)) {
            $events[] = $this->event($company, 3, self::FR_VAT_EXCLUDED_PAYMENT, '2026-01-31', $this->b2biPayment($sentinel));
        }

        if (in_array($composition, ['b2c', 'combined'], true)) {
            $events[] = $this->event($company, 4, self::FR_B2C_PAYMENT, '2026-01-31', $this->b2cPayment());
        }

        return $events;
    }

    /** @return array<int, FRReportEntryData> */
    private function b2cCategoryEvents(Company $company): array
    {
        $specifications = [
            ['2026-09-05', 'TLB1', 'customer', 'standard', 100, 20],
            ['2026-09-06', 'TPS1', 'supplier', 'exempt', 80, 0],
            ['2026-09-07', 'TNT1', 'customer', 'zero_rated', 60, 0],
            ['2026-09-08', 'TMA1', 'supplier', 'outside_scope', 40, 0],
        ];

        return array_map(
            fn (array $specification, int $index): FRReportEntryData => $this->event(
                $company,
                40 + $index,
                self::FR_B2C_TRANSACTION,
                '2026-09-10',
                $this->b2cCategoryTransaction(...$specification),
            ),
            $specifications,
            array_keys($specifications),
        );
    }

    /** @param array<string, mixed> $data */
    private function event(Company $company, int $id, int $eventId, string $periodEnd, array $data): FRReportEntryData
    {
        return match ($eventId) {
            self::FR_B2C_TRANSACTION => FRReportEntryData::fromB2CTransaction(B2CTransactionData::fromArray($data)),
            self::FR_B2C_PAYMENT => FRReportEntryData::fromB2CPayment(B2CPaymentData::fromArray($data)),
            self::FR_VAT_EXCLUDED_TRANSACTION => FRReportEntryData::fromB2BIInvoice(B2BIInvoiceData::fromArray($data)),
            self::FR_VAT_EXCLUDED_PAYMENT => FRReportEntryData::fromB2BIPayment(B2BIPaymentData::fromArray($data)),
        };
    }

    /** @return array<string, mixed> */
    private function b2biInvoice(string $sentinel, string $siren): array
    {
        return [
            'invoiceNumber' => $sentinel.'-SENTINEL-INV',
            'issueDate' => '2026-09-05',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => 120,
            'accountingSupplierParty' => [
                'party' => ['companyName' => $sentinel.'-SENTINEL', 'address' => ['country' => 'FR']],
                'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => $siren]],
            ],
            'accountingCustomerParty' => [
                'party' => ['companyName' => $sentinel.'-CUSTOMER', 'address' => ['country' => 'IT']],
                'publicIdentifiers' => [['scheme' => 'IT:VAT', 'id' => 'IT00987654321']],
            ],
            'taxSubtotals' => [[
                'taxCategory' => 'standard', 'percentage' => 20, 'taxableAmount' => 100, 'taxAmount' => 20, 'country' => 'FR',
            ]],
            'invoiceLines' => [[
                'description' => $sentinel.'-SENTINEL-GOODS',
                'amountExcludingVat' => 100,
                'tax' => ['percentage' => 20, 'category' => 'standard', 'country' => 'FR'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function b2cTransaction(string $sentinel): array
    {
        return [
            'date' => '2026-09-05',
            'category' => 'TLB1',
            'currency' => 'EUR',
            'amountExcludingVat' => $sentinel === 'A' ? 100 : 200,
            'amountIncludingVat' => $sentinel === 'A' ? 120 : 240,
            'taxSubtotals' => [[
                'category' => 'standard',
                'percentage' => 20,
                'taxableAmount' => $sentinel === 'A' ? 100 : 200,
                'taxAmount' => $sentinel === 'A' ? 20 : 40,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function b2biPayment(string $sentinel, ?string $paymentMeansCode = null, string $invoiceSuffix = 'SENTINEL-INV'): array
    {
        return array_filter([
            'invoiceNumber' => $sentinel.'-'.$invoiceSuffix,
            'issueDate' => '2026-01-05',
            'paymentDate' => '2026-01-20',
            'paymentMeansCode' => $paymentMeansCode,
            'taxSubtotals' => [[
                'percentage' => 20,
                'category' => 'standard',
                'currency' => 'EUR',
                'country' => 'FR',
                'amountIncludingTax' => 120,
            ]],
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    /** @return array<string, mixed> */
    private function b2biTaxCategoryInvoice(string $sentinel, string $siren): array
    {
        $taxes = [
            ['S', 20, 100, 20],
            ['K', 0, 10, 0],
            ['AE', 0, 10, 0],
            ['E', 0, 10, 0],
            ['Z', 0, 10, 0],
            ['G', 0, 10, 0],
            ['O', 0, 10, 0],
        ];

        return [
            'invoiceNumber' => $sentinel.'-TAX-CATEGORIES',
            'issueDate' => '2026-09-05',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => 180,
            'accountingSupplierParty' => [
                'party' => ['companyName' => $sentinel.'-SENTINEL', 'address' => ['country' => 'FR']],
                'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => $siren]],
            ],
            'accountingCustomerParty' => [
                'party' => ['companyName' => $sentinel.'-CUSTOMER', 'address' => ['country' => 'IT']],
                'publicIdentifiers' => [['scheme' => 'IT:VAT', 'id' => 'IT00987654321']],
            ],
            'taxSubtotals' => array_map(static fn (array $tax): array => [
                'taxCategory' => $tax[0],
                'percentage' => $tax[1],
                'taxableAmount' => $tax[2],
                'taxAmount' => $tax[3],
                'country' => 'FR',
            ], $taxes),
            'invoiceLines' => array_map(static fn (array $tax, int $index): array => [
                'description' => $sentinel.'-TAX-LINE-'.($index + 1),
                'amountExcludingVat' => $tax[2],
                'tax' => ['percentage' => $tax[1], 'category' => $tax[0], 'country' => 'FR'],
            ], $taxes, array_keys($taxes)),
        ];
    }

    /** @return array<string, mixed> */
    private function b2cCategoryTransaction(
        string $date,
        string $category,
        string $vatPaymentOption,
        string $taxCategory,
        int $net,
        int $vat,
    ): array {
        return [
            'date' => $date,
            'category' => $category,
            'currency' => 'EUR',
            'amountExcludingVat' => $net,
            'amountIncludingVat' => $net + $vat,
            'transactionsCount' => 1,
            'vatPaymentOption' => $vatPaymentOption,
            'taxSubtotals' => [[
                'category' => $taxCategory,
                'percentage' => $vat === 0 ? 0 : 20,
                'taxableAmount' => $net,
                'taxAmount' => $vat,
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function assertMapperSpecificScenario(string $name, array $payload): void
    {
        $report = $payload['document']['frEReport'];

        if ($name === 'le_a_transaction_b2bi_tax_categories_in') {
            $invoice = $report['transactionReport']['b2biInvoices'][0];
            $expected = ['standard', 'intra_community', 'reverse_charge', 'exempt', 'zero_rated', 'export', 'outside_scope'];
            $this->assertSame($expected, array_column($invoice['taxSubtotals'], 'taxCategory'));
            $this->assertSame($expected, array_column(array_column($invoice['invoiceLines'], 'tax'), 'category'));
        }

        if ($name === 'le_a_transaction_b2c_categories_in') {
            $transactions = $report['transactionReport']['b2cTransactions'];
            $this->assertSame(['TLB1', 'TPS1', 'TNT1', 'TMA1'], array_column($transactions, 'category'));
            $this->assertSame(['customer', 'supplier', 'customer', 'supplier'], array_column($transactions, 'vatPaymentOption'));
        }

        if ($name === 'le_a_payment_b2bi_means_in') {
            $this->assertSame(['30', '48'], array_column($report['paymentReport']['b2biPayments'], 'paymentMeansCode'));
        }
    }

    /** @return array<string, mixed> */
    private function b2cPayment(): array
    {
        return [
            'date' => '2026-01-20',
            'taxSubtotal' => [[
                'category' => 'standard',
                'percentage' => 20,
                'country' => 'FR',
                'currency' => 'EUR',
                'amount' => 120,
            ]],
        ];
    }

    private function assertOrUpdateArtifact(string $filename, string $bytes): void
    {
        $path = base_path(self::ARTIFACT_DIRECTORY.'/'.$filename);

        if (getenv('UPDATE_STORECOVE_ARTIFACTS') === '1') {
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($path, $bytes);
        }

        $this->assertFileExists($path, "Run the explicit artifact update command to create {$filename}.");
        $this->assertSame($bytes, file_get_contents($path), "Storecove HTTP artifact {$filename} changed.");
    }
}
