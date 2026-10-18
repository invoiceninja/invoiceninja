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

namespace Tests\Unit;

use App\Helpers\Invoice\InclusiveTax;
use PHPUnit\Framework\TestCase;

/**
 * Audit-grade invariants for the single inclusive-tax back-out routine.
 *
 */
class InclusiveTaxTest extends TestCase
{
    public function testKnownVectors()
    {
        // 1000 @ 2x10% -> each tax round(1000*10/120)=83.33, total 166.66, net 833.34
        $r = InclusiveTax::backout(1000, [10, 10, 0]);
        $this->assertEquals(833.34, $r['net']);
        $this->assertEquals(166.66, $r['tax']);
        $this->assertEquals([83.33, 83.33, 0.0], $r['components']);

        // Exact inverse of exclusive: 1200 @ 2x10% -> net 1000, tax 200
        $r = InclusiveTax::backout(1200, [10, 10, 0]);
        $this->assertEquals(1000.0, $r['net']);
        $this->assertEquals(200.0, $r['tax']);

        // Single tax is unchanged from the classic back-out: 110 @ 10% -> net 100, tax 10
        $r = InclusiveTax::backout(110, [10, 0, 0]);
        $this->assertEquals(100.0, $r['net']);
        $this->assertEquals(10.0, $r['tax']);

        // Zero rates -> no tax, net == gross
        $r = InclusiveTax::backout(50, [0, 0, 0]);
        $this->assertEquals(50.0, $r['net']);
        $this->assertEquals(0.0, $r['tax']);
    }

    public function testNegativeInclusiveTaxesAreBackedOut(): void
    {
        $negative = InclusiveTax::backout(90, [-10, 0, 0]);

        $this->assertSame(100.0, $negative['net']);
        $this->assertSame(-10.0, $negative['tax']);
        $this->assertSame([-10.0, 0.0, 0.0], $negative['components']);

        $offsetting = InclusiveTax::backout(90, [10, -10, 0]);

        $this->assertSame(90.0, $offsetting['net']);
        $this->assertSame(0.0, $offsetting['tax']);
        $this->assertSame([9.0, -9.0, 0.0], $offsetting['components']);
    }

    public function testAggregateRatesAtOrBelowNegativeOneHundredRemainNonThrowing(): void
    {
        $rateSets = [
            [-100, 0, 0],
            [-0.01, -64.04, -35.95],
            [-110, 0, 0],
        ];

        foreach ($rateSets as $rates) {
            $result = InclusiveTax::backout(90, $rates);

            $this->assertSame(90.0, $result['net']);
            $this->assertSame(0.0, $result['tax']);
            $this->assertSame([0.0, 0.0, 0.0], $result['components']);
        }
    }

    public function testMalformedAndNonFiniteRatesGracefullyBecomeZero(): void
    {
        $invalid = InclusiveTax::backout(90, ['bad-rate', [], NAN]);

        $this->assertSame(90.0, $invalid['net']);
        $this->assertSame(0.0, $invalid['tax']);
        $this->assertSame([0.0, 0.0, 0.0], $invalid['components']);

        $partiallyValid = InclusiveTax::backout(110, [10, INF, null]);

        $this->assertSame(100.0, $partiallyValid['net']);
        $this->assertSame(10.0, $partiallyValid['tax']);
        $this->assertSame([10.0, 0.0, 0.0], $partiallyValid['components']);
    }

    /**
     * Deterministic sweep. A fixed grid (not random, so it is reproducible by an
     * auditor) proves the guarantees hold for every case:
     *   I1  net + sum(components) === gross              (document reconciles)
     *   I2  tax === round(sum(components))               (total is the parts)
     *   I3  component_i === round(gross * rate / (100+R))(exact, auditor-reproducible)
     *   I4  |component_i - round(net * rate)| <= 0.01    (rate-of-net, within a cent;
     *                                                     NOT exact at sub-cent tails)
     *   I5  components/net are non-negative
     */
    public function testReconciliationInvariantsAcrossSweep()
    {
        $rate_sets = [
            [10, 10, 0],
            [10, 17.5, 0],
            [5, 5, 5],
            [20, 0, 0],
            [10, 10, 10],
            [7.5, 2.5, 0],
            [13, 0, 0],
            [15, 6, 0],
        ];

        $checked = 0;

        // every cent from 0.01 to 30.00, plus a spread of larger amounts
        $amounts = range(1, 3000);
        foreach ([12345, 99999, 100000, 250075, 500001, 1000000] as $big) {
            $amounts[] = $big;
        }

        foreach ($amounts as $cents) {
            $amount = round($cents / 100, 2);

            foreach ($rate_sets as $rates) {
                $r = InclusiveTax::backout($amount, $rates);
                $components = $r['components'];
                $combined = array_sum($rates);

                // I1
                $this->assertEqualsWithDelta($amount, round($r['net'] + array_sum($components), 2), 0.0, "I1 failed for {$amount} / " . implode(',', $rates));
                // I2
                $this->assertEquals(round(array_sum($components), 2), $r['tax'], "I2 failed for {$amount} / " . implode(',', $rates));

                foreach ($rates as $i => $rate) {
                    // I3 - exact, auditor-reproducible from the gross
                    $expected = $rate > 0 ? round($amount * $rate / (100 + $combined), 2) : 0.0;
                    $this->assertEquals($expected, $components[$i], "I3 failed for {$amount} / rate {$rate}");
                    // I4 - each tax equals rate-of-net within a cent (approximation)
                    $this->assertLessThanOrEqual(0.01, round(abs($components[$i] - round($r['net'] * $rate / 100, 2)), 2), "I4 failed for {$amount} / rate {$rate}");
                    // I5
                    $this->assertGreaterThanOrEqual(0, $components[$i]);
                }

                $this->assertGreaterThanOrEqual(0, $r['net']);

                $checked++;
            }
        }

        // guard against the loop silently doing nothing
        $this->assertGreaterThan(20000, $checked);
    }
}
