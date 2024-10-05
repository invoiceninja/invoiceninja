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

use App\Utils\Traits\MakesHash;
use Tests\TestCase;

/**
 * 
 *   App\Utils\Traits\MakesHash
 */
class PrimaryKeyTransformationTest extends TestCase
{
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testTransformationArray()
    {
        $keys = [
            $this->encodePrimaryKey(310), $this->encodePrimaryKey(311),
        ];

        $transformed_keys = $this->transformKeys($keys);

        $this->assertEquals(310, $transformed_keys[0]);

        $this->assertEquals(311, $transformed_keys[1]);
    }

    public function testTransformation()
    {
        $keys = $this->encodePrimaryKey(310);

        $this->assertEquals(310, $this->transformKeys($keys));
    }
}
