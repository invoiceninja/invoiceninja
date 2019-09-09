<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\Group
 */
class GroupTest extends TestCase
{

    public function setUp() :void
    {
    
    parent::setUp();
	
   // $this->settings = new ClientSettings(ClientSettings::defaults());
    	$this->settings = ClientSettings::buildClientSettings(new CompanySettings(CompanySettings::defaults()), new ClientSettings(ClientSettings::defaults()));

	}

	public function testGroupsPropertiesExistsResponses()
	{
		//$this->assertEquals(print_r($this->settings));
 
		$this->assertTrue(property_exists($this->settings->groups, 'company_gateways'));

		$this->assertTrue(property_exists($this->settings, 'groups'));
	}

	public function testPropertyValueAccessors()
	{

		$this->settings->groups->company_gateways = 'slug';
	
		$this->assertEquals('slug', $this->settings->groups->company_gateways);

	}

	public function testPropertyIsSet()
	{
		$this->assertFalse(isset($this->settings->groups->company_gateways));

		$this->settings->groups->company_gateways = 'slug';
	
		$this->assertTrue(isset($this->settings->groups->company_gateways));
	}

}