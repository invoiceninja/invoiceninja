<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\Helpers;
use Carbon\Carbon;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Carbon::setLocale('en');
        app()->setLocale('en');

        parent::tearDown();
    }

    public function testFontsReturnFormat(): void
    {
        $font = Helpers::resolveFont();

        $this->assertArrayHasKey('name', $font);
        $this->assertArrayHasKey('url', $font);
    }

    public function testResolvingFont(): void
    {
        $font = Helpers::resolveFont('Inter');

        $this->assertEquals('Inter', $font['name']);
    }

    public function testDefaultFontIsArial(): void
    {
        $font = Helpers::resolveFont();

        $this->assertEquals('Arial', $font['name']);
    }

    public function testReservedKeywordMathUsesMatchedOperation(): void
    {
        $date = Carbon::create(2024, 1, 15, 0, 0, 0, 'UTC');

        $value = Helpers::processReservedKeywords(
            ':MONTH+1 :YEAR-1 :QUARTER*2 :MONTHYEAR+2',
            $this->entity(),
            $date,
        );

        $this->assertSame('February 2023 Q2 March 2024', $value);
    }

    public function testReservedKeywordQuarterAdditionMatchesTheDocumentedFormat(): void
    {
        $value = Helpers::processReservedKeywords(
            'Retainer payment for :QUARTER+1',
            $this->entity(),
            Carbon::create(2024, 1, 15, 0, 0, 0, 'UTC'),
        );

        $this->assertSame('Retainer payment for Q2', $value);
    }

    public function testRangeContainingASlashNeverErasesTheSurroundingText(): void
    {
        $description = 'Period: [MONTHYEAR|MONTHYEAR/2]. Keep this description.';

        $value = Helpers::processReservedKeywords(
            $description,
            $this->entity(),
            Carbon::create(2026, 8, 15, 0, 0, 0, 'UTC'),
        );

        $this->assertSame($description, $value);
    }

    public function testMonthYearRangeSupportsSubtractingMonths(): void
    {
        $value = Helpers::processReservedKeywords(
            '[MONTHYEAR|MONTHYEAR-2]',
            $this->entity(),
            Carbon::create(2026, 8, 15, 0, 0, 0, 'UTC'),
        );

        $this->assertSame('August 2026 - June 2026', $value);
    }

    public function testMonthYearRangeDoesNotOverflowAtTheEndOfAMonth(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 31, 0, 0, 0, 'UTC'));

        $value = Helpers::processReservedKeywords(
            '[MONTHYEAR|MONTHYEAR+1]',
            $this->entity(),
            Carbon::create(2026, 1, 15, 0, 0, 0, 'UTC'),
        );

        $this->assertSame('January 2026 - February 2026', $value);
    }

    public function testLiteralReservedKeywordRangeUsesANeutralRangeSeparator(): void
    {
        $date = Carbon::create(2026, 7, 15, 0, 0, 0, 'UTC');

        $literalRange = Helpers::processReservedKeywords(':MONTH_AFTER', $this->entity('de'), $date);

        $this->assertSame('2026-07-15 - 2026-08-14', $literalRange);
    }

    public function testCalculatedReservedKeywordRangeUsesANeutralRangeSeparator(): void
    {
        $date = Carbon::create(2026, 7, 15, 0, 0, 0, 'UTC');

        $calculatedRange = Helpers::processReservedKeywords('[MONTHYEAR|MONTHYEAR+1]', $this->entity('de'), $date);

        $this->assertSame('Juli 2026 - August 2026', $calculatedRange);
    }

    private function entity(string $locale = 'en'): object
    {
        return new class ($locale) {
            public function __construct(private readonly string $locale) {}

            public function locale(): string
            {
                return $this->locale;
            }

            public function timezone(): object
            {
                return (object) ['name' => 'UTC'];
            }

            public function date_format(): string
            {
                return 'Y-m-d';
            }
        };
    }
}
