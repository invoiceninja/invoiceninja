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
		
		$this->assertEquals($user_settings->Client->datatable->per_page, 25);
	}

	public function testIsObject()
	{
		$user_settings = DefaultSettings::userSettings();

        $this->assertInternalType('object',$user_settings->Client->datatable->column_visibility);

	}
}