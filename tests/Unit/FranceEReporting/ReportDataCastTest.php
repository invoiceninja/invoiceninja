<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\DataMapper\ReportData;
use App\Models\TransactionEvent;
use InvalidArgumentException;
use Tests\TestCase;

class ReportDataCastTest extends TestCase
{
    public function testItHydratesAndSerializesDirectFranceReportPayloads(): void
    {
        $frReportPayload = $this->combinedReportPayload();

        $event = new TransactionEvent();
        $event->setRawAttributes([
            'reporting_data' => json_encode($frReportPayload, JSON_THROW_ON_ERROR),
        ], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertInstanceOf(FRReportData::class, $reportData->frReport);
        $this->assertSame('IN', $reportData->frReport->typeCode);
        $this->assertEquals($frReportPayload, $reportData->frReport->toArray());
        $this->assertEquals([
            'schemaVersion' => 1,
            'frReport' => $frReportPayload,
        ], $reportData->toArray());
        $this->assertSame(['schemaVersion', 'frReport'], array_keys($reportData->toArray()));
        $this->assertArrayNotHasKey('documentType', $reportData->frReport->toArray());
        $this->assertArrayNotHasKey('frEReport', $reportData->frReport->toArray());

        $b2biInvoice = $reportData->frReport->transactionReport->b2biInvoices[0]->toArray();
        $this->assertEquals($frReportPayload['transactionReport']['b2biInvoices'][0], $b2biInvoice);
        $this->assertArrayHasKey('taxCategory', $b2biInvoice['taxSubtotals'][0]);
        $this->assertArrayNotHasKey('category', $b2biInvoice['taxSubtotals'][0]);
        $this->assertArrayHasKey('amountExcludingVat', $b2biInvoice['invoiceLines'][0]);
        $this->assertArrayNotHasKey('amountExcludingTax', $b2biInvoice['invoiceLines'][0]);
        $this->assertArrayHasKey('party', $b2biInvoice['accountingSupplierParty']);
        $this->assertArrayHasKey('publicIdentifiers', $b2biInvoice['accountingSupplierParty']);
        $this->assertArrayHasKey('taxSubtotal', $reportData->frReport->paymentReport->b2cPayments[0]->toArray());

        $event->reporting_data = $reportData;

        $this->assertEquals($frReportPayload, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItHydratesAndSerializesDirectFranceReportEntryPayloads(): void
    {
        $b2biInvoicePayload = $this->combinedReportPayload()['transactionReport']['b2biInvoices'][0];

        $event = new TransactionEvent();
        $event->setRawAttributes([
            'reporting_data' => json_encode($b2biInvoicePayload, JSON_THROW_ON_ERROR),
        ], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertNull($reportData->frReport);
        $this->assertInstanceOf(FRReportEntryData::class, $reportData->frReportEntry);
        $this->assertEquals($b2biInvoicePayload, $reportData->frReportEntry->b2biInvoice->toArray());
        $this->assertEquals($b2biInvoicePayload, $reportData->toStorageArray());
        $this->assertSame(['schemaVersion', 'frReportEntry'], array_keys($reportData->toArray()));

        $event->reporting_data = $reportData;

        $this->assertEquals($b2biInvoicePayload, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItHydratesFranceReportEntriesUsingTransactionEventId(): void
    {
        $payload = $this->combinedReportPayload();

        $this->assertReportEntryHydratesFromEventId(
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            $payload['transactionReport']['b2biInvoices'][0],
            'b2biInvoice',
        );
        $this->assertReportEntryHydratesFromEventId(
            TransactionEvent::FR_B2C_TRANSACTION,
            $payload['transactionReport']['b2cTransactions'][0],
            'b2cTransaction',
        );
        $this->assertReportEntryHydratesFromEventId(
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            $payload['paymentReport']['b2biPayments'][0],
            'b2biPayment',
        );
        $this->assertReportEntryHydratesFromEventId(
            TransactionEvent::FR_B2C_PAYMENT,
            $payload['paymentReport']['b2cPayments'][0],
            'b2cPayment',
        );
    }

    public function testReportArraysSerializeAsLists(): void
    {
        $payload = $this->combinedReportPayload();
        $transactionReport = new TransactionReportData(
            period: '2026-09-01 - 2026-09-30',
            b2biInvoices: [1003 => B2BIInvoiceData::fromArray($payload['transactionReport']['b2biInvoices'][0])],
            b2cTransactions: [1001 => B2CTransactionData::fromArray($payload['transactionReport']['b2cTransactions'][0])],
        );
        $paymentReport = new PaymentReportData(
            period: '2026-09-01 - 2026-09-30',
            b2biPayments: [1004 => B2BIPaymentData::fromArray($payload['paymentReport']['b2biPayments'][0])],
            b2cPayments: [1002 => B2CPaymentData::fromArray($payload['paymentReport']['b2cPayments'][0])],
        );

        $this->assertTrue(array_is_list($transactionReport->toArray()['b2biInvoices']));
        $this->assertTrue(array_is_list($transactionReport->toArray()['b2cTransactions']));
        $this->assertTrue(array_is_list($paymentReport->toArray()['b2biPayments']));
        $this->assertTrue(array_is_list($paymentReport->toArray()['b2cPayments']));
    }
    public function testItWrapsDirectFranceReportPayloadsForCompatibility(): void
    {
        $frReportPayload = $this->combinedReportPayload();

        $reportData = ReportData::fromArray($frReportPayload);

        $this->assertInstanceOf(FRReportData::class, $reportData->frReport);
        $this->assertEquals([
            'schemaVersion' => 1,
            'frReport' => $frReportPayload,
        ], $reportData->toArray());
    }

    public function testItDefaultsMissingSchemaVersionsToOne(): void
    {
        $frReportPayload = $this->paymentReportPayload();
        unset($frReportPayload['schemaVersion']);

        $reportData = ReportData::fromArray([
            'frReport' => $frReportPayload,
        ]);

        $this->assertSame(1, $reportData->schemaVersion);
        $this->assertSame(1, $reportData->frReport->schemaVersion);
        $this->assertSame(1, $reportData->toArray()['schemaVersion']);
        $this->assertSame(1, $reportData->toArray()['frReport']['schemaVersion']);
    }

    public function testItRequiresAtLeastOneRegionalReport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ReportData requires at least one regional report or report entry.');

        ReportData::fromArray([
            'schemaVersion' => 1,
        ]);
    }

    public function testFranceReportsRequireAtLeastOneReportSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of transactionReport or paymentReport is required.');

        FRReportData::fromArray([
            'schemaVersion' => 1,
            'typeCode' => 'IN',
            'documentId' => 'FR-F10-2026-09',
            'issueDate' => '2026-10-10',
            'issueTime' => '09:00:00',
            'timeZone' => '+0200',
        ]);
    }

    public function testFranceReportsRejectEmptyPresentReportSections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('transactionReport requires at least one b2biInvoices or b2cTransactions item.');

        FRReportData::fromArray([
            'schemaVersion' => 1,
            'typeCode' => 'IN',
            'documentId' => 'FR-F10-2026-09',
            'issueDate' => '2026-10-10',
            'issueTime' => '09:00:00',
            'timeZone' => '+0200',
            'transactionReport' => [
                'period' => '2026-09-01 - 2026-09-10',
                'b2biInvoices' => [],
                'b2cTransactions' => [],
            ],
        ]);
    }

    public function testFactoryMethodsBuildRectificativeCombinedReports(): void
    {
        $report = FRReportData::combinedRectificativeReport(
            documentId: 'FR-F10-2026-09-RE',
            issueDate: '2026-10-11',
            issueTime: '10:30:00',
            timeZone: '+0200',
            transactionReport: new TransactionReportData(
                period: '2026-09-01 - 2026-09-10',
                b2cTransactions: [
                    new B2CTransactionData(
                        date: '2026-09-10',
                        category: 'services',
                        currency: 'EUR',
                        amountExcludingVat: '1000.00',
                        amountIncludingVat: '1200.00',
                        transactionsCount: 4,
                    ),
                ],
            ),
            paymentReport: new PaymentReportData(
                period: '2026-09-01 - 2026-09-30',
                b2cPayments: [
                    new B2CPaymentData(
                        date: '2026-09-25',
                        taxSubtotal: [
                            new TaxSubtotalData(
                                percentage: '20.0',
                                category: 'standard',
                                taxableAmount: '1000.00',
                                taxAmount: '200.00',
                                currency: 'EUR',
                            ),
                        ],
                    ),
                ],
            ),
        );

        $this->assertSame('RE', $report->typeCode);
        $this->assertArrayHasKey('transactionReport', $report->toArray());
        $this->assertArrayHasKey('paymentReport', $report->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertReportEntryHydratesFromEventId(int $eventId, array $payload, string $property): void
    {
        $event = new TransactionEvent();
        $event->setRawAttributes([
            'event_id' => $eventId,
            'reporting_data' => json_encode($payload, JSON_THROW_ON_ERROR),
        ], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertNull($reportData->frReport);
        $this->assertInstanceOf(FRReportEntryData::class, $reportData->frReportEntry);
        $this->assertEquals($payload, $reportData->frReportEntry->{$property}->toArray());
        $this->assertEquals($payload, $reportData->toStorageArray());

        $event->reporting_data = $reportData;

        $this->assertEquals($payload, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }
    /**
     * @return array<string, mixed>
     */
    private function combinedReportPayload(): array
    {
        return [
            'schemaVersion' => 1,
            'typeCode' => 'IN',
            'documentId' => 'FR-F10-2026-09',
            'issueDate' => '2026-10-10',
            'issueTime' => '09:00:00',
            'timeZone' => '+0200',
            'declarantParty' => [
                'party' => [
                    'companyName' => 'Example SAS',
                    'address' => [
                        'country' => 'FR',
                    ],
                ],
                'publicIdentifiers' => [
                    [
                        'scheme' => 'FR:SIRET',
                        'id' => '12345678900012',
                    ],
                ],
            ],
            'transactionReport' => [
                'period' => '2026-09-01 - 2026-09-10',
                'b2biInvoices' => [
                    [
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
                                [
                                    'scheme' => 'FR:VAT',
                                    'id' => 'FR99352022154',
                                ],
                            ],
                        ],
                        'accountingCustomerParty' => [
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
                    ],
                ],
                'b2cTransactions' => [
                    [
                        'date' => '2026-09-10',
                        'category' => 'TLB1',
                        'currency' => 'EUR',
                        'amountExcludingVat' => '1000.00',
                        'amountIncludingVat' => '1200.00',
                        'transactionsCount' => 4,
                        'vatPaymentOption' => 'customer',
                        'taxSubtotals' => [
                            [
                                'category' => 'standard',
                                'percentage' => '20.0',
                                'taxableAmount' => '1000.00',
                                'taxAmount' => '200.00',
                                'currency' => 'EUR',
                            ],
                        ],
                    ],
                ],
            ],
            'paymentReport' => [
                'period' => '2026-09-01 - 2026-09-30',
                'b2biPayments' => [
                    [
                        'invoiceNumber' => 'S2F3_REPORT2025_001',
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
                    ],
                ],
                'b2cPayments' => [
                    [
                        'date' => '2026-09-25',
                        'taxSubtotal' => [
                            [
                                'category' => 'standard',
                                'percentage' => '20.0',
                                'taxableAmount' => '1000.00',
                                'taxAmount' => '200.00',
                                'currency' => 'EUR',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentReportPayload(): array
    {
        return [
            'schemaVersion' => 1,
            'typeCode' => 'IN',
            'documentId' => 'FR-F10-2026-09',
            'issueDate' => '2026-10-10',
            'issueTime' => '09:00:00',
            'timeZone' => '+0200',
            'paymentReport' => [
                'period' => '2026-09-01 - 2026-09-30',
                'b2biPayments' => [],
                'b2cPayments' => [
                    [
                        'date' => '2026-09-25',
                    ],
                ],
            ],
        ];
    }
}
