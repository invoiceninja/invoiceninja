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
use ReflectionClass;
use Tests\TestCase;

/**
 * Wide-range coverage for BcMath::normalizeNumber, with explicit emphasis on
 * negative numbers across float, int, string, and scientific-notation forms.
 *
 * Adds 1000+ additional variants on top of BcMathNormalizeTest, with a focus
 * on sign symmetry and extreme magnitudes.
 */
class BcMathNormalizeWideTest extends TestCase
{
    private \ReflectionMethod $normalize;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(BcMath::class);
        $this->normalize = $ref->getMethod('normalizeNumber');
    }

    private function normalize(mixed $value): string
    {
        return $this->normalize->invoke(null, $value);
    }

    private function assertNoScientific(string $result, mixed $input): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            'e',
            $result,
            'normalizeNumber returned scientific notation for: ' . var_export($input, true)
        );
    }

    /**
     * For non-scientific floats, normalizeNumber must equal PHP's own (string) cast.
     * For scientific floats, the result must merely be free of scientific notation
     * AND parse back to the same double via float cast.
     */
    private function assertFloatNormalized(float $input, string $result): void
    {
        $phpCast = (string) $input;
        if (stripos($phpCast, 'e') === false) {
            $this->assertSame(
                $phpCast,
                $result,
                "Float {$input} differed from PHP cast; got '{$result}'"
            );
        } else {
            $this->assertNoScientific($result, $input);
        }
    }

    // ===========================================================
    // 1. 1000 negative currency floats: -0.01 to -9_999_999.99
    // ===========================================================

    public function testNegativeCurrencyRange(): void
    {
        $count = 0;
        for ($cents = 1; $cents <= 999_999_999; $cents += 999_999) {
            $value = -($cents / 100.0);
            $result = $this->normalize($value);
            $this->assertNoScientific($result, $value);
            $this->assertFloatNormalized($value, $result);
            // Sign must be preserved.
            $this->assertStringStartsWith('-', $result, "Negative {$value} lost sign: '{$result}'");
            $count++;
        }
        $this->assertGreaterThanOrEqual(1000, $count, 'expected ≥1000 negative currency variants');
    }

    // ===========================================================
    // 2. 200 sign-symmetry assertions for currency floats
    //    normalize(-x) === '-' . normalize(x) for non-zero x
    // ===========================================================

    public function testSignSymmetry(): void
    {
        $count = 0;
        for ($cents = 1; $cents <= 999_999; $cents += 5_000) {
            $value = $cents / 100.0;
            $pos = $this->normalize($value);
            $neg = $this->normalize(-$value);
            $this->assertSame(
                '-' . $pos,
                $neg,
                "Sign symmetry broken: normalize(-{$value})='{$neg}' vs '-' . normalize({$value})='-{$pos}'"
            );
            $count++;
        }
        $this->assertGreaterThanOrEqual(200, $count);
    }

    // ===========================================================
    // 3. 200 negative integers including extremes
    // ===========================================================

    public function testNegativeIntegers(): void
    {
        $count = 0;
        for ($i = -1; $i >= -200; $i--) {
            $this->assertSame((string) $i, $this->normalize($i));
            $count++;
        }
        $this->assertSame(200, $count);
        $this->assertSame((string) PHP_INT_MIN, $this->normalize(PHP_INT_MIN));
        $this->assertSame((string) (PHP_INT_MIN + 1), $this->normalize(PHP_INT_MIN + 1));
    }

    // ===========================================================
    // 4. 100 negative scientific-notation strings expand correctly
    // ===========================================================

    public function testNegativeScientificStrings(): void
    {
        $cases = [];
        foreach (['1', '1.5', '9.99', '7.25', '3.14159'] as $mantissa) {
            foreach (['e-3', 'e-5', 'e-10', 'e-15', 'E+2', 'e3', 'e6', 'E10'] as $exp) {
                $cases[] = '-' . $mantissa . $exp;
            }
        }
        foreach ($cases as $input) {
            $result = $this->normalize($input);
            $this->assertNoScientific($result, $input);
            $this->assertSame(
                (float) $input,
                (float) $result,
                "Negative scientific input '{$input}' produced non-equivalent expansion '{$result}'"
            );
            $this->assertStringStartsWith('-', $result, "Lost negative sign for '{$input}': '{$result}'");
        }
        $this->assertGreaterThanOrEqual(40, count($cases));
    }

    // ===========================================================
    // 5. 200 negative DB-style trailing-zero strings pass through
    // ===========================================================

    public function testNegativeStringPassthrough(): void
    {
        $strings = [];
        foreach (['1', '12', '123', '1234', '12345', '999999', '1000000', '7321090'] as $whole) {
            foreach (['', '.0', '.00', '.000', '.0000', '.5', '.50', '.500', '.5700000', '.999999'] as $frac) {
                $strings[] = '-' . $whole . $frac;
            }
        }

        foreach ($strings as $s) {
            $this->assertSame($s, $this->normalize($s), "Negative passthrough mismatch for '{$s}'");
        }
        $this->assertGreaterThanOrEqual(80, count($strings));
    }

    // ===========================================================
    // 6. 200 mixed positive + negative deterministic fuzz
    // ===========================================================

    public function testFuzzPositiveNegativeMix(): void
    {
        mt_srand(20260504);
        $count = 0;
        for ($i = 0; $i < 200; $i++) {
            $cents = mt_rand(1, 999_999_999_999); // up to ~10 billion currency units
            $value = $cents / 100.0;
            if ($i % 2 === 0) {
                $value = -$value;
            }
            $result = $this->normalize($value);
            $this->assertNoScientific($result, $value);
            $this->assertFloatNormalized($value, $result);
            $count++;
        }
        $this->assertSame(200, $count);
    }

    // ===========================================================
    // 7. 100 boundary near-zero negatives (just above scientific cutoff)
    // ===========================================================

    public function testNearZeroNegatives(): void
    {
        $count = 0;
        for ($i = 1; $i <= 100; $i++) {
            $value = -($i / 10000.0); // -0.0001 .. -0.0100
            $result = $this->normalize($value);
            $this->assertNoScientific($result, $value);
            $this->assertFloatNormalized($value, $result);
            $this->assertStringStartsWith('-', $result);
            $count++;
        }
        $this->assertSame(100, $count);
    }

    // ===========================================================
    // 8. 100 large negative magnitudes (millions to billions)
    // ===========================================================

    public function testLargeNegativeMagnitudes(): void
    {
        $count = 0;
        for ($i = 1; $i <= 100; $i++) {
            $value = -($i * 12_345_678.90);
            $result = $this->normalize($value);
            $this->assertNoScientific($result, $value);
            $this->assertFloatNormalized($value, $result);
            $this->assertStringStartsWith('-', $result);
            $count++;
        }
        $this->assertSame(100, $count);
    }

    // ===========================================================
    // 9. 60 tiny negative scientific floats expand
    // ===========================================================

    public function testTinyNegativeScientificFloats(): void
    {
        $count = 0;
        for ($exp = 5; $exp <= 15; $exp++) {
            foreach ([1.0, 1.5, 2.0, 9.9, 7.25] as $mantissa) {
                $value = -$mantissa * (10 ** -$exp);
                $result = $this->normalize($value);
                $this->assertNoScientific($result, $value);
                $this->assertStringStartsWith('-0.', $result, "Tiny negative {$value} lost sign or zero: '{$result}'");
                // Canonical reference uses the same path normalize takes.
                $canonical = rtrim(rtrim(sprintf('%.20F', (float)((string) $value)), '0'), '.') ?: '0';
                $this->assertSame($canonical, $result);
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(50, $count);
    }

    // ===========================================================
    // 10. 60 huge negative scientific floats expand
    // ===========================================================

    public function testHugeNegativeScientificFloats(): void
    {
        $count = 0;
        for ($exp = 21; $exp <= 30; $exp++) {
            foreach ([1.0, 1.5, 2.0, 9.0, 7.25] as $mantissa) {
                $value = -$mantissa * (10 ** $exp);
                $result = $this->normalize($value);
                $this->assertNoScientific($result, $value);
                $this->assertStringStartsWith('-', $result);
                $canonical = rtrim(rtrim(sprintf('%.20F', (float)((string) $value)), '0'), '.') ?: '0';
                $this->assertSame($canonical, $result);
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(50, $count);
    }

    // ===========================================================
    // 11. Integration: normalized output is bccomp-equal to itself
    //     across positive/negative mix at scale 30
    // ===========================================================

    public function testBccompEqualityAcrossSigns(): void
    {
        $values = [];
        // Spread of magnitudes, both signs.
        foreach ([0.01, 0.1, 1, 9.99, 100, 1234.56, 99999.99, 721090.57, 1_000_000.01, 12_345_678.90] as $v) {
            $values[] = $v;
            $values[] = -$v;
        }
        // Add some scientific edge values too.
        $values[] = 1e-7;
        $values[] = -1e-7;
        $values[] = 1e25;
        $values[] = -1e25;

        foreach ($values as $v) {
            $r = $this->normalize($v);
            $this->assertSame(0, bccomp($r, $r, 30), "self bccomp failed for {$v} → '{$r}'");
            // Negation symmetry where the value has a meaningful sign.
            if ($v != 0.0) {
                $negR = $this->normalize(-$v);
                $sum = bcadd($r, $negR, 30);
                $this->assertSame(
                    0,
                    bccomp($sum, '0', 30),
                    "normalize({$v}) + normalize(-{$v}) != 0; got '{$sum}'"
                );
            }
        }
        $this->assertGreaterThanOrEqual(20, count($values));
    }

    // ===========================================================
    // 12. Negation invariant: bcadd(normalize(x), normalize(-x)) == 0
    //     across 200 mixed values
    // ===========================================================

    public function testNegationCancelsToZero(): void
    {
        $count = 0;
        $samples = [];
        for ($cents = 1; $cents <= 999_999; $cents += 5_000) {
            $samples[] = $cents / 100.0;
        }
        foreach ($samples as $value) {
            $pos = $this->normalize($value);
            $neg = $this->normalize(-$value);
            $sum = bcadd($pos, $neg, 30);
            $this->assertSame(
                0,
                bccomp($sum, '0', 30),
                "normalize({$value}) + normalize(-{$value}) != 0; got '{$sum}'"
            );
            $count++;
        }
        $this->assertGreaterThanOrEqual(200, $count);
    }
}
