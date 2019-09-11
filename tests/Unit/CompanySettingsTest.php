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
		
		$this->assertEquals($this->company_settings->timezone_id, 15);

	}

	public function testLanguageId()
	{

		$this->assertEquals($this->company_settings->language_id, 1);

	}

	public function testPropertyIsNotset()
	{

		$this->assertFalse(isset($this->company_settings->custom_label1));

	}

	public function testPropertyIsSet()
	{

		$this->assertTrue(isset($this->company_settings->timezone_id));

	}
}
