<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\Models\Company;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportContext;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class FranceEReportCompilerTest extends TestCase
{
    private const IDEMPOTENCY_GUID = '0e7c37f8-d4ce-4c2a-b98a-b290483c18cf';

    private const FR_B2C_TRANSACTION = 1;

    private const FR_B2C_PAYMENT = 2;

    private const FR_VAT_EXCLUDED_TRANSACTION = 3;

    private const FR_VAT_EXCLUDED_PAYMENT = 4;

    public function testItBuildsAnExactCombinedTransactionRequestForOneLegalEntity(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $payload = $this->compilePayload($company, FranceEReportVariant::TransactionInitial, $context, [
            $this->event($company, 2, self::FR_B2C_TRANSACTION, $this->b2cTransactionPayload('2026-09-05')),
            $this->event($company, 1, self::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoicePayload('A-INV-001', 'Seller A')),
        ], 'FR-F10-A-TRANSACTION-202609');

        $this->assertSame(['legalEntityId', 'idempotencyGuid', 'document'], array_keys($payload));
        $this->assertSame(100042, $payload['legalEntityId']);
        $this->assertSame(self::IDEMPOTENCY_GUID, $payload['idempotencyGuid']);
        $this->assertSame('fr_e_report', $payload['document']['documentType']);

        $report = $payload['document']['frEReport'];
        $this->assertSame('IN', $report['typeCode']);
        $this->assertSame('+0200', $report['timeZone']);
        $this->assertArrayHasKey('transactionReport', $report);
        $this->assertArrayNotHasKey('paymentReport', $report);
        $this->assertArrayNotHasKey('schemaVersion', $report);
        $this->assertSame(['companyName' => 'Seller A'], $report['declarantParty']['party']);
        $this->assertSame([['scheme' => 'FR:SIRENE', 'id' => '552100554']], $report['declarantParty']['publicIdentifiers']);
        $this->assertCount(1, $report['transactionReport']['b2biInvoices']);
        $this->assertCount(1, $report['transactionReport']['b2cTransactions']);
    }

    public function testItBuildsInitialAndRectificativeCombinedPaymentRequests(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-01-01', '2026-01-31', '2026-02-01 09:00:00 Europe/Paris');
        $events = [
            $this->event($company, 1, self::FR_VAT_EXCLUDED_PAYMENT, $this->b2biPaymentPayload('A-INV-001')),
            $this->event($company, 2, self::FR_B2C_PAYMENT, $this->b2cPaymentPayload('2026-01-20')),
        ];

        foreach ([
            [FranceEReportVariant::PaymentInitial, 'IN'],
            [FranceEReportVariant::PaymentRectificative, 'RE'],
        ] as [$variant, $typeCode]) {
            $payload = $this->compilePayload($company, $variant, $context, $events, 'FR-F10-A-'.$typeCode.'-PAYMENT-202601');
            $report = $payload['document']['frEReport'];

            $this->assertSame($typeCode, $report['typeCode']);
            $this->assertArrayHasKey('paymentReport', $report);
            $this->assertArrayNotHasKey('transactionReport', $report);
            $this->assertCount(1, $report['paymentReport']['b2biPayments']);
            $this->assertCount(1, $report['paymentReport']['b2cPayments']);
        }
    }

    public function testItPartitionsReportsPerLegalEntityWithoutCrossEntityRows(): void
    {
        $companyA = $this->company(42, 100042, '552100554', 'Seller A');
        $companyB = $this->company(43, 100043, '732829320', 'Seller B');
        $contextA = $this->context($companyA, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $contextB = $this->context($companyB, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');

        $payloadA = $this->compilePayload($companyA, FranceEReportVariant::TransactionInitial, $contextA, [
            $this->event($companyA, 7, self::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoicePayload('A-INV-001', 'Seller A')),
        ], 'FR-F10-A-TRANSACTION-202609');
        $payloadB = $this->compilePayload($companyB, FranceEReportVariant::TransactionInitial, $contextB, [
            $this->event($companyB, 7, self::FR_VAT_EXCLUDED_TRANSACTION, $this->b2biInvoicePayload('B-INV-001', 'Seller B', '732829320')),
        ], 'FR-F10-B-TRANSACTION-202609');

        $encodedA = json_encode($payloadA, JSON_THROW_ON_ERROR);
        $encodedB = json_encode($payloadB, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('A-INV-001', $encodedA);
        $this->assertStringNotContainsString('B-INV-001', $encodedA);
        $this->assertStringContainsString('552100554', $encodedA);
        $this->assertStringNotContainsString('732829320', $encodedA);
        $this->assertStringContainsString('B-INV-001', $encodedB);
        $this->assertStringNotContainsString('A-INV-001', $encodedB);
        $this->assertSame(100042, $payloadA['legalEntityId']);
        $this->assertSame(100043, $payloadB['legalEntityId']);
    }

    public function testItRejectsCrossCompanyAndMixedSectionInput(): void
    {
        $companyA = $this->company(42, 100042, '552100554', 'Seller A');
        $companyB = $this->company(43, 100043, '732829320', 'Seller B');
        $context = $this->context($companyA, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');

        try {
            $this->compilePayload($companyB, FranceEReportVariant::TransactionInitial, $context, [
                $this->event($companyB, 1, self::FR_B2C_TRANSACTION, $this->b2cTransactionPayload('2026-09-05')),
            ], 'FR-F10-A-TRANSACTION-202609');
            $this->fail('Expected cross-company input to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('context companyId', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('incompatible with transaction_in');
        $this->compilePayload($companyA, FranceEReportVariant::TransactionInitial, $context, [
            $this->event($companyA, 1, self::FR_B2C_TRANSACTION, $this->b2cTransactionPayload('2026-09-05')),
            $this->event($companyA, 2, self::FR_B2C_PAYMENT, $this->b2cPaymentPayload('2026-09-05')),
        ], 'FR-F10-A-TRANSACTION-202609');
    }

    public function testItAggregatesB2CRowsDeterministicallyAndKeepsTransactionsCountOptional(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $first = $this->b2cTransactionPayload('2026-09-05');
        $second = $this->b2cTransactionPayload('2026-09-05');
        $second['taxSubtotals'][0]['percentage'] = '20.00';
        unset($second['transactionsCount']);

        $payload = $this->compilePayload($company, FranceEReportVariant::TransactionInitial, $context, [
            $this->event($company, 2, self::FR_B2C_TRANSACTION, $second),
            $this->event($company, 1, self::FR_B2C_TRANSACTION, $first),
        ], 'FR-F10-A-TRANSACTION-202609');
        $row = $payload['document']['frEReport']['transactionReport']['b2cTransactions'][0];

        $this->assertSame(200, $row['amountExcludingVat']);
        $this->assertSame(240, $row['amountIncludingVat']);
        $this->assertSame(200, $row['taxSubtotals'][0]['taxableAmount']);
        $this->assertSame(40, $row['taxSubtotals'][0]['taxAmount']);
        $this->assertArrayNotHasKey('transactionsCount', $row);
        $this->assertCount(1, $row['taxSubtotals']);
        $this->assertSame(20, $row['taxSubtotals'][0]['percentage']);
        $this->assertArrayNotHasKey('currency', $row['taxSubtotals'][0]);
    }

    public function testItAggregatesMultipleB2CPaymentRowsAndOrdersTheResult(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-01-01', '2026-01-31', '2026-02-01 00:00:00 Europe/Paris');
        $sameDayOne = $this->b2cPaymentPayload('2026-01-20');
        $sameDayTwo = $this->b2cPaymentPayload('2026-01-20');
        $sameDayTwo['taxSubtotal'][0]['percentage'] = '20.00';

        $report = (new FranceEReportCompiler())->compileVariantFromEntries(
            $company,
            FranceEReportVariant::PaymentInitial,
            $context,
            [
                $this->event($company, 3, self::FR_B2C_PAYMENT, $this->b2cPaymentPayload('2026-01-21')),
                $this->event($company, 2, self::FR_B2C_PAYMENT, $sameDayTwo),
                $this->event($company, 1, self::FR_B2C_PAYMENT, $sameDayOne),
            ],
        )->toArray();

        $payments = $report['paymentReport']['b2cPayments'];

        $this->assertSame(['2026-01-20', '2026-01-21'], array_column($payments, 'date'));
        $this->assertSame(240, $payments[0]['taxSubtotal'][0]['amount']);
        $this->assertSame(20, $payments[0]['taxSubtotal'][0]['percentage']);
        $this->assertCount(1, $payments[0]['taxSubtotal']);
    }

    public function testGeneratedDocumentIdIsStableForTheSameContentAndChangesWithContent(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-01-01', '2026-01-31', '2026-02-01 00:00:00 Europe/Paris');
        $compiler = new FranceEReportCompiler();
        $event = $this->event($company, 1, self::FR_B2C_PAYMENT, $this->b2cPaymentPayload('2026-01-20'));

        $first = $compiler->compileVariantFromEntries($company, FranceEReportVariant::PaymentInitial, $context, [$event]);
        $second = $compiler->compileVariantFromEntries($company, FranceEReportVariant::PaymentInitial, $context, [$event]);
        $changedPayload = $this->b2cPaymentPayload('2026-01-20');
        $changedPayload['taxSubtotal'][0]['amount'] = 121;
        $changed = $compiler->compileVariantFromEntries(
            $company,
            FranceEReportVariant::PaymentInitial,
            $context,
            [$this->event($company, 1, self::FR_B2C_PAYMENT, $changedPayload)],
        );

        $this->assertSame($first->documentId, $second->documentId);
        $this->assertNotSame($first->documentId, $changed->documentId);
        $this->assertMatchesRegularExpression('/^FRF10-PI-20260131-[a-f0-9]{16}$/', $first->documentId);
    }

    public function testItRejectsMalformedSourceComponentsAndSupplierDeclarantMismatch(): void
    {
        $company = $this->company(42, 100042, '552100554', 'Seller A');
        $context = $this->context($company, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');

        try {
            $this->compilePayload($company, FranceEReportVariant::TransactionInitial, $context, [
                $this->event($company, 1, self::FR_B2C_TRANSACTION, []),
            ], 'FR-F10-A-TRANSACTION-202609');
            $this->fail('Expected a source row without its report component to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('b2cTransactions.date', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supplier FR:SIRENE must match');
        $this->compilePayload($company, FranceEReportVariant::TransactionInitial, $context, [
            $this->event(
                $company,
                2,
                self::FR_VAT_EXCLUDED_TRANSACTION,
                $this->b2biInvoicePayload('A-INV-001', 'Different Supplier', '732829320'),
            ),
        ], 'FR-F10-A-TRANSACTION-202609');
    }

    public function testItDerivesTheDeclarantSirenOnlyFromAValidSiret(): void
    {
        $valid = $this->company(42, 100042, '73282932000074', 'Seller A');
        $validContext = $this->context($valid, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');
        $payload = $this->compilePayload($valid, FranceEReportVariant::TransactionInitial, $validContext, [
            $this->event($valid, 1, self::FR_B2C_TRANSACTION, $this->b2cTransactionPayload('2026-09-05')),
        ], 'FR-F10-A-TRANSACTION-202609');

        $this->assertSame(
            '732829320',
            $payload['document']['frEReport']['declarantParty']['publicIdentifiers'][0]['id'],
        );

        $invalid = $this->company(43, 100043, '73282932000000', 'Seller B');
        $invalidContext = $this->context($invalid, '2026-09-01', '2026-09-10', '2026-09-11 09:00:00 Europe/Paris');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SIRET');
        $this->compilePayload($invalid, FranceEReportVariant::TransactionInitial, $invalidContext, [
            $this->event($invalid, 1, self::FR_B2C_TRANSACTION, $this->b2cTransactionPayload('2026-09-05')),
        ], 'FR-F10-B-TRANSACTION-202609');
    }

    /**
     * @param array<int, FRReportEntryData> $events
     * @return array<string, mixed>
     */
    private function compilePayload(
        Company $company,
        FranceEReportVariant $variant,
        FranceEReportContext $context,
        array $events,
        string $documentId,
    ): array {
        $report = (new FranceEReportCompiler())->compileVariantFromEntries(
            $company,
            $variant,
            $context,
            $events,
            $documentId,
        );

        return (new FranceEReportPayloadBuilder())->build($company, $context, $report, self::IDEMPOTENCY_GUID);
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
        $settings->france_reporting_schedule = 'monthly';
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

    /** @param array<string, mixed> $payload */
    private function event(Company $company, int $id, int $eventId, array $payload): FRReportEntryData
    {
        return match ($eventId) {
            self::FR_B2C_TRANSACTION => FRReportEntryData::fromB2CTransaction(B2CTransactionData::fromArray($payload)),
            self::FR_B2C_PAYMENT => FRReportEntryData::fromB2CPayment(B2CPaymentData::fromArray($payload)),
            self::FR_VAT_EXCLUDED_TRANSACTION => FRReportEntryData::fromB2BIInvoice(B2BIInvoiceData::fromArray($payload)),
            self::FR_VAT_EXCLUDED_PAYMENT => FRReportEntryData::fromB2BIPayment(B2BIPaymentData::fromArray($payload)),
        };
    }

    /** @return array<string, mixed> */
    private function b2biInvoicePayload(
        string $invoiceNumber,
        string $supplierName,
        string $supplierSiren = '552100554',
    ): array
    {
        return [
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => '2026-09-05',
            'dueDate' => '2026-09-30',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => 120,
            'accountingSupplierParty' => [
                'party' => ['companyName' => $supplierName, 'address' => ['country' => 'FR']],
                'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => $supplierSiren]],
            ],
            'accountingCustomerParty' => [
                'party' => ['companyName' => 'Customer IT', 'address' => ['country' => 'IT']],
                'publicIdentifiers' => [['scheme' => 'IT:VAT', 'id' => 'IT00987654321']],
            ],
            'taxSubtotals' => [[
                'taxCategory' => 'standard',
                'percentage' => 20,
                'taxableAmount' => 100,
                'taxAmount' => 20,
                'country' => 'FR',
            ]],
            'invoiceLines' => [[
                'description' => 'Goods',
                'amountExcludingVat' => 100,
                'tax' => ['percentage' => 20, 'category' => 'standard', 'country' => 'FR'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function b2cTransactionPayload(string $date): array
    {
        return [
            'date' => $date,
            'category' => 'TLB1',
            'currency' => 'EUR',
            'amountExcludingVat' => 100,
            'amountIncludingVat' => 120,
            'transactionsCount' => 1,
            'vatPaymentOption' => 'customer',
            'taxSubtotals' => [[
                'category' => 'standard',
                'percentage' => 20,
                'taxableAmount' => 100,
                'taxAmount' => 20,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function b2biPaymentPayload(string $invoiceNumber): array
    {
        return [
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => '2026-01-05',
            'paymentDate' => '2026-01-20',
            'taxSubtotals' => [[
                'percentage' => 20,
                'category' => 'standard',
                'currency' => 'EUR',
                'country' => 'FR',
                'amountIncludingTax' => 120,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function b2cPaymentPayload(string $date): array
    {
        return [
            'date' => $date,
            'taxSubtotal' => [[
                'category' => 'standard',
                'percentage' => 20,
                'country' => 'FR',
                'currency' => 'EUR',
                'amount' => 120,
            ]],
        ];
    }
}
