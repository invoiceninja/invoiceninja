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

use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 * 
 *   App\Http\Requests\Company\UpdateCompanyRequest
 */
class CompanySettingsSaveableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSettingsSaverWithFreePlan()
    {
        $filtered = $this->filterSaver(CompanySettings::defaults());

        $this->assertTrue(property_exists($filtered, 'timezone_id'));

        $this->assertTrue(property_exists(CompanySettings::defaults(), 'timezone_id'));

        $this->assertTrue(property_exists(CompanySettings::defaults(), 'auto_archive_invoice'));

        $this->assertFalse(property_exists($filtered, 'auto_archive_invoice'));
    }

    private function filterSaver($settings)
    {
        $saveable_cast = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_cast)) {
                unset($settings->{$key});
            }
        }

        return $settings;
    }
}
