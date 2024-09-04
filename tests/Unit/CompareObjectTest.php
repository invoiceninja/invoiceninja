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
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\ClientSettings
 */
class CompareObjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->client_settings = ClientSettings::defaults();

        $this->company_settings = CompanySettings::defaults();
    }

    public function buildClientSettings()
    {
        foreach ($this->company_settings as $key => $value) {
            if (! isset($this->client_settings->{$key}) && property_exists($this->company_settings, $key)) {
                $this->client_settings->{$key} = $this->company_settings->{$key};
            }
        }

        return $this->client_settings;
    }

    public function testProperties()
    {
        $build_client_settings = $this->buildClientSettings();

        $this->assertEquals($build_client_settings->timezone_id, 1);
        $this->assertEquals($build_client_settings->language_id, 1);
        $this->assertEquals($build_client_settings->payment_terms, '');
    }

    public function testDirectClientSettingsBuild()
    {
        $settings = ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults());

        $this->assertEquals($settings->timezone_id, 1);
        $this->assertEquals($settings->language_id, 1);
        $this->assertEquals($settings->payment_terms, '');
        $this->assertFalse($settings->auto_archive_invoice);
    }
}
