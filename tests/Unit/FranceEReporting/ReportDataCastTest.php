<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\DataMapper\ReportData;
use App\Models\TransactionEvent;
use InvalidArgumentException;
use Tests\TestCase;

class ReportDataCastTest extends TestCase
{
    public function testItHydratesAndSerializesTheParentReportDataEnvelope(): void
    {
        $frReportPayload = $this->combinedReportPayload();
        $payload = [
            'schemaVersion' => 1,
            'frReport' => $frReportPayload,
        ];

        $event = new TransactionEvent();
        $event->setRawAttributes([
            'reporting_data' => json_encode($payload, JSON_THROW_ON_ERROR),
        ], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertInstanceOf(FRReportData::class, $reportData->frReport);
        $this->assertSame('IN', $reportData->frReport->typeCode);
        $this->assertSame($frReportPayload, $reportData->frReport->toArray());
        $this->assertSame($payload, $reportData->toArray());
        $this->assertSame(['schemaVersion', 'frReport'], array_keys($reportData->toArray()));
        $this->assertArrayNotHasKey('documentType', $reportData->frReport->toArray());
        $this->assertArrayNotHasKey('frEReport', $reportData->frReport->toArray());

        $event->reporting_data = $reportData;

        $this->assertSame($payload, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItWrapsDirectFranceReportPayloadsForCompatibility(): void
    {
        $frReportPayload = $this->combinedReportPayload();

        $reportData = ReportData::fromArray($frReportPayload);

        $this->assertInstanceOf(FRReportData::class, $reportData->frReport);
        $this->assertSame([
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
        $this->expectExceptionMessage('ReportData requires at least one regional report.');

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
                    'publicIdentifiers' => [
                        [
                            'scheme' => 'FR:SIRET',
                            'id' => '12345678900012',
                        ],
                    ],
                ],
            ],
            'transactionReport' => [
                'period' => '2026-09-01 - 2026-09-10',
                'b2biInvoices' => [],
                'b2cTransactions' => [
                    [
                        'date' => '2026-09-10',
                        'category' => 'services',
                        'currency' => 'EUR',
                        'amountExcludingVat' => '1000.00',
                        'amountIncludingVat' => '1200.00',
                        'transactionsCount' => 4,
                        'vatPaymentOption' => 'on_collection',
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
                'b2biPayments' => [],
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
