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

    	$this->assertEquals($this->client->group_settings->settings->timezone_id, 'SPOCK');
		$this->assertEquals($this->client->getSetting('timezone_id'), 'SPOCK');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'SPOCK');
			
	}



	public function testClientDefaults()
	{
		

		$cs = $this->client->company->settings;
		$cs->timezone_id = NULL;

		$this->client->company->settings = $cs;

		$gs = $this->client->group_settings->settings;
		$gs->timezone_id = NULL;

		$this->client->group_settings->settings = $gs;

		$cls = $this->client->settings;
		$cls->timezone_id = 'SCOTTY';
		$cls->date_format = 'sharleen';

    	$this->client->settings = $cls;

    	$this->client->group_settings->save();
    	$this->client->company->save();    	
    	$this->client->save();

    	$this->client->fresh();

    	$this->assertEquals($this->client->settings->timezone_id, 'SCOTTY');
		$this->assertEquals($this->client->getSetting('timezone_id'), 'SCOTTY');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'SCOTTY');
			
	}


	public function testClientPriority()
	{
		$cs = $this->client->company->settings;
		$cs->timezone_id = 'COMPANY';

		$this->client->company->settings = $cs;

		$gs = $this->client->group_settings->settings;
		$gs->timezone_id = 'GROUP';

		$this->client->group_settings->settings = $gs;

		$cls = $this->client->settings;
		$cls->timezone_id = 'CLIENT';

    	$this->client->settings = $cls;

    	$this->client->group_settings->save();
    	$this->client->company->save();    	
    	$this->client->save();

    	$this->client->fresh();

		$this->assertEquals($this->client->getSetting('timezone_id'), 'CLIENT');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'CLIENT');
	}


	public function testGroupPriority()
	{
		$cs = $this->client->company->settings;
		$cs->timezone_id = 'COMPANY';

		$this->client->company->settings = $cs;

		$gs = $this->client->group_settings->settings;
		$gs->timezone_id = 'GROUP';

		$this->client->group_settings->settings = $gs;

		$cls = $this->client->settings;
		$cls->timezone_id = NULL;

    	$this->client->settings = $cls;

    	$this->client->group_settings->save();
    	$this->client->company->save();    	
    	$this->client->save();

    	$this->client->fresh();

		$this->assertEquals($this->client->getSetting('timezone_id'), 'GROUP');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'GROUP');
	}	

	public function testCompanyFallBackPriority()
	{
		$cs = $this->client->company->settings;
		$cs->timezone_id = 'COMPANY';

		$this->client->company->settings = $cs;

		$gs = $this->client->group_settings->settings;
		$gs->timezone_id = NULL;

		$this->client->group_settings->settings = $gs;

		$cls = $this->client->settings;
		$cls->timezone_id = NULL;

    	$this->client->settings = $cls;

    	$this->client->group_settings->save();
    	$this->client->company->save();    	
    	$this->client->save();

    	$this->client->fresh();

		$this->assertEquals($this->client->getSetting('timezone_id'), 'COMPANY');
		$this->assertEquals($this->client->getMergedSettings()->timezone_id, 'COMPANY');
	}	
}