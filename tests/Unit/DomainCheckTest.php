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

use App\DataProviders\Domains;
use Tests\TestCase;

/**
 * @test
 */
class DomainCheckTest extends TestCase
{

    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testDomainCheck()
    {

        $this->assertTrue(in_array('yopmail.com', Domains::getDomains()));
        $this->assertFalse(in_array('invoiceninja.com', Domains::getDomains()));

    }
}
