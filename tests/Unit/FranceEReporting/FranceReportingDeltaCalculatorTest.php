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

use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\Services\EDocument\Standards\France\FranceReportEntryInverter;
use App\Services\EDocument\Standards\France\FranceReportingDeltaCalculator;
use App\Services\EDocument\Standards\France\FranceReportingSubject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FranceReportingDeltaCalculatorTest extends TestCase
{
    private FranceReportingDeltaCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new FranceReportingDeltaCalculator(new FranceReportEntryInverter());
    }

    public function test_unchanged_subject_produces_no_delta_or_snapshot(): void
    {
        $subject = $this->subject('invoice:1', '100.00', '120.00');

        $delta = $this->calculator->calculate([$subject], [$subject]);

        $this->assertTrue($delta->isEmpty());
        $this->assertSame([], $delta->snapshots);
    }

    public function test_new_subject_produces_current_entry_and_snapshot(): void
    {
        $subject = $this->subject('invoice:1', '100.00', '120.00');

        $delta = $this->calculator->calculate([$subject], []);

        $this->assertCount(1, $delta->entries);
        $this->assertSame($subject->entry, $delta->entries[0]);
        $this->assertSame([$subject], $delta->snapshots);
    }

    public function test_changed_subject_reverses_accepted_entry_then_adds_current_entry(): void
    {
        $accepted = $this->subject('invoice:1', '100.00', '120.00');
        $current = $this->subject('invoice:1', '150.00', '180.00');

        $delta = $this->calculator->calculate([$current], [$accepted]);

        $this->assertCount(2, $delta->entries);
        $this->assertSame(-100, $delta->entries[0]->b2cTransaction?->toArray()['amountExcludingVat']);
        $this->assertSame(-120, $delta->entries[0]->b2cTransaction?->toArray()['amountIncludingVat']);
        $this->assertArrayNotHasKey('transactionsCount', $delta->entries[0]->b2cTransaction?->toArray() ?? []);
        $this->assertSame($current->entry, $delta->entries[1]);
        $this->assertSame([$current], $delta->snapshots);
    }

    public function test_removed_subject_produces_reversal_and_tombstone_snapshot(): void
    {
        $accepted = $this->subject('invoice:1', '100.00', '120.00');

        $delta = $this->calculator->calculate([], [$accepted]);

        $this->assertCount(1, $delta->entries);
        $this->assertSame(-100, $delta->entries[0]->b2cTransaction?->toArray()['amountExcludingVat']);
        $this->assertCount(1, $delta->snapshots);
        $this->assertSame('invoice:1', $delta->snapshots[0]->key);
        $this->assertNull($delta->snapshots[0]->entry);
    }

    public function test_report_context_change_replaces_even_an_unchanged_subject(): void
    {
        $subject = $this->subject('invoice:1', '100.00', '120.00');

        $delta = $this->calculator->replace([$subject], [$subject]);

        $this->assertCount(2, $delta->entries);
        $this->assertSame(-120, $delta->entries[0]->b2cTransaction?->toArray()['amountIncludingVat']);
        $this->assertSame(120, $delta->entries[1]->b2cTransaction?->toArray()['amountIncludingVat']);
        $this->assertSame([$subject], $delta->snapshots);
    }

    public function test_duplicate_subject_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate France reporting subject');

        $this->calculator->calculate([
            $this->subject('invoice:1', '100.00', '120.00'),
            $this->subject('invoice:1', '200.00', '240.00'),
        ], []);
    }

    private function subject(string $key, string $excludingVat, string $includingVat): FranceReportingSubject
    {
        $tax = (string) ((float) $includingVat - (float) $excludingVat);

        return new FranceReportingSubject(
            key: $key,
            entry: FRReportEntryData::fromB2CTransaction(new B2CTransactionData(
                date: '2026-08-01',
                category: 'TLB1',
                currency: 'EUR',
                amountExcludingVat: $excludingVat,
                amountIncludingVat: $includingVat,
                transactionsCount: 1,
                taxSubtotals: [new TaxSubtotalData(
                    percentage: 20,
                    category: 'S',
                    taxableAmount: $excludingVat,
                    taxAmount: $tax,
                )],
            )),
            clientId: 1,
            invoiceId: 1,
        );
    }
}
