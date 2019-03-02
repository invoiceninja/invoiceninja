<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\BaseSettings
 */
class BaseSettingsTest extends TestCase
{

    public function setUp()
    {
    
    parent::setUp();
	
    $this->settings = ClientSettings::defaults();

	}

	public function testPropertyExists()
	{
		$blank_object = new \stdClass;

		$this->assertEquals(count(get_object_vars($this->migrate($blank_object))), 14);
	}

	public function testPropertyNamesExist()
	{
		$blank_object = new \stdClass;

		$updated_object = $this->migrate($blank_object);

		$this->assertTrue(property_exists($updated_object, 'language_id'));
	}

	public function testPropertyNamesNotExist()
	{
		$blank_object = new \stdClass;

		$updated_object = $this->migrate($blank_object);

		$this->assertFalse(property_exists($updated_object, 'non_existent_prop'));
	}	

	public function migrate(\stdClass $object) : \stdClass
	{

		foreach($this->settings as $property => $value)
		{
			if(!property_exists($object, $property))
				$object->{$property} = NULL;
		}

		return $object;
	}
}
