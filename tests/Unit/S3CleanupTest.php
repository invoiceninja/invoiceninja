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

use Tests\TestCase;

/**
 * 
 */
class S3CleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testMergeCollections()
    {
        $c1 = collect(['1', '2', '3', '4']);
        $c2 = collect(['5', '6', '7', '8']);

        $c3 = collect(['1', '2', '10']);

        $merged = $c1->merge($c2)->toArray();

        $this->assertTrue(in_array('1', $merged));
        $this->assertFalse(in_array('10', $merged));
    }
}
