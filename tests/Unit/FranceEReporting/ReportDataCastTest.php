<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\DataMapper\ReportData;
use App\Models\TransactionEvent;
use InvalidArgumentException;
use Tests\TestCase;

class ReportDataCastTest extends TestCase
{
    public function testItHydratesAndStoresOneDirectFranceReportSection(): void
    {
        $payload = $this->transactionReportPayload();
        $storage = ['schemaVersion' => 1, 'frReport' => $payload];
        $event = new TransactionEvent();
        $event->setRawAttributes(['reporting_data' => json_encode($storage, JSON_THROW_ON_ERROR)], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertInstanceOf(FRReportData::class, $reportData->frReport);
        $this->assertEquals($payload, $reportData->frReport->toArray());
        $this->assertArrayHasKey('transactionReport', $reportData->frReport->toArray());
        $this->assertArrayNotHasKey('paymentReport', $reportData->frReport->toArray());

        $event->reporting_data = $reportData;
        $this->assertEquals($storage, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItHydratesAllFourFranceReportEntryShapes(): void
    {
        $transaction = $this->transactionReportPayload()['transactionReport'];
        $payment = $this->paymentReportPayload()['paymentReport'];

        $this->assertReportEntryHydrates(
            $transaction['b2biInvoices'][0],
            'b2biInvoice',
        );
        $this->assertReportEntryHydrates(
            $transaction['b2cTransactions'][0],
            'b2cTransaction',
        );
        $this->assertReportEntryHydrates(
            $payment['b2biPayments'][0],
            'b2biPayment',
        );
        $this->assertReportEntryHydrates(
            $payment['b2cPayments'][0],
            'b2cPayment',
        );
    }

    public function testItHydratesAndStoresAFranceReportEntryEnvelope(): void
    {
        $payload = $this->transactionReportPayload()['transactionReport']['b2biInvoices'][0];
        $storage = [
            'schemaVersion' => 1,
            'frReportEntry' => [
                'schemaVersion' => 1,
                'b2biInvoice' => $payload,
            ],
        ];
        $event = new TransactionEvent();
        $event->setRawAttributes(['reporting_data' => json_encode($storage, JSON_THROW_ON_ERROR)], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(ReportData::class, $reportData);
        $this->assertNull($reportData->frReport);
        $this->assertInstanceOf(FRReportEntryData::class, $reportData->frReportEntry);
        $this->assertEquals($payload, $reportData->frReportEntry->b2biInvoice->toArray());
        $this->assertEquals($storage, $reportData->toStorageArray());

        $event->reporting_data = $reportData;
        $this->assertEquals($storage, json_decode($event->getAttributes()['reporting_data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItWrapsBareFranceReportsAndDefaultsSchemaVersions(): void
    {
        $payload = $this->paymentReportPayload();
        unset($payload['schemaVersion']);

        $wrapped = ReportData::fromArray(['frReport' => $payload]);

        $this->assertSame(1, $wrapped->schemaVersion);
        $this->assertSame(1, $wrapped->frReport->schemaVersion);
        $this->assertSame(1, $wrapped->toArray()['schemaVersion']);
        $this->assertSame(1, $wrapped->toArray()['frReport']['schemaVersion']);
        $this->assertArrayHasKey('paymentReport', $wrapped->frReport->toArray());
    }

    public function testReportDataStillRequiresARegionalReportOrEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires at least one regional report or report entry');

        ReportData::fromArray(['schemaVersion' => 1]);
    }

    public function testReportSectionsOmitUnusedArraysAndAlwaysSerializeLists(): void
    {
        $transactionPayload = $this->transactionReportPayload()['transactionReport'];
        $paymentPayload = $this->paymentReportPayload()['paymentReport'];
        $transactionReport = new TransactionReportData(
            period: '2026-09-01 - 2026-09-30',
            b2biInvoices: [1003 => B2BIInvoiceData::fromArray($transactionPayload['b2biInvoices'][0])],
        );
        $paymentReport = new PaymentReportData(
            period: '2026-09-01 - 2026-09-30',
            b2cPayments: [1002 => B2CPaymentData::fromArray($paymentPayload['b2cPayments'][0])],
        );

        $this->assertTrue(array_is_list($transactionReport->toArray()['b2biInvoices']));
        $this->assertArrayNotHasKey('b2cTransactions', $transactionReport->toArray());
        $this->assertTrue(array_is_list($paymentReport->toArray()['b2cPayments']));
        $this->assertArrayNotHasKey('b2biPayments', $paymentReport->toArray());
    }

    public function testFranceReportsRequireExactlyOneReportSection(): void
    {
        foreach ([
            $this->baseReportPayload(),
            [
                ...$this->baseReportPayload(),
                'transactionReport' => $this->transactionReportPayload()['transactionReport'],
                'paymentReport' => $this->paymentReportPayload()['paymentReport'],
            ],
        ] as $payload) {
            try {
                FRReportData::fromArray($payload);
                $this->fail('Expected invalid section cardinality to be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Exactly one of transactionReport or paymentReport is required.', $exception->getMessage());
            }
        }
    }

    public function testItRejectsEmptyPresentReportSections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('transactionReport requires at least one');

        FRReportData::fromArray([
            ...$this->baseReportPayload(),
            'transactionReport' => ['period' => '2026-09-01 - 2026-09-10'],
        ]);
    }

    public function testContextSpecificSubtotalSerializersDoNotLeakKeys(): void
    {
        $transaction = $this->transactionReportPayload()['transactionReport'];
        $payment = $this->paymentReportPayload()['paymentReport'];

        $b2biTransaction = B2BIInvoiceData::fromArray($transaction['b2biInvoices'][0])->toArray()['taxSubtotals'][0];
        $b2cTransaction = B2CTransactionData::fromArray($transaction['b2cTransactions'][0])->toArray()['taxSubtotals'][0];
        $b2biPayment = B2BIPaymentData::fromArray($payment['b2biPayments'][0])->toArray()['taxSubtotals'][0];
        $b2cPayment = B2CPaymentData::fromArray($payment['b2cPayments'][0])->toArray()['taxSubtotal'][0];

        $this->assertSame(['taxCategory', 'percentage', 'taxableAmount', 'taxAmount', 'country'], array_keys($b2biTransaction));
        $this->assertSame(['category', 'percentage', 'taxableAmount', 'taxAmount'], array_keys($b2cTransaction));
        $this->assertSame(['percentage', 'category', 'currency', 'country', 'amountIncludingTax'], array_keys($b2biPayment));
        $this->assertSame(['category', 'percentage', 'country', 'currency', 'amount'], array_keys($b2cPayment));
    }

    /** @param array<string, mixed> $payload */
    private function assertReportEntryHydrates(array $payload, string $property): void
    {
        $event = new TransactionEvent();
        $event->setRawAttributes([
            'reporting_data' => json_encode([
                'schemaVersion' => 1,
                'frReportEntry' => [
                    'schemaVersion' => 1,
                    $property => $payload,
                ],
            ], JSON_THROW_ON_ERROR),
        ], true);

        $reportData = $event->reporting_data;

        $this->assertInstanceOf(FRReportEntryData::class, $reportData->frReportEntry);
        $this->assertEquals($payload, $reportData->frReportEntry->{$property}->toArray());
    }

    /** @return array<string, mixed> */
    private function baseReportPayload(): array
    {
        return [
            'schemaVersion' => 1,
            'typeCode' => 'IN',
            'documentId' => 'FR-F10-REPORT-ID-LONGER-THAN-20',
            'issueDate' => '2026-10-01',
            'issueTime' => '09:00:00',
            'timeZone' => '+0200',
        ];
    }

    /** @return array<string, mixed> */
    private function transactionReportPayload(): array
    {
        return [
            ...$this->baseReportPayload(),
            'transactionReport' => [
                'period' => '2026-09-01 - 2026-09-30',
                'b2biInvoices' => [[
                    'invoiceNumber' => 'A-INV-001',
                    'issueDate' => '2026-09-05',
                    'documentCurrency' => 'EUR',
                    'amountIncludingVat' => 120,
                    'accountingSupplierParty' => [
                        'party' => ['companyName' => 'Seller', 'address' => ['country' => 'FR']],
                        'publicIdentifiers' => [['scheme' => 'FR:SIRENE', 'id' => '552100554']],
                    ],
                    'accountingCustomerParty' => [
                        'party' => ['companyName' => 'Customer', 'address' => ['country' => 'IT']],
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
                ]],
                'b2cTransactions' => [[
                    'date' => '2026-09-10',
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
        ];
    }

    /** @return array<string, mixed> */
    private function paymentReportPayload(): array
    {
        return [
            ...$this->baseReportPayload(),
            'paymentReport' => [
                'period' => '2026-09-01 - 2026-09-30',
                'b2biPayments' => [[
                    'invoiceNumber' => 'A-INV-001',
                    'issueDate' => '2026-09-05',
                    'paymentDate' => '2026-09-20',
                    'taxSubtotals' => [[
                        'percentage' => 20,
                        'category' => 'standard',
                        'currency' => 'EUR',
                        'country' => 'FR',
                        'amountIncludingTax' => 120,
                    ]],
                ]],
                'b2cPayments' => [[
                    'date' => '2026-09-20',
                    'taxSubtotal' => [[
                        'category' => 'standard',
                        'percentage' => 20,
                        'country' => 'FR',
                        'currency' => 'EUR',
                        'amount' => 120,
                    ]],
                ]],
            ],
        ];
    }
}
