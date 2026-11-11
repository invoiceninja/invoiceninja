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

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProvinceStateAbbreviationTest extends TestCase
{
    public static function caProvinceProvider(): array
    {
        return [
            'english name' => ['New Brunswick', 'NB'],
            'english name lowercase' => ['new brunswick', 'NB'],
            'french name' => ['Nouveau-Brunswick', 'NB'],
            'french name with accents' => ['Québec', 'QC'],
            'french name accented territory' => ['Nouvelle-Écosse', 'NS'],
            'existing code' => ['NB', 'NB'],
            'existing code lowercase' => ['nb', 'NB'],
            'padded code' => [' NB ', 'NB'],
            'unknown' => ['Atlantis', ''],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('caProvinceProvider')]
    public function testCanadianProvinceResolution(string $input, string $expected): void
    {
        $this->assertSame($expected, CAProvinces::getAbbreviation($input));
    }

    public function testNullCanadianProvinceResolvesToEmpty(): void
    {
        $this->assertSame('', CAProvinces::getAbbreviation(null));
    }

    public static function usStateProvider(): array
    {
        return [
            'full name' => ['California', 'CA'],
            'full name lowercase' => ['california', 'CA'],
            'existing code' => ['CA', 'CA'],
            'existing code lowercase' => ['ca', 'CA'],
            'padded code' => [' NY ', 'NY'],
            'unknown' => ['Westeros', ''],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('usStateProvider')]
    public function testUsStateResolution(string $input, string $expected): void
    {
        $this->assertSame($expected, USStates::getAbbreviation($input));
    }

    public function testNullUsStateResolvesToEmpty(): void
    {
        $this->assertSame('', USStates::getAbbreviation(null));
    }
}
