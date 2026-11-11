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
 * 500 highly irregular floats with known IEEE-754 precision issues, run
 * through every touched BcMath method.
 *
 * Oracle strategy:
 *   For pathological floats we cannot easily produce an exact decimal
 *   expectation independent of PHP's own float→string conversion (that's
 *   the entire reason these floats are "pathological"). So instead of
 *   asserting exact strings, we assert ALGEBRAIC INVARIANTS that must
 *   hold for any correct implementation, regardless of which decimal
 *   representation the helper chose:
 *
 *     1. normalize($f) contains no scientific notation
 *     2. comp($f, $f) === 0                            (reflexivity)
 *     3. equal($f, $f) is true                          (reflexivity)
 *     4. !greaterThan($f, $f) and !lessThan($f, $f)     (irreflexivity)
 *     5. add($f, 0) equals normalize($f)                (additive identity)
 *     6. mul($f, 1) equals normalize($f)                (multiplicative identity)
 *     7. sub($f, $f) → isZero is true                   (self-cancellation)
 *     8. add($f, -$f) → isZero is true                  (negation cancellation)
 *     9. mul($f, 0) → isZero is true                    (zero absorption)
 *    10. equal($f, "(string) $f") is true               (float ⇄ canonical-string)
 *
 * The float-vs-canonical-string check at #10 is the original 721090.57
 * regression: a float must compare equal to its own PHP-cast string form,
 * regardless of any binary tail.
 */
class BcMathIrregularFloatsTest extends TestCase
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

    /**
     * 500 pathological floats spanning the well-known IEEE-754 trouble spots
     * for invoice / financial workloads.
     *
     * @return list<float>
     */
    private function irregular500(): array
    {
        $out = [];

        // ─────────────────────────────────────────────────────────────────
        // Block 1 — Hand-curated classics that are not exactly representable
        // in binary floating-point. (~40)
        // ─────────────────────────────────────────────────────────────────
        $classics = [
            0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9,
            0.07, 0.15, 0.25, 0.35, 0.45, 0.55, 0.65, 0.75, 0.85, 0.95,
            0.29, 0.58, 1.005, 1.015, 1.025, 1.035, 1.115,
            4.35, 4.45, 4.55,
            2.675, 3.145, 5.85, 9.95, 19.99, 29.99, 49.95,
            721090.57, 554.17, 1234567.89,
        ];
        foreach ($classics as $v) {
            $out[] = $v;
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 2 — Cumulative addition of 0.1 (textbook IEEE drift). (100)
        // ─────────────────────────────────────────────────────────────────
        $sum = 0.0;
        for ($i = 1; $i <= 100; $i++) {
            $sum += 0.1;
            $out[] = $sum;
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 3 — 1/n for n = 3..52 (50 unit fractions, mostly inexact).
        // ─────────────────────────────────────────────────────────────────
        for ($n = 3; $n <= 52; $n++) {
            $out[] = 1.0 / $n;
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 4 — 0.1 * n for n = 1..50 (50 — accumulates differently
        // than n * 0.1 in some paths, both are inexact for non-power-of-2 n).
        // ─────────────────────────────────────────────────────────────────
        for ($n = 1; $n <= 50; $n++) {
            $out[] = 0.1 * $n;
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 5 — Mathematical constants × small integer multipliers (~60).
        // PI, E, sqrt(2) etc. are transcendental/irrational → always inexact.
        // ─────────────────────────────────────────────────────────────────
        $constants = [M_PI, M_E, M_SQRT2, M_LN2, M_LOG2E, M_LN10];
        foreach ($constants as $c) {
            for ($k = 1; $k <= 10; $k++) {
                $out[] = $c * $k;
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 6 — Tax-rate-like products: rate% of a base (~70).
        // These are the values that show up most in invoice math.
        // ─────────────────────────────────────────────────────────────────
        $rates = [5.5, 7.25, 8.875, 10.5, 13.5, 15.25, 17.5, 19.6, 20.5, 21.0, 22.5, 24.0, 25.0, 27.0];
        foreach ($rates as $rate) {
            for ($base = 1.0; $base <= 100.0; $base *= 1.7) {
                $out[] = $base * $rate / 100;
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 7 — Subtractions that drift: integer minus a small inexact (~50).
        // ─────────────────────────────────────────────────────────────────
        foreach ([0.1, 0.2, 0.3, 0.7, 0.9, 0.99, 0.123, 0.456, 0.789] as $b) {
            for ($a = 1; $a <= 6; $a++) {
                $out[] = $a - $b;
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // Block 8 — Synthetic line-item drift: sum of pseudo-random fractions
        // accumulating across 3–7 line items each. (~70)
        // ─────────────────────────────────────────────────────────────────
        for ($i = 0; $i < 70; $i++) {
            $total = 0.0;
            $items = ($i % 5) + 3;
            for ($j = 0; $j < $items; $j++) {
                $total += ((($i * 7) + ($j * 13)) % 99) / 100.0;
            }
            $out[] = $total;
        }

        // Trim/pad to exactly 500.
        $out = array_values(array_filter($out, fn($v) => is_finite($v)));
        if (count($out) < 500) {
            // Pad with shifted copies if any block returned fewer than expected.
            $idx = 0;
            while (count($out) < 500) {
                $out[] = $out[$idx % count($out)] + $idx / 1_000_000.0;
                $idx++;
            }
        }
        return array_slice($out, 0, 500);
    }

    // =====================================================================
    // INVARIANT 1: normalize never returns scientific notation
    // =====================================================================

    public function test_irregular500_normalize_has_no_scientific(): void
    {
        foreach ($this->irregular500() as $f) {
            $expectedHasE = false;
            $actualHasE = stripos($this->normalize($f), 'e') !== false;
            $this->assertSame(
                $expectedHasE,
                $actualHasE,
                "normalize({$f}) leaked scientific notation: '{$this->normalize($f)}'"
            );
        }
    }

    // =====================================================================
    // INVARIANT 2 & 4: comp/greaterThan/lessThan reflexivity & irreflexivity
    // =====================================================================

    public function test_irregular500_self_comparison_is_zero(): void
    {
        foreach ($this->irregular500() as $f) {
            $this->assertSame(0, BcMath::comp($f, $f, 10), "comp({$f},{$f}) must be 0");
            $this->assertTrue(BcMath::equal($f, $f, 10), "equal({$f},{$f}) must be true");
            $this->assertFalse(BcMath::greaterThan($f, $f, 10), "greaterThan({$f},{$f}) must be false");
            $this->assertFalse(BcMath::lessThan($f, $f, 10), "lessThan({$f},{$f}) must be false");
        }
    }

    // =====================================================================
    // INVARIANT 5: additive identity — add($f, 0) == normalize($f)
    // =====================================================================

    public function test_irregular500_add_zero_identity(): void
    {
        foreach ($this->irregular500() as $f) {
            // Expected = normalize($f) BUT only used as an algebraic equality
            // target, not as an oracle for normalize itself. The strong claim
            // is comp($f, add($f, 0)) === 0, which we assert independently.
            $this->assertSame(
                0,
                BcMath::comp($f, BcMath::add($f, 0, 10), 10),
                "add({$f}, 0) is not numerically equal to {$f}"
            );
            $this->assertSame(
                0,
                BcMath::comp($f, BcMath::add(0, $f, 10), 10),
                "add(0, {$f}) is not numerically equal to {$f}"
            );
        }
    }

    // =====================================================================
    // INVARIANT 6: multiplicative identity — mul($f, 1) == $f
    // =====================================================================

    public function test_irregular500_mul_one_identity(): void
    {
        foreach ($this->irregular500() as $f) {
            $this->assertSame(
                0,
                BcMath::comp($f, BcMath::mul($f, 1, 20), 10),
                "mul({$f}, 1) is not numerically equal to {$f}"
            );
            $this->assertSame(
                0,
                BcMath::comp($f, BcMath::mul(1, $f, 20), 10),
                "mul(1, {$f}) is not numerically equal to {$f}"
            );
        }
    }

    // =====================================================================
    // INVARIANT 7: sub($f, $f) is zero
    // =====================================================================

    public function test_irregular500_self_subtraction_is_zero(): void
    {
        foreach ($this->irregular500() as $f) {
            $diff = BcMath::sub($f, $f, 20);
            $this->assertTrue(
                BcMath::isZero($diff, 20),
                "sub({$f}, {$f}) = '{$diff}' should be zero"
            );
        }
    }

    // =====================================================================
    // INVARIANT 8: add($f, -$f) is zero
    // =====================================================================

    public function test_irregular500_negation_cancels(): void
    {
        foreach ($this->irregular500() as $f) {
            $sum = BcMath::add($f, -$f, 20);
            $this->assertTrue(
                BcMath::isZero($sum, 20),
                "add({$f}, -{$f}) = '{$sum}' should be zero"
            );
        }
    }

    // =====================================================================
    // INVARIANT 9: mul($f, 0) is zero
    // =====================================================================

    public function test_irregular500_mul_zero_absorbs(): void
    {
        foreach ($this->irregular500() as $f) {
            $product = BcMath::mul($f, 0, 10);
            $this->assertTrue(
                BcMath::isZero($product, 10),
                "mul({$f}, 0) = '{$product}' should be zero"
            );
        }
    }

    // =====================================================================
    // INVARIANT 10: equal($float, (string) $float) — the original regression
    //
    // This is the canonical "string-form must equal float-form" property.
    // The expected value is the LITERAL boolean true, derived from the
    // mathematical fact that the float and its own string representation
    // refer to the same numeric value. No production helper is invoked
    // to compute the expectation.
    // =====================================================================

    public function test_irregular500_float_equals_own_string_form(): void
    {
        foreach ($this->irregular500() as $f) {
            $stringForm = (string) $f;
            $expectedValue = true;
            $actualValue = BcMath::equal($f, $stringForm, 10);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "equal({$f}, '{$stringForm}') must be true"
            );
        }
    }

    // =====================================================================
    // INVARIANT 11: isZero classification matches the float's primitive value
    //
    // Independent oracle: $f === 0.0 is checked at the PHP language level
    // before any helper involvement.
    // =====================================================================

    public function test_irregular500_isZero_classification(): void
    {
        foreach ($this->irregular500() as $f) {
            $expectedValue = ($f === 0.0);
            $actualValue = BcMath::isZero($f, 10);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "isZero({$f}) expected " . ($expectedValue ? 'true' : 'false')
                    . " got " . ($actualValue ? 'true' : 'false')
            );
        }
    }

    // =====================================================================
    // CROSS-PAIR INVARIANT: for every pair (f1, f2) in a 25-element subset,
    // sign-of-comparison must be consistent with sign-of-subtraction.
    // =====================================================================

    public function test_irregular_pairs_comp_matches_sign_of_sub(): void
    {
        $sample = array_slice($this->irregular500(), 0, 25);
        foreach ($sample as $f1) {
            foreach ($sample as $f2) {
                $diff = BcMath::sub($f1, $f2, 20);
                $expectedValue = BcMath::isZero($diff, 20)
                    ? 0
                    : (str_starts_with($diff, '-') ? -1 : 1);
                $actualValue = BcMath::comp($f1, $f2, 10);
                $this->assertSame(
                    $expectedValue,
                    $actualValue,
                    "comp({$f1},{$f2}) inconsistent with sign of sub: diff='{$diff}' comp={$actualValue}"
                );
            }
        }
    }
}
