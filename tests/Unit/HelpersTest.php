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
use PHPUnit\Framework\TestCase;

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
}
