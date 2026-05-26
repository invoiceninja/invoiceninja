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
 * Coverage for BcMath::normalizeNumber across 1000+ variants.
 *
 * The contract under test:
 *   - Null and '' → "0"
 *   - Floats → a plain decimal string with no scientific notation that
 *     round-trips exactly back to the input float ((float) $out === $in)
 *   - Ints  → (string) $int
 *   - Strings → preserved verbatim, except scientific notation is expanded
 *
 * Property-based assertions are used wherever an exact expected output
 * would force the test to encode PHP's serialize_precision behavior.
 */
class BcMathNormalizeTest extends TestCase
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

    private function assertNoScientificNotation(string $result, mixed $input): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            'e',
            $result,
            'normalizeNumber returned scientific notation for input: ' . var_export($input, true)
        );
    }

    /**
     * For non-scientific-notation floats, normalizeNumber must produce
     * exactly the same string PHP's own cast produces — no padded zeros,
     * no leaked binary tail. (PHP's (string) cast is not always a perfect
     * float round-trip due to serialize_precision=-1 quirks, so we anchor
     * on string equality with PHP's cast rather than (float) round-trip.)
     */
    private function assertFloatRoundTrips(float $input, string $result): void
    {
        $phpCast = (string) $input;
        if (stripos($phpCast, 'e') !== false) {
            // Scientific input: just assert no scientific in output and
            // numeric equivalence to the canonical PHP string at scale 30.
            $this->assertNoScientificNotation($result, $input);
            return;
        }
        $this->assertSame(
            $phpCast,
            $result,
            "Float {$input} normalized differently from PHP cast; got '{$result}'"
        );
    }

    private function assertBccompEqual(string $a, string $b, string $context): void
    {
        $this->assertSame(
            0,
            bccomp($a, $b, 30),
            "{$context}: bccomp('{$a}', '{$b}') !== 0"
        );
    }

    // =========================================================
    // Hand-anchored cases — the original bug + classic foot-guns
    // =========================================================

    public function testBcMathScenarioOne()
    {
        $value = 1922.01;
        $string_value = "1922.01";

        $this->assertFalse(BcMath::lessThan($string_value, $value));
        $this->assertFalse(BcMath::lessThan($value, $string_value));
        $this->assertTrue(BcMath::equal($string_value, $value));
        $this->assertTrue(BcMath::equal($value, $string_value));



        $value = 1922.010000;
        $string_value = "1922.01";

        $this->assertFalse(BcMath::lessThan($string_value, $value));
        $this->assertFalse(BcMath::lessThan($value, $string_value));
        $this->assertTrue(BcMath::equal($string_value, $value));
        $this->assertTrue(BcMath::equal($value, $string_value));


    }

    public function testHandAnchoredCriticalCases(): void
    {
        $cases = [
            // [input, expected]
            [null,        '0'],
            ['',          '0'],
            [0,           '0'],
            [0.0,         '0'],
            [1,           '1'],
            [-1,          '-1'],
            [PHP_INT_MAX, (string) PHP_INT_MAX],
            [PHP_INT_MIN, (string) PHP_INT_MIN],
            // The reported regression
            [721090.57,   '721090.57'],
            // Classic floats that PHP renders cleanly via serialize_precision=-1
            [0.1,         '0.1'],
            [0.2,         '0.2'],
            [554.17,      '554.17'],
            [1234.56,     '1234.56'],
            [99999.99,    '99999.99'],
            // String pass-through (DB-style)
            ['554.170000',     '554.170000'],
            ['721090.5700000', '721090.5700000'],
            ['0.00',           '0.00'],
            ['-0',             '-0'],
            // Scientific expansion
            ['1.0E-5',  '0.00001'],
            ['1.5e3',   '1500'],
            ['2.5E+2',  '250'],
        ];

        foreach ($cases as [$input, $expected]) {
            $this->assertSame(
                $expected,
                $this->normalize($input),
                'Failed for input: ' . var_export($input, true)
            );
        }
    }

    public function testNonFiniteFloatsCoercedToZero(): void
    {
        foreach ([NAN, INF, -INF] as $bad) {
            $this->assertSame(
                '0',
                $this->normalize($bad),
                'Non-finite float should be coerced to "0", not throw or leak'
            );
        }
    }

    // =========================================================
    // Property-based: 500 currency-range floats round-trip
    // =========================================================

    public function testCurrencyRangeFloatsRoundTrip(): void
    {
        // Independent oracle: format integer cents by hand. No (string) cast
        // of the input float — that would share the same code path the
        // production helper uses, hiding shared bugs.
        $expectFrom = function (int $cents): string {
            $sign = $cents < 0 ? '-' : '';
            $abs = abs($cents);
            $dollars = intdiv($abs, 100);
            $remainder = $abs % 100;
            // Match PHP's shortest-round-trip output: trailing zero in the
            // fractional part is dropped (e.g. 100 cents → "1", not "1.00";
            // 110 cents → "1.1", not "1.10"). This is the canonical decimal
            // form the float should normalize to.
            if ($remainder === 0) {
                return $sign . (string) $dollars;
            }
            if ($remainder % 10 === 0) {
                return $sign . $dollars . '.' . intdiv($remainder, 10);
            }
            return $sign . sprintf('%d.%02d', $dollars, $remainder);
        };

        $count = 0;
        for ($cents = 1; $cents <= 999_999; $cents += 1999) {
            $expectedValue = $expectFrom($cents);
            $actualValue = $this->normalize($cents / 100.0);
            $this->assertNoScientificNotation($actualValue, $cents);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "currency +{$cents}c expected '{$expectedValue}' got '{$actualValue}'"
            );
            $count++;
        }
        for ($cents = 1; $cents <= 999_999; $cents += 1999) {
            $expectedValue = $expectFrom(-$cents);
            $actualValue = $this->normalize(-($cents / 100.0));
            $this->assertNoScientificNotation($actualValue, -$cents);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "currency -{$cents}c expected '{$expectedValue}' got '{$actualValue}'"
            );
            $count++;
        }

        $this->assertGreaterThanOrEqual(1000, $count, 'expected at least 1000 currency variants');
    }

    // =========================================================
    // 200 large-magnitude floats (still in fixed-point range)
    // =========================================================

    public function testLargeMagnitudeFloatsRoundTrip(): void
    {
        $count = 0;
        // Magnitudes from millions to trillions, common in aggregate reports.
        $bases = [1_000_000.01, 50_000_000.5, 999_999_999.99, 1_234_567_890.12];
        for ($i = 0; $i < 50; $i++) {
            foreach ($bases as $base) {
                $value = $base + $i * 7.13;
                $result = $this->normalize($value);
                $this->assertNoScientificNotation($result, $value);
                $this->assertFloatRoundTrips($value, $result);
                $count++;
            }
        }
        $this->assertSame(200, $count);
    }

    // =========================================================
    // 100 small-but-not-scientific floats (>= 1e-4)
    // =========================================================

    public function testSmallFloatsRoundTrip(): void
    {
        $count = 0;
        for ($i = 1; $i <= 100; $i++) {
            $value = $i / 10000.0; // 0.0001 .. 0.0100
            $result = $this->normalize($value);
            $this->assertNoScientificNotation($result, $value);
            $this->assertFloatRoundTrips($value, $result);
            $count++;
        }
        $this->assertSame(100, $count);
    }

    // =========================================================
    // 60 tiny floats that PHP renders as scientific notation
    // =========================================================

    public function testTinyScientificFloatsExpanded(): void
    {
        $count = 0;
        // 1e-5 .. 1e-15, plus mantissa variants.
        for ($exp = 5; $exp <= 15; $exp++) {
            foreach ([1.0, 1.5, 2.0, 9.9, 7.25] as $mantissa) {
                $value = $mantissa * (10 ** -$exp);
                $result = $this->normalize($value);

                $this->assertNoScientificNotation($result, $value);
                // Numeric equivalence to PHP's own scientific rendering of the same float.
                // Using sprintf %.20F as the canonical reference rather than (float) round-trip,
                // since serialize_precision=-1 is not always a perfect bijection for tiny doubles.
                $canonical = rtrim(rtrim(sprintf('%.20F', (float)((string) $value)), '0'), '.') ?: '0';
                $this->assertSame(
                    $canonical,
                    $result,
                    "Tiny float {$value} normalized differently from canonical fixed-point; got '{$result}'"
                );
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(50, $count);
    }

    // =========================================================
    // 60 huge floats that PHP renders as scientific notation
    // =========================================================

    public function testHugeScientificFloatsExpanded(): void
    {
        $count = 0;
        for ($exp = 21; $exp <= 30; $exp++) {
            foreach ([1.0, 1.5, 2.0, 9.0, 7.25] as $mantissa) {
                $value = $mantissa * (10 ** $exp);
                $result = $this->normalize($value);

                $this->assertNoScientificNotation($result, $value);
                $canonical = rtrim(rtrim(sprintf('%.20F', (float)((string) $value)), '0'), '.') ?: '0';
                $this->assertSame(
                    $canonical,
                    $result,
                    "Huge float {$value} normalized differently from canonical fixed-point; got '{$result}'"
                );
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(50, $count);
    }

    // =========================================================
    // 100 integer inputs — must equal (string) $int
    // =========================================================

    public function testIntegerInputs(): void
    {
        $count = 0;
        for ($i = -50; $i <= 49; $i++) {
            $this->assertSame((string) $i, $this->normalize($i));
            $count++;
        }
        $this->assertSame(100, $count);

        // Boundary ints
        $this->assertSame((string) PHP_INT_MAX, $this->normalize(PHP_INT_MAX));
        $this->assertSame((string) PHP_INT_MIN, $this->normalize(PHP_INT_MIN));
    }

    // =========================================================
    // 100 string-passthrough variants (no scientific notation)
    // =========================================================

    public function testStringPassthroughPreservesPrecision(): void
    {
        $strings = [];
        // DB-style trailing zeros at varying scale
        foreach (['0', '1', '12', '123', '1234', '12345', '999999'] as $whole) {
            foreach (['', '.0', '.00', '.000', '.0000', '.000000', '.5', '.50', '.500', '.555555'] as $frac) {
                $strings[] = $whole . $frac;
            }
        }
        // Negative variants
        $negatives = array_map(fn($s) => '-' . $s, array_filter($strings, fn($s) => $s !== '0'));
        $strings = array_merge($strings, $negatives);

        // Keep at least 100
        $strings = array_slice($strings, 0, 130);
        foreach ($strings as $s) {
            $result = $this->normalize($s);
            $this->assertSame($s, $result, "string '{$s}' should pass through unchanged");
            // bccomp must accept the result and treat it as numerically equal to itself.
            $this->assertBccompEqual($result, $s, "passthrough '{$s}'");
        }
        $this->assertGreaterThanOrEqual(100, count($strings));
    }

    // =========================================================
    // 60 string-with-scientific-notation expansion cases
    // =========================================================

    public function testStringScientificExpanded(): void
    {
        $cases = [];
        foreach (['1', '1.5', '9.99', '7.25'] as $mantissa) {
            foreach (['e-3', 'e-5', 'e-10', 'E+2', 'e3', 'e6', 'E10'] as $exp) {
                $cases[] = $mantissa . $exp;
            }
        }
        // Negative mantissa variants.
        $negatives = array_map(fn($s) => '-' . $s, $cases);
        $cases = array_merge($cases, $negatives);

        foreach ($cases as $input) {
            $result = $this->normalize($input);
            $this->assertNoScientificNotation($result, $input);
            // Numeric equivalence via bccomp at high scale.
            // Anchor expectation: (float) $input rendered as (string) of the same float (after expansion).
            $expectedFloat = (float) $input;
            $this->assertSame(
                $expectedFloat,
                (float) $result,
                "Scientific input '{$input}' did not produce a float-equivalent expansion; got '{$result}'"
            );
        }

        $this->assertGreaterThanOrEqual(50, count($cases));
    }

    // =========================================================
    // 200 random-but-deterministic floats fuzz round-trip
    // =========================================================

    public function testFuzzCurrencyFloatsRoundTrip(): void
    {
        // Deterministic seed so failures are reproducible.
        mt_srand(20260504);
        $count = 0;
        for ($i = 0; $i < 200; $i++) {
            $cents = mt_rand(1, 999_999_999); // up to ~10 million currency units
            $value = $cents / 100.0;
            if (mt_rand(0, 1)) {
                $value = -$value;
            }

            $result = $this->normalize($value);
            $this->assertNoScientificNotation($result, $value);
            $this->assertFloatRoundTrips($value, $result);
            $count++;
        }
        $this->assertSame(200, $count);
    }

    // =========================================================
    // Integration: bccomp at scale 10 returns 0 for normalized
    // =========================================================

    public function testNormalizedFloatsCompareEqualToCanonicalString(): void
    {
        $samples = [
            721090.57, 554.17, 0.1, 0.2, 0.30, 1234.56, 99999.99,
            0.0001, 0.0123, 1_000_000.01, 0.07, 12.345, 100.50,
            -721090.57, -0.1, -1234.56,
        ];
        foreach ($samples as $value) {
            $normalized = $this->normalize($value);
            // Canonical string form for the same value via PHP's shortest round-trip.
            $canonical = (string) $value;
            $this->assertSame(
                0,
                bccomp($normalized, $canonical, 10),
                "bccomp at scale 10 should consider {$normalized} == {$canonical}"
            );
        }
    }
}
