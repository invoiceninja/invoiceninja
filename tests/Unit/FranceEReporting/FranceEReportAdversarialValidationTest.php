<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\Models\Company;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportContext;
use App\Services\EDocument\Standards\France\FranceEReportStorecoveProjection;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class FranceEReportAdversarialValidationTest extends TestCase
{
    private const GUID = 'f17b51f1-347d-4991-b65b-c65f4bccda30';

    public function testTheFinalStorecoveProjectionHasOneStrictPublicShape(): void
    {
        $document = $this->validDocument();
        $projection = FranceEReportStorecoveProjection::from([
            'legalEntityId' => 100042,
            'idempotencyGuid' => self::GUID,
            'document' => $document,
            'tenant_id' => 'internal-only',
            'routing' => ['ignored' => true],
        ]);

        $this->assertSame([
            'legalEntityId' => 100042,
            'idempotencyGuid' => self::GUID,
            'document' => $document,
        ], $projection);

        foreach ([
            ['legalEntityId' => '100042', 'idempotencyGuid' => self::GUID, 'document' => $document],
            ['legalEntityId' => 100042, 'idempotencyGuid' => 'invalid', 'document' => $document],
            ['legalEntityId' => 100042, 'idempotencyGuid' => self::GUID, 'document' => ['documentType' => 'invoice']],
        ] as $invalid) {
            $this->assertInvalid(static fn () => FranceEReportStorecoveProjection::from($invalid));
        }

        $unsupported = $projection;
        $unsupported['document']['frEReport']['declarantParty']['party']['address'] = ['country' => 'FR'];
        $this->assertInvalid(
            static fn () => FranceEReportStorecoveProjection::from($unsupported),
            'contains unsupported fields: address',
        );

        $nullSibling = $projection;
        $nullSibling['document']['frEReport']['paymentReport'] = null;
        $this->assertInvalid(
            static fn () => FranceEReportStorecoveProjection::from($nullSibling),
            'must be omitted when null',
        );

        $emptyRows = $projection;
        $emptyRows['document']['frEReport']['transactionReport']['b2biInvoices'] = [];
        $this->assertInvalid(
            static fn () => FranceEReportStorecoveProjection::from($emptyRows),
            'must be omitted when empty',
        );
    }

    public function testTheGatewayRejectsALegalEntityMismatchBeforeTransport(): void
    {
        $company = new Company();
        $company->setRawAttributes(['id' => 42, 'legal_entity_id' => 100042], true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('legalEntityId does not match');

        (new Storecove())->proxy->setCompany($company)->submitDocument([
            'legalEntityId' => 100043,
            'idempotencyGuid' => self::GUID,
            'document' => [
                'documentType' => 'fr_e_report',
                'frEReport' => ['typeCode' => 'IN'],
            ],
        ]);
    }

    public function testPeriodsMustSpanAtLeastTwoDistinctDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('periodEnd must be after periodStart');

        new FranceEReportContext(
            companyId: 42,
            legalEntityId: 100042,
            periodStart: '2026-09-10',
            periodEnd: '2026-09-10',
            issuedAt: CarbonImmutable::parse('2026-09-11 09:00:00 Europe/Paris'),
        );
    }

    public function testDirectReportSectionsAlsoRejectInvalidOrOneDayPeriods(): void
    {
        foreach (['2026-02-30 - 2026-03-01', '2026-09-10 - 2026-09-10'] as $period) {
            $this->assertInvalid(static fn () => new TransactionReportData(
                period: $period,
                b2cTransactions: [new B2CTransactionData(
                    date: '2026-09-10',
                    category: 'TLB1',
                    currency: 'EUR',
                    amountExcludingVat: 100,
                    amountIncludingVat: 120,
                    taxSubtotals: [new TaxSubtotalData(
                        percentage: 20,
                        category: 'standard',
                        taxableAmount: 100,
                        taxAmount: 20,
                    )],
                )],
            ));
        }
    }

    public function testUnprovedB2BIPaymentShortcutFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('amount/currency mapping is disabled');

        new B2BIPaymentData(
            invoiceNumber: 'A-INV-001',
            paymentDate: '2026-09-20',
            amount: 120,
            currency: 'EUR',
            issueDate: '2026-09-05',
            taxSubtotals: [$this->b2biPaymentSubtotal()],
        );
    }

    public function testB2BIInvoiceProjectionRejectsUnknownOrInconsistentContent(): void
    {
        $unknownLine = $this->invoicePayload();
        $unknownLine['invoiceLines'][0]['quantity'] = 1;
        $this->assertInvalid(
            static fn () => B2BIInvoiceData::fromArray($unknownLine),
            'unsupported fields',
        );

        $unknownAddress = $this->invoicePayload();
        $unknownAddress['accountingCustomerParty']['party']['address']['state'] = 'Lazio';
        $this->assertInvalid(
            static fn () => B2BIInvoiceData::fromArray($unknownAddress),
            'address contains unsupported fields',
        );

        $wrongGross = $this->invoicePayload();
        $wrongGross['amountIncludingVat'] = 121;
        $this->assertInvalid(
            static fn () => B2BIInvoiceData::fromArray($wrongGross),
            'reconcile with taxSubtotals',
        );

        $wrongLineNet = $this->invoicePayload();
        $wrongLineNet['invoiceLines'][0]['amountExcludingVat'] = 99;
        $this->assertInvalid(
            static fn () => B2BIInvoiceData::fromArray($wrongLineNet),
            'invoiceLines must reconcile',
        );
    }

    public function testReportValuesRejectInvalidCodesPrecisionAndContextLeakage(): void
    {
        $this->assertInvalid(static fn () => new B2CTransactionData(
            date: '2026-09-05',
            category: 'UNKNOWN',
            currency: 'EUR',
            amountExcludingVat: 100,
            amountIncludingVat: 120,
            taxSubtotals: [new TaxSubtotalData(
                percentage: 20,
                category: 'standard',
                taxableAmount: 100,
                taxAmount: 20,
            )],
        ), 'category is not supported');

        $this->assertInvalid(static fn () => new TaxSubtotalData(
            percentage: 101,
            category: 'standard',
            country: 'FR',
            currency: 'EUR',
            amount: 120,
        ), 'between 0 and 100');

        $this->assertInvalid(static fn () => new TaxSubtotalData(
            percentage: 20,
            category: 'standard',
            country: 'France',
            currency: 'EUR',
            amount: 120,
        ), 'country code');

        $this->assertInvalid(static fn () => new TaxSubtotalData(
            percentage: 20,
            category: 'standard',
            country: 'FR',
            currency: 'EUR',
            amount: '120.001',
        ), 'two decimal places');

        $subtotalWithLeakedField = new TaxSubtotalData(
            percentage: 20,
            taxCategory: 'standard',
            category: 'standard',
            country: 'FR',
            currency: 'EUR',
            amount: 120,
        );
        $this->assertInvalid(
            static fn () => $subtotalWithLeakedField->toB2CPaymentArray(),
            'does not support taxCategory',
        );
    }

    public function testPeppolTaxCategoryCodesKeepTheirDistinctStorecoveMeanings(): void
    {
        $intraCommunity = new TaxSubtotalData(
            percentage: 0,
            taxCategory: 'K',
            taxableAmount: 100,
            taxAmount: 0,
            country: 'FR',
        );
        $reverseCharge = new TaxSubtotalData(
            percentage: 0,
            taxCategory: 'AE',
            taxableAmount: 100,
            taxAmount: 0,
            country: 'FR',
        );

        $this->assertSame('intra_community', $intraCommunity->toB2BITransactionArray()['taxCategory']);
        $this->assertSame('reverse_charge', $reverseCharge->toB2BITransactionArray()['taxCategory']);
    }

    public function test_transaction_and_payment_corrections_are_explicit_variants(): void
    {
        $this->assertTrue(FranceEReportVariant::TransactionRectificative->isTransaction());
        $this->assertTrue(FranceEReportVariant::TransactionRectificative->isRectificative());
        $this->assertFalse(FranceEReportVariant::PaymentRectificative->isTransaction());
        $this->assertTrue(FranceEReportVariant::PaymentRectificative->isRectificative());
        $this->assertSame('RE', FranceEReportVariant::TransactionRectificative->typeCode());
        $this->assertSame('RE', FranceEReportVariant::PaymentRectificative->typeCode());
    }

    /** @return array<string, mixed> */
    private function invoicePayload(): array
    {
        return [
            'invoiceNumber' => 'A-INV-001',
            'issueDate' => '2026-09-05',
            'documentCurrency' => 'EUR',
            'amountIncludingVat' => 120,
            'accountingSupplierParty' => [
                'party' => ['companyName' => 'Seller A', 'address' => ['country' => 'FR']],
                'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => '552100554']],
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

    private function b2biPaymentSubtotal(): TaxSubtotalData
    {
        return new TaxSubtotalData(
            percentage: 20,
            category: 'standard',
            currency: 'EUR',
            country: 'FR',
            amountIncludingTax: 120,
        );
    }

    /** @return array<string, mixed> */
    private function validDocument(): array
    {
        return [
            'documentType' => 'fr_e_report',
            'frEReport' => [
                'typeCode' => 'IN',
                'documentId' => 'FR-F10-VALID',
                'issueDate' => '2026-09-11',
                'issueTime' => '09:00:00',
                'timeZone' => '+0200',
                'declarantParty' => [
                    'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => '552100554']],
                    'party' => ['companyName' => 'Seller A'],
                ],
                'transactionReport' => [
                    'period' => '2026-09-01 - 2026-09-10',
                    'b2cTransactions' => [[
                        'date' => '2026-09-05',
                        'category' => 'TLB1',
                        'currency' => 'EUR',
                        'amountExcludingVat' => 100,
                        'amountIncludingVat' => 120,
                        'taxSubtotals' => [[
                            'category' => 'standard',
                            'percentage' => 20,
                            'taxableAmount' => 100,
                            'taxAmount' => 20,
                        ]],
                    ]],
                ],
            ],
        ];
    }

    private function assertInvalid(callable $callback, ?string $message = null): void
    {
        try {
            $callback();
            $this->fail('Expected invalid France e-report data to be rejected.');
        } catch (InvalidArgumentException $exception) {
            if ($message) {
                $this->assertStringContainsString($message, $exception->getMessage());
            } else {
                $this->addToAssertionCount(1);
            }
        }
    }
}
