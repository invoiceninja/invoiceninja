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

    public function setUp()
    {
    
    parent::setUp();
	
    $this->company_settings = CompanySettings::default();

	}

	public function testTimezoneId()
	{
		
		$this->assertEquals($this->company_settings->timezone_id, 15);

	}

	public function testCurrencyId()
	{

		$this->assertEquals($this->company_settings->currency_id, 1);

	}

	public function testLanguageId()
	{

		$this->assertEquals($this->company_settings->language_id, 1);

	}

	public function testCustomAttributes()
	{
		$this->assertObjectHasAttribute('label1', $this->company_settings->custom);
	}

	public function testCustomInvoiceAttributes()
	{

		$this->assertObjectHasAttribute('label1', $this->company_settings->invoice);

	}

	public function testCustomProductAttributes()
	{

		$this->assertObjectHasAttribute('label1', $this->company_settings->product);
		
	}

	public function testCustomTaskAttributes()
	{

		$this->assertObjectHasAttribute('label1', $this->company_settings->task);
		
	}

	public function testCustomExpenseAttributes()
	{

		$this->assertObjectHasAttribute('label1', $this->company_settings->expense);
		
	}

	public function testPropertyIsNotset()
	{

		$this->assertFalse(isset($this->company_settings->custom->label1));

	}

	public function testPropertyIsSet()
	{

		$this->assertTrue(isset($this->company_settings->currency_id));

	}
}
