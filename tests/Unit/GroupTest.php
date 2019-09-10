<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\CompanyGatewaySettings
 */
class GroupTest extends TestCase
{

    public function setUp() :void
    {
    
    	parent::setUp();
	
    	$this->settings = ClientSettings::buildClientSettings(new CompanySettings(CompanySettings::defaults()), new ClientSettings(ClientSettings::defaults()));

	}

	public function testGroupsPropertiesExistsResponses()
	{
	
		$this->assertTrue(property_exists($this->settings, 'design'));
	}

	public function testPropertyValueAccessors()
	{

		$this->settings->translations = (object) ['hello' => 'world'];
	
		$this->assertEquals('world', $this->settings->translations->hello);

	}

	public function testPropertyIsSet()
	{
		$this->assertFalse(isset($this->settings->translations->nope));
	}

}