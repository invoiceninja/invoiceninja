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

use App\Utils\BcMath;
use Tests\TestCase;

/**
 * 500 variants per touched BcMath method, with INDEPENDENT oracles.
 *
 * Test integrity rules followed in this file:
 *
 *   1. The expected value is NEVER computed by re-running the production
 *      normalization or bcmath path on the same input. A bug shared by
 *      both sides would otherwise hide itself.
 *
 *   2. Inputs are generated as integer "cents" (or hand-written strings)
 *      and the expected result is computed with closed-form INTEGER math
 *      (PHP_INT arithmetic), then formatted by hand into a decimal string.
 *      The actual value is computed by passing the SAME cents through
 *      different surface forms (string, float, int) into BcMath.
 *
 *   3. Variable names make the split obvious:
 *      - $expectedValue   — derived from independent integer math
 *      - $actualValue     — what BcMath actually returned
 *
 * Touched methods (from `grep BcMath::` over app/):
 *   equal (21), mul (11), sub (8), comp (6), lessThan (5),
 *   greaterThan (4), round (3), add (3), isZero (1)
 */
class BcMathMethodsTest extends TestCase
{
    /**
     * Format an integer cent count as a decimal string with 2 fractional digits.
     * E.g. 80235 → "802.35", -5 → "-0.05", 0 → "0.00".
     */
    private function centsToString(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        return $sign . sprintf('%d.%02d', intdiv($abs, 100), $abs % 100);
    }

    /**
     * Format an integer micros count (1e-6 units) at the given decimal scale.
     * Truncates (not rounds) to match bcmath's truncating behavior.
     */
    private function microsToString(int $micros, int $scale): string
    {
        $sign = $micros < 0 ? '-' : '';
        $abs = abs($micros);
        $whole = intdiv($abs, 1_000_000);
        $frac = $abs % 1_000_000;
        // Six-digit fractional, then trim to scale.
        $fracStr = str_pad((string) $frac, 6, '0', STR_PAD_LEFT);
        if ($scale === 0) {
            return $sign . (string) $whole;
        }
        if ($scale <= 6) {
            return $sign . $whole . '.' . substr($fracStr, 0, $scale);
        }
        return $sign . $whole . '.' . $fracStr . str_repeat('0', $scale - 6);
    }

    /**
     * Generate 500 pairs of integer cents (a, b) deterministically.
     * Returns an array of [aCents, bCents].
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function centsPairs(int $seed): array
    {
        mt_srand($seed);
        $out = [];
        // Block 1: 200 small currency pairs, both positive.
        for ($i = 0; $i < 200; $i++) {
            $out[] = [mt_rand(1, 999_999), mt_rand(1, 999_999)];
        }
        // Block 2: 150 mixed-sign currency pairs.
        for ($i = 0; $i < 150; $i++) {
            $a = mt_rand(1, 9_999_999) * ($i % 2 === 0 ? -1 : 1);
            $b = mt_rand(1, 9_999_999) * ($i % 2 === 1 ? -1 : 1);
            $out[] = [$a, $b];
        }
        // Block 3: 100 large-magnitude pairs (still int-safe).
        for ($i = 0; $i < 100; $i++) {
            $a = mt_rand(100_000_000, 99_999_999_999);
            $b = mt_rand(100_000_000, 99_999_999_999);
            if ($i % 3 === 0) {
                $a = -$a;
            }
            if ($i % 3 === 1) {
                $b = -$b;
            }
            $out[] = [$a, $b];
        }
        // Block 4: 50 small-magnitude (under $1) pairs, mixed sign.
        for ($i = 0; $i < 50; $i++) {
            $a = mt_rand(1, 99) * ($i % 2 ? -1 : 1);
            $b = mt_rand(1, 99) * ($i % 3 ? -1 : 1);
            $out[] = [$a, $b];
        }
        return $out;
    }

    /**
     * Choose how to surface a cent value to the helper, varying by index.
     * Uses ONLY exact-representable transformations:
     *   - direct decimal string (centsToString)
     *   - integer-only forms when fractional cents are zero
     *   - exact-representable float ($cents / 100.0 is exact for |cents| up to 2^53)
     */
    private function surfaceCents(int $cents, int $variant): mixed
    {
        return match ($variant % 3) {
            0 => $this->centsToString($cents),       // string
            1 => $cents / 100.0,                     // exact float
            2 => $cents % 100 === 0 ? intdiv($cents, 100) : $this->centsToString($cents), // int when whole
        };
    }

    // ====================================================================
    // add — 500 cases. Oracle: integer-cents addition then format.
    // ====================================================================

    public function test_add_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1001) as $i => [$aCents, $bCents]) {
            $sumCents = $aCents + $bCents;
            $expectedValue = $this->centsToString($sumCents);

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::add($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "add: {$aCents}c + {$bCents}c = {$sumCents}c → expected '{$expectedValue}', got '{$actualValue}'"
            );
        }
    }

    // ====================================================================
    // sub — 500 cases. Oracle: integer-cents subtraction then format.
    // ====================================================================

    public function test_sub_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1002) as $i => [$aCents, $bCents]) {
            $diffCents = $aCents - $bCents;
            $expectedValue = $this->centsToString($diffCents);

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::sub($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "sub: {$aCents}c - {$bCents}c = {$diffCents}c → expected '{$expectedValue}', got '{$actualValue}'"
            );
        }
    }

    // ====================================================================
    // mul — 500 cases. Oracle: integer-cents multiplication, then scale.
    // a (cents) * b (cents) = product (cents²) = product/10000 in dollars.
    // Result formatted at scale=4 truncates to 4 decimal places.
    // ====================================================================

    public function test_mul_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1003) as $i => [$aCents, $bCents]) {
            // Skip pairs that would overflow PHP_INT in product on 64-bit.
            // 99_999_999_999 * 99_999_999_999 fits in 137 bits → use bcmul of the int strings as oracle for big ones.
            if (abs($aCents) > 1_000_000_000 || abs($bCents) > 1_000_000_000) {
                // Oracle for big pairs: bcmul of the integer cents strings,
                // divided by 10000 = simply place the decimal point.
                $productCentsSquared = bcmul((string) $aCents, (string) $bCents, 0);
                $expectedValue = $this->placeDecimal($productCentsSquared, 4, 4);
            } else {
                $productCentsSquared = $aCents * $bCents; // safe in PHP_INT
                // dollars * 10000 = $productCentsSquared, so dollars = productCentsSquared / 10000
                $expectedValue = $this->placeDecimal((string) $productCentsSquared, 4, 4);
            }

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::mul($a, $b, 4);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "mul: {$aCents}c × {$bCents}c → expected '{$expectedValue}', got '{$actualValue}'"
            );
        }
    }

    /**
     * Place a decimal point in an integer string at $shift positions from
     * the right, truncated to $scale fractional digits. Independent of bcmath.
     */
    private function placeDecimal(string $intStr, int $shift, int $scale): string
    {
        $sign = '';
        if (str_starts_with($intStr, '-')) {
            $sign = '-';
            $intStr = substr($intStr, 1);
        }
        if (strlen($intStr) <= $shift) {
            $intStr = str_repeat('0', $shift - strlen($intStr) + 1) . $intStr;
        }
        $whole = substr($intStr, 0, strlen($intStr) - $shift);
        $frac = substr($intStr, strlen($intStr) - $shift);
        if ($scale === 0) {
            $result = $whole;
        } else {
            // Truncate or pad to $scale.
            if (strlen($frac) >= $scale) {
                $frac = substr($frac, 0, $scale);
            } else {
                $frac = $frac . str_repeat('0', $scale - strlen($frac));
            }
            $result = $whole . '.' . $frac;
        }
        // bcmath emits "-0.0000" for some negative-zero results; match that exactly.
        return $sign . $result;
    }

    // ====================================================================
    // comp — 500 cases. Oracle: sign of (aCents - bCents).
    // ====================================================================

    public function test_comp_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1004) as $i => [$aCents, $bCents]) {
            $diff = $aCents - $bCents;
            $expectedValue = $diff <=> 0; // -1, 0, 1

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::comp($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "comp: {$aCents}c vs {$bCents}c → expected {$expectedValue}, got {$actualValue}"
            );
        }
    }

    // ====================================================================
    // equal — 500 cases. Oracle: aCents === bCents.
    // Plus 500 self-equality cases for currency floats.
    // ====================================================================

    public function test_equal_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1005) as $i => [$aCents, $bCents]) {
            $expectedValue = ($aCents === $bCents);

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::equal($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "equal: {$aCents}c == {$bCents}c expected " . ($expectedValue ? 'true' : 'false')
            );
        }
    }

    public function test_equal_500_self_equality_currency(): void
    {
        // Float vs hand-formatted string for the SAME cents must compare equal.
        // The expected boolean is the literal "true" — derived from the
        // mathematical fact that a/100 (cents) equals "a/100" (string), not from
        // any normalization/bccomp call.
        $count = 0;
        for ($cents = 1; $cents <= 999_999; $cents += 1999) {
            $expectedValue = true;

            // Surface the same cents value two different ways.
            $asFloat = $cents / 100.0;
            $asString = $this->centsToString($cents);

            $actualValue = BcMath::equal($asFloat, $asString, 2);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "equal({$asFloat}, '{$asString}') must be true"
            );

            $actualValueNeg = BcMath::equal(-$asFloat, $this->centsToString(-$cents), 2);
            $this->assertSame(
                $expectedValue,
                $actualValueNeg,
                "equal(-{$asFloat}, '" . $this->centsToString(-$cents) . "') must be true"
            );

            $count++;
        }
        $this->assertGreaterThanOrEqual(500, $count);
    }

    // ====================================================================
    // greaterThan — 500 cases. Oracle: aCents > bCents.
    // ====================================================================

    public function test_greaterThan_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1006) as $i => [$aCents, $bCents]) {
            $expectedValue = ($aCents > $bCents);

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::greaterThan($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "greaterThan: {$aCents}c > {$bCents}c expected " . ($expectedValue ? 'true' : 'false')
            );
        }
    }

    // ====================================================================
    // lessThan — 500 cases. Oracle: aCents < bCents.
    // ====================================================================

    public function test_lessThan_500_currency_pairs(): void
    {
        foreach ($this->centsPairs(1007) as $i => [$aCents, $bCents]) {
            $expectedValue = ($aCents < $bCents);

            $a = $this->surfaceCents($aCents, $i);
            $b = $this->surfaceCents($bCents, $i + 1);

            $actualValue = BcMath::lessThan($a, $b, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "lessThan: {$aCents}c < {$bCents}c expected " . ($expectedValue ? 'true' : 'false')
            );
        }
    }

    // ====================================================================
    // round — 500 cases. Oracle: hand-rolled half-away-from-zero rounding
    // on integer "micros" (1e-6 units), independent of PHP's round() and bcmath.
    // ====================================================================

    public function test_round_500_currency_pairs(): void
    {
        // Hand-anchored half-up cases (mathematical truth, not derived from any helper).
        $anchored = [
            ['0.5',    0, '1'],
            ['1.5',    0, '2'],
            ['2.5',    0, '3'],
            ['-0.5',   0, '-1'],
            ['-1.5',   0, '-2'],
            ['1.25',   1, '1.3'],
            ['-1.25',  1, '-1.3'],
            ['0.005',  2, '0.01'],
            ['-0.005', 2, '-0.01'],
            ['1.005',  2, '1.01'],
            ['1.234',  2, '1.23'],
            ['1.236',  2, '1.24'],
            ['9.999',  2, '10.00'],
            ['-9.999', 2, '-10.00'],
            ['0',      2, '0.00'],
        ];
        foreach ($anchored as [$in, $prec, $expected]) {
            $expectedValue = $expected;
            $actualValue = BcMath::round($in, $prec);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "round('{$in}', {$prec}) expected '{$expectedValue}' got '{$actualValue}'"
            );
        }

        // Property-based: 500 values built from integer micros, rounded by
        // hand-rolled half-away-from-zero, independent of PHP's round() and bcmath.
        mt_srand(1008);
        $count = 0;
        while ($count < 500) {
            $micros = mt_rand(0, 99_999_999); // 0 .. 99.999999
            if ($count % 2) {
                $micros = -$micros;
            }
            $precision = mt_rand(0, 5);

            $valueStr = $this->microsToString($micros, 6);
            $expectedValue = $this->roundMicrosHalfUp($micros, $precision);
            $actualValue = BcMath::round($valueStr, $precision);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "round('{$valueStr}', {$precision}) expected '{$expectedValue}' got '{$actualValue}'"
            );
            $count++;
        }
        $this->assertSame(500, $count);
    }

    /**
     * Hand-rolled half-away-from-zero rounding on a value expressed in micros (1e-6).
     * Returns the formatted decimal string at the given precision.
     */
    private function roundMicrosHalfUp(int $micros, int $precision): string
    {
        $sign = $micros < 0 ? '-' : '';
        $abs = abs($micros);

        // We have $abs in micros (1e-6). Target is $precision decimal places, i.e.
        // unit = 10^(6 - precision) micros. Round half away from zero.
        $unit = 10 ** (6 - $precision);
        if ($unit <= 0) {
            // precision >= 6 → no rounding needed; truncation behavior is exact.
            return $sign . $this->microsToStringAbs($abs, $precision);
        }

        $halfUnit = intdiv($unit, 2);
        $quotient = intdiv($abs, $unit);
        $remainder = $abs % $unit;
        if ($remainder >= $halfUnit) {
            $quotient++;
        }

        // $quotient now represents the value in units of 10^(-precision).
        if ($precision === 0) {
            return $sign . (string) $quotient;
        }
        $tenPow = 10 ** $precision;
        $whole = intdiv($quotient, $tenPow);
        $frac = $quotient % $tenPow;
        return $sign . $whole . '.' . str_pad((string) $frac, $precision, '0', STR_PAD_LEFT);
    }

    private function microsToStringAbs(int $abs, int $precision): string
    {
        $whole = intdiv($abs, 1_000_000);
        $frac = $abs % 1_000_000;
        if ($precision === 0) {
            return (string) $whole;
        }
        $fracStr = str_pad((string) $frac, 6, '0', STR_PAD_LEFT);
        if ($precision <= 6) {
            return $whole . '.' . substr($fracStr, 0, $precision);
        }
        return $whole . '.' . $fracStr . str_repeat('0', $precision - 6);
    }

    // ====================================================================
    // isZero — 500 non-zero values + every plausible zero spelling.
    // Oracle: literal true/false, derived from the input by inspection.
    // ====================================================================

    public function test_isZero_zero_forms(): void
    {
        $zeroForms = [
            0, 0.0, '0', '00', '0.0', '0.00', '0.000000', '-0', '-0.0', '-0.00',
            '0E0', '0e0', '+0', '0.0000000000', '.0', '0.', null, '',
        ];
        foreach ($zeroForms as $z) {
            $expectedValue = true;
            $actualValue = BcMath::isZero($z, 10);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                'isZero should be true for: ' . var_export($z, true)
            );
        }
    }

    public function test_isZero_500_nonzero_currency(): void
    {
        mt_srand(1009);
        $count = 0;
        while ($count < 500) {
            $cents = mt_rand(1, 99_999_999);
            if ($count % 2 === 0) {
                $cents = -$cents;
            }
            // Surface in mixed forms.
            $surface = $this->surfaceCents($cents, $count);

            $expectedValue = false; // by construction, $cents != 0
            $actualValue = BcMath::isZero($surface, 2);

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "isZero should be false for {$cents}c (" . var_export($surface, true) . ')'
            );
            $count++;
        }
        $this->assertSame(500, $count);
    }
}
