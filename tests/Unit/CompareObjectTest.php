<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\ClientSettings
 */
class CompareObjectTest extends TestCase
{

    public function setUp() :void
    {
    
    parent::setUp();
	
    $this->client_settings = new ClientSettings(ClientSettings::defaults());

    $this->company_settings = new CompanySettings(CompanySettings::defaults());

	}


	public function buildClientSettings()
	{

		foreach($this->client_settings as $key => $value)
		{

			if(!isset($this->client_settings->{$key}) && property_exists($this->company_settings, $key))
				$this->client_settings->{$key} = $this->company_settings->{$key};
		}

		return $this->client_settings;
	}


	public function testProperties()
	{

		$build_client_settings = $this->buildClientSettings();

		$this->assertEquals($build_client_settings->timezone_id, 15);
		$this->assertEquals($build_client_settings->language_id, 1);
		$this->assertEquals($build_client_settings->payment_terms, 1);
	}	

	public function testDirectClientSettingsBuild()
	{
		$settings = ClientSettings::buildClientSettings(new CompanySettings(CompanySettings::defaults()), new ClientSettings(ClientSettings::defaults()));

		$this->assertEquals($settings->timezone_id, 15);
		$this->assertEquals($settings->language_id, 1);
		$this->assertEquals($settings->payment_terms, 1);
		$this->assertEquals($settings->custom_invoice_taxes1, 'FALSE');
	}


}
