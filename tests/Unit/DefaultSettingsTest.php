<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\DefaultSettings
 */
class DefaultTest extends TestCase
{

    public function setUp()
    {
    
    parent::setUp();
	
	}

	public function testDefaultUserSettings()
	{
		$user_settings = DefaultSettings::userSettings();
		
		$this->assertEquals($user_settings->client->datatable->per_page, 20);
	}

	public function testIsIterable()
	{
		$user_settings = DefaultSettings::userSettings();

        $this->assertInternalType('object',$user_settings->client->datatable->column_visibility);

	}
}