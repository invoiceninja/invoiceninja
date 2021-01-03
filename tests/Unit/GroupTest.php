<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Tests\TestCase;

/**
 * @test
 */
class GroupTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();

        $this->settings = ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults());
    }

    public function testGroupsPropertiesExistsResponses()
    {
        $this->assertTrue(property_exists($this->settings, 'timezone_id'));
    }

    public function testPropertyValueAccessors()
    {
        $this->settings->translations = (object) ['hello' => 'world'];

        $this->assertEquals('world', $this->settings->translations->hello);
    }

    public function testPropertyIsSet()
    {
        $this->assertFalse(isset($this->settings->translations->nope));
    }
}
