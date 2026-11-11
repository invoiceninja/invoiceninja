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

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\DeclarantPartyData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\PartyData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\PublicIdentifierData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\Models\Company;
use App\Services\EDocument\Standards\France\FranceEReportContext;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class FranceEReportValidationTest extends TestCase
{
    private const GUID = '31cc5691-d83a-4e49-a6b3-271f3c8d2cb7';

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testItRejectsInvalidReportIdentifiersAndSectionCardinality(): void
    {
        foreach ([str_repeat('A', 51), 'invalid:id', ' leading-space'] as $documentId) {
            try {
                $this->transactionReport($documentId);
                $this->fail('Expected invalid documentId to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('documentId', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        new FRReportData(
            typeCode: 'IN',
            documentId: 'FR-F10-VALID',
            issueDate: '2026-10-01',
            issueTime: '09:00:00',
            timeZone: '+0200',
            transactionReport: $this->transactionSection(),
            paymentReport: $this->paymentSection('A-INV-001'),
        );
    }

    public function testItRejectsInvalidLegalEntityAndParisOffsetContexts(): void
    {
        foreach ([
            fn () => new FranceEReportContext(1, 0, '2026-09-01', '2026-09-10', CarbonImmutable::parse('2026-09-11 09:00:00 Europe/Paris')),
            fn () => new FranceEReportContext(1, 100, '2026-09-01', '2026-09-10', CarbonImmutable::parse('2026-09-11 09:00:00 +0100')),
            fn () => new FranceEReportContext(1, 100, '2026-09-10', '2026-09-01', CarbonImmutable::parse('2026-09-11 09:00:00 Europe/Paris')),
        ] as $factory) {
            try {
                $factory();
                $this->fail('Expected invalid context to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testItAppliesTheTransitionalInvoiceIdLimitWithoutTruncating(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 12:00:00 Europe/Paris'));
        $invoiceId = '123456789012345678901';
        $report = $this->paymentReport($invoiceId, '2026-02-01', '+0100');
        $builder = new FranceEReportPayloadBuilder();

        try {
            $builder->build(
                $this->company(),
                new FranceEReportContext(1, 100, '2026-01-01', '2026-01-31', CarbonImmutable::parse('2026-02-01 09:00:00 Europe/Paris')),
                $report,
                self::GUID,
            );
            $this->fail('Expected the transitional invoice ID limit to be enforced.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('20-character', $exception->getMessage());
        }

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-01 12:00:00 Europe/Paris'));

        try {
            $future = $builder->build(
                $this->company(),
                new FranceEReportContext(1, 100, '2026-01-01', '2026-01-31', CarbonImmutable::parse('2026-02-01 09:00:00 Europe/Paris')),
                $report,
                self::GUID,
            );
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame($invoiceId, $future['document']['frEReport']['paymentReport']['b2biPayments'][0]['invoiceNumber']);
    }

    public function testItRejectsUnsupportedOrMalformedAmountsAndPaymentSubtotals(): void
    {
        foreach ([
            fn () => $this->b2cTransaction(currency: 'USD'),
            fn () => $this->b2cTransaction(gross: 121),
            fn () => new B2CPaymentData(
                date: '2026-09-20',
                taxSubtotal: [new TaxSubtotalData(
                    percentage: 20,
                    category: 'standard',
                    country: 'FR',
                    amount: 120,
                )],
            ),
        ] as $factory) {
            try {
                $factory();
                $this->fail('Expected unsupported or malformed report data to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTransactionsCountMayBeOmittedOrZero(): void
    {
        $omitted = $this->b2cTransaction()->toArray();
        $zero = $this->b2cTransaction(transactionsCount: 0)->toArray();

        $this->assertArrayNotHasKey('transactionsCount', $omitted);
        $this->assertSame(0, $zero['transactionsCount']);
    }

    public function testItBuildsTransactionReButRejectsAnInvalidIdempotencyGuidAtTheProviderBoundary(): void
    {
        $context = new FranceEReportContext(
            1,
            100,
            '2026-09-01',
            '2026-09-30',
            CarbonImmutable::parse('2026-10-01 09:00:00 Europe/Paris'),
        );
        $transactionRe = new FRReportData(
            typeCode: 'RE',
            documentId: 'FR-F10-TRANSACTION-RE',
            issueDate: '2026-10-01',
            issueTime: '09:00:00',
            timeZone: '+0200',
            declarantParty: $this->declarant(),
            transactionReport: $this->transactionSection(),
        );

        $payload = (new FranceEReportPayloadBuilder())->build($this->company(), $context, $transactionRe, self::GUID);
        $this->assertSame('RE', data_get($payload, 'document.frEReport.typeCode'));
        $this->assertArrayHasKey('transactionReport', data_get($payload, 'document.frEReport'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotencyGuid');
        (new FranceEReportPayloadBuilder())->build($this->company(), $context, $this->transactionReport(), 'not-a-guid');
    }

    private function transactionReport(string $documentId = 'FR-F10-REPORT-ID-LONGER-THAN-20'): FRReportData
    {
        return new FRReportData(
            typeCode: 'IN',
            documentId: $documentId,
            issueDate: '2026-10-01',
            issueTime: '09:00:00',
            timeZone: '+0200',
            declarantParty: $this->declarant(),
            transactionReport: $this->transactionSection(),
        );
    }

    private function paymentReport(
        string $invoiceId,
        string $issueDate,
        string $timeZone,
        string $period = '2026-01-01 - 2026-01-31',
    ): FRReportData {
        return new FRReportData(
            typeCode: 'IN',
            documentId: 'FR-F10-PAYMENT-IN',
            issueDate: $issueDate,
            issueTime: '09:00:00',
            timeZone: $timeZone,
            declarantParty: $this->declarant(),
            paymentReport: $this->paymentSection($invoiceId, $period),
        );
    }

    private function transactionSection(): TransactionReportData
    {
        return new TransactionReportData(
            period: '2026-09-01 - 2026-09-30',
            b2cTransactions: [$this->b2cTransaction()],
        );
    }

    private function paymentSection(string $invoiceId, string $period = '2026-01-01 - 2026-01-31'): PaymentReportData
    {
        return new PaymentReportData(
            period: $period,
            b2biPayments: [new B2BIPaymentData(
                invoiceNumber: $invoiceId,
                issueDate: '2026-01-05',
                paymentDate: '2026-01-20',
                taxSubtotals: [new TaxSubtotalData(
                    percentage: 20,
                    category: 'standard',
                    currency: 'EUR',
                    country: 'FR',
                    amountIncludingTax: 120,
                )],
            )],
        );
    }

    private function b2cTransaction(
        string $currency = 'EUR',
        int $gross = 120,
        ?int $transactionsCount = null,
    ): B2CTransactionData
    {
        return new B2CTransactionData(
            date: '2026-09-20',
            category: 'TLB1',
            currency: $currency,
            amountExcludingVat: 100,
            amountIncludingVat: $gross,
            transactionsCount: $transactionsCount,
            taxSubtotals: [new TaxSubtotalData(
                percentage: 20,
                category: 'standard',
                taxableAmount: 100,
                taxAmount: 20,
            )],
        );
    }

    private function declarant(): DeclarantPartyData
    {
        return new DeclarantPartyData(
            party: new PartyData(companyName: 'Seller A'),
            publicIdentifiers: [new PublicIdentifierData('FR:SIRENE', '552100554')],
        );
    }

    private function company(): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            'id' => 1,
            'legal_entity_id' => 100,
        ], true);

        return $company;
    }
}
