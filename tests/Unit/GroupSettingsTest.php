<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @coversDefaultClass App\Models\Client
 */
class GroupSettingsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use ClientGroupSettingsSaver;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->company_settings = CompanySettings::defaults();
        $this->client_settings = ClientSettings::buildClientSettings($this->company_settings, ClientSettings::defaults());
    }

    public function testCompanyDefaults()
    {
        $this->company_settings->timezone_id = 'fluffy';
        $this->company->settings = $this->company_settings;
        $this->company->save();

        $this->client_settings->timezone_id = '1';
        $this->client->settings = $this->client_settings;
        $this->client->save();

        $this->assertEquals($this->client->settings->timezone_id, '1');
        $this->assertEquals($this->client->getSetting('timezone_id'), '1');
        $this->assertEquals($this->client->getMergedSettings()->timezone_id, '1');

        $this->assertEquals($this->company->settings->timezone_id, 'fluffy');
    }

    public function testGroupDefaults()
    {
        $cs = $this->company->settings;
        $cs->timezone_id = '';

        $this->company->settings = $cs;

        $gs = $this->client->group_settings->settings;
        $gs->timezone_id = 'SPOCK';

        $this->client->group_settings->settings = $gs;
        $this->client->save();

        $cls = $this->client->settings;
        $cls->timezone_id = '';
        $cls->date_format = 'sharleen';
        $this->client->settings = $cls;
        $this->client->save();

        $this->client->company->save();
        $this->client->save();

        $this->assertEquals($this->client->group_settings->settings->timezone_id, 'SPOCK');
        $this->assertEquals($this->client->getSetting('timezone_id'), 'SPOCK');
        $this->assertEquals($this->client->getMergedSettings()->timezone_id, 'SPOCK');
    }

    public function testClientDefaults()
    {
        $cs = $this->client->company->settings;
        $cs->timezone_id = null;

        $this->client->company->settings = $cs;

        $gs = $this->client->group_settings->settings;
        $gs->timezone_id = null;

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

    /**
     * @covers ::getMergedSettings
     */
    public function testGroupPriority()
    {
        $cs = $this->client->company->settings;
        $cs->timezone_id = 'COMPANY';

        $this->client->company->settings = $cs;

        $gs = $this->client->group_settings->settings;
        $gs->timezone_id = 'GROUP';

        $this->client->group_settings->settings = $gs;

        $cls = $this->client->settings;
        $cls->timezone_id = null;

        $this->client->settings = $cls;

        $this->client->group_settings->save();
        $this->client->company->save();
        $this->client->save();

        $this->client->fresh();

        $this->assertEquals($this->client->getSetting('timezone_id'), 'GROUP');
        $this->assertEquals($this->client->getMergedSettings()->timezone_id, 'GROUP');
    }

    /**
     * @covers ::getSetting
     */
    public function testCompanyFallBackPriority()
    {
        $cs = $this->client->company->settings;
        $cs->timezone_id = 'COMPANY';

        $this->client->company->settings = $cs;

        $gs = $this->client->group_settings->settings;
        $gs->timezone_id = null;

        $this->client->group_settings->settings = $gs;

        $cls = $this->client->settings;
        $cls->timezone_id = null;

        $this->client->settings = $cls;

        $this->client->group_settings->save();
        $this->client->company->save();
        $this->client->save();

        $this->client->fresh();

        $this->assertEquals($this->client->getSetting('timezone_id'), 'COMPANY');
        $this->assertEquals($this->client->getMergedSettings()->timezone_id, 'COMPANY');
    }

    public function testDiscardingUnsetProperties()
    {
        $this->settings = $this->company->settings;

        $this->assertTrue($this->validateSettings($this->settings));

        $new_settings = $this->saveSettings($this->settings, $this->client);
    }
}
