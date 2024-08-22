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
use Tests\TestCase;

/**
 * @test
 * @covers  App\DataMapper\BaseSettings
 */
class BaseSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = ClientSettings::defaults();
    }

    public function testPropertyNamesExist()
    {
        $blank_object = new \stdClass();

        $updated_object = $this->migrate($blank_object);

        $this->assertTrue(property_exists($updated_object, 'size_id'));
    }

    public function testPropertyNamesNotExist()
    {
        $blank_object = new \stdClass();

        $updated_object = $this->migrate($blank_object);

        $this->assertFalse(property_exists($updated_object, 'non_existent_prop'));
    }

    public function migrate(\stdClass $object): \stdClass
    {
        foreach ($this->settings as $property => $value) {
            if (! property_exists($object, $property)) {
                $object->{$property} = null;
            }
        }

        return $object;
    }
}
