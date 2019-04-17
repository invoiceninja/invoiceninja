<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\ClientSettings
 */
class CompanyObjectTest extends TestCase
{

    public function setUp()
    {
    
    parent::setUp();
	
    $this->client_settings = new ClientSettings(ClientSettings::defaults());
    $this->company_settings = new CompanySettings(CompanySettings::defaults());

	}


	public function buildClientSettings()
	{

		foreach($this->client_settings as $key => $value)
		{

			if(!isset($this->client_settings->{$key}))
				$this->client_settings->{$key} = $this->company_settings->{$key};
		}


		return $this->client_settings;
	}


	public function testProperties()
	{
		$build_client_settings = $this->buildClientSettings();


		$this->assertEquals($build_client_settings->timezone_id, 15);
		$this->assertEquals($build_client_settings->currency_id, 1);
		$this->assertEquals($build_client_settings->language_id, 1);
		$this->assertEquals($build_client_settings->payment_terms, 7);
	}	


}
