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

use App\Utils\SystemHealth;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\SystemHealth
 */
class SystemHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testVariables()
    {
        $results = SystemHealth::check();

        $this->assertTrue(is_array($results));

        $this->assertTrue(count($results) > 1);

        $this->assertTrue((bool) $results['system_health']);

        // $this->assertTrue($results['extensions'][0]['mysqli']);
        $this->assertTrue($results['extensions'][0]['gd']);
        $this->assertTrue($results['extensions'][1]['curl']);
        $this->assertTrue($results['extensions'][2]['zip']);
    }
}
