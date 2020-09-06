<?php

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\CompanySettings
 */
class CompanySettingsTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();

        $this->company_settings = CompanySettings::defaults();
    }

    public function testTimezoneId()
    {
        $this->assertEquals($this->company_settings->timezone_id, 1);
    }

    public function testLanguageId()
    {
        $this->assertEquals($this->company_settings->language_id, 1);
    }

    public function testPropertyIssetOk()
    {
        $this->assertTrue(isset($this->company_settings->custom_value1));
    }

    public function testPropertyIsSet()
    {
        $this->assertTrue(isset($this->company_settings->timezone_id));
    }

    public function testSettingsArrayAgainstCastsArray()
    {
        $company_settings = json_decode(json_encode(CompanySettings::defaults()), true);
        $casts = CompanySettings::$casts;

        $diff = array_diff_key($company_settings, $casts);

        $this->assertEquals(1, count($diff));
    }
}
