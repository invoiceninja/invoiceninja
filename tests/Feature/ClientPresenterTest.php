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

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Models\Presenters\ClientPresenter
 */
class ClientPresenterTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCompanyName()
    {
        $settings = $this->client->company->settings;

        $settings->name = 'test';
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->getSetting('name');

        $merged_settings = $this->client->getMergedSettings();

        $name = $this->client->present()->company_name();

        $this->assertEquals('test', $merged_settings->name);
        $this->assertEquals('test', $name);
    }

    public function testCompanyAddress()
    {
        $this->assertNotNull($this->client->present()->company_address());
    }
}
