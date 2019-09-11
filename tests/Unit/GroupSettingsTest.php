<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\GroupSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Models\Client
 */
class GroupSettingsTest extends TestCase
{
	use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
    
	    parent::setUp();
		
	    $this->makeTestData();

	    $this->company_settings = CompanySettings::defaults();
	    $this->client->settings = new ClientSettings(ClientSettings::defaults());


	}


	public function testCompanyDefaults()
	{
		
		$this->company_settings->timezone_id = 'fluffy';
    	$this->client->company->settings = $this->company_settings;

		$this->assertEquals($this->client->company->settings->timezone_id, 'fluffy');
		$this->assertEquals($this->client->getSetting('timezone_id'), 'fluffy');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'fluffy');
			
	}


	public function testGroupDefaults()
	{

		$cs = $this->client->company->settings;
		$cs->timezone_id = NULL;

		$this->client->company->settings = $cs;

		$gs = $this->client->group_settings->settings;
		$gs->timezone_id = 'SPOCK';

		$this->client->group_settings->settings = $gs;

		$cls = $this->client->settings;
		$cls->timezone_id = NULL;
		$cls->date_format = 'sharleen';

    	$this->client->settings = $cls;

    	$this->client->group_settings->save();
    	$this->client->company->save();    	
    	$this->client->save();

    	$this->client->fresh();

//    	\Log::error(print_r($this->client,1));
    	\Log::error(print_r($this->client->settings,1));
    	\Log::error(print_r($this->client->settings->timezone_id,1));
    	\Log::error(print_r($this->client->settings->date_format,1));
    	\Log::error(print_r($this->client->group_settings->settings->timezone_id,1));
    	\Log::error(print_r($this->client->group_settings->settings,1));
    	\Log::error(print_r($this->client->company->settings->timezone_id,1));

    	$this->assertEquals($this->client->group_settings->settings->timezone_id, 'SPOCK');
		$this->assertEquals($this->client->getSetting('timezone_id'), 'SPOCK');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'SPOCK');
			
	}



	public function testClientDefaults()
	{
		
		$this->company_settings->timezone_id = NULL;
		$this->client->group_settings->settings->timezone_id = NULL;
		$this->client->settings->timezone_id = 'SCOTTY';
    	$this->client->company->settings = $this->company_settings;

    	$this->client->save();
    	$this->client->company->save();
    	$this->client->group_settings->save();

    	$this->assertEquals($this->client->settings->timezone_id, 'SCOTTY');
		$this->assertEquals($this->client->getSetting('timezone_id'), 'SCOTTY');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'SCOTTY');
			
	}

}