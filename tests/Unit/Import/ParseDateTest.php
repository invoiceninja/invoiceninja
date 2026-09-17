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

namespace Tests\Unit\Import;

use App\DataMapper\CompanySettings;
use App\Import\Transformer\BaseTransformer;
use App\Models\Company;
use Tests\TestCase;

/**
 * @test
 *
 * Guards against regressions in BaseTransformer::parseDate() following the
 * introduction of a "configured company date format" parsing step.
 *
 * The date_format_id => format mapping (see DateFormatsSeeder):
 *   1 => d/M/Y    8  => Y-m-d
 *   9 => d-m-Y    10 => m/d/Y
 *   11 => d.m.Y   14 => d/m/Y
 */
class ParseDateTest extends TestCase
{
    /**
     * Build a BaseTransformer whose company reports the given configured
     * date format and country.
     */
    private function transformerFor(string $dateFormatId, string $countryId = '840'): BaseTransformer
    {
        $settings = CompanySettings::defaults();
        $settings->date_format_id = $dateFormatId;
        $settings->country_id = $countryId;

        $company = new Company();
        $company->settings = $settings;

        return new BaseTransformer($company);
    }

    /**
     * The original, format-agnostic inputs that already worked before the
     * configured-format step was added MUST continue to resolve identically,
     * regardless of which company date format is configured.
     */
    public function testIsoDatesAreUnaffectedByAnyConfiguredFormat(): void
    {
        foreach (['1', '8', '9', '10', '11', '14'] as $formatId) {
            $transformer = $this->transformerFor($formatId);

            $this->assertSame(
                '2024-01-15',
                $transformer->parseDate('2024-01-15'),
                "ISO date regressed under date_format_id {$formatId}"
            );
        }
    }

    /**
     * The pre-existing European branch (contains "/" and non-US country)
     * must still take precedence and parse as d/m/Y.
     */
    public function testNonUsSlashDatesStillParseAsEuropean(): void
    {
        // country != 840, configured format is US m/d/Y — the European
        // short-circuit must still win, exactly as before the change.
        $transformer = $this->transformerFor('10', '36'); // Australia

        $this->assertSame('2024-02-01', $transformer->parseDate('01/02/2024'));
    }

    /**
     * US numeric dates (country 840) resolve via the configured m/d/Y format.
     */
    public function testUsSlashDatesParseAsUsFormat(): void
    {
        $transformer = $this->transformerFor('10', '840'); // m/d/Y

        $this->assertSame('2024-01-15', $transformer->parseDate('01/15/2024'));
    }

    /**
     * REGRESSION GUARD: the configured-format step must not silently corrupt
     * a two-digit year. createFromFormat('m/d/Y', '1/5/24') yields year 0024,
     * so the step must reject it and fall through to general parsing, which
     * resolves the year correctly.
     */
    public function testTwoDigitYearIsNotCorruptedByConfiguredFormat(): void
    {
        $transformer = $this->transformerFor('10', '840'); // m/d/Y

        $result = $transformer->parseDate('1/5/24');

        $this->assertNotSame('0024-01-05', $result, 'Two-digit year was silently corrupted to year 0024');
        $this->assertSame('2024-01-05', $result);
    }

    /**
     * Dates that match a textual configured format parse correctly.
     */
    public function testTextualMonthFormatsParse(): void
    {
        $this->assertSame('2024-01-15', $this->transformerFor('1')->parseDate('15/Jan/2024')); // d/M/Y
        $this->assertSame('2024-01-15', $this->transformerFor('9')->parseDate('15-01-2024'));   // d-m-Y
        $this->assertSame('2024-01-15', $this->transformerFor('11')->parseDate('15.01.2024'));  // d.m.Y
    }

    /**
     * Inputs that do NOT match the configured format must fall through to the
     * existing general parser rather than throwing or being mis-parsed.
     */
    public function testInputNotMatchingConfiguredFormatFallsThrough(): void
    {
        // Configured d.m.Y, but the input is an ISO string with trailing time.
        $transformer = $this->transformerFor('11', '840');

        $this->assertSame('2024-01-15', $transformer->parseDate('2024-01-15 10:30:00'));
    }

    /**
     * A configured format that legitimately disagrees with the general parser
     * for the SAME ambiguous input is expected behaviour, not a regression:
     * d/m/Y must produce day-first.
     */
    public function testConfiguredFormatDisambiguatesAmbiguousDatesForUsCountry(): void
    {
        // US country (no European short-circuit), but format configured d/m/Y.
        $transformer = $this->transformerFor('14', '840'); // d/m/Y

        $this->assertSame('2024-02-01', $transformer->parseDate('01/02/2024'));
    }
}
