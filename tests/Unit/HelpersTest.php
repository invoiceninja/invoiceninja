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
        $entity = new class {
            public function locale(): string
            {
                return 'en';
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

        $date = Carbon::create(2024, 1, 15, 0, 0, 0, 'UTC');

        $value = Helpers::processReservedKeywords(
            ':MONTH+1 :YEAR-1 :QUARTER*2 :MONTHYEAR+2',
            $entity,
            $date,
        );

        $this->assertSame('February 2023 2 March 2024', $value);
    }
}
