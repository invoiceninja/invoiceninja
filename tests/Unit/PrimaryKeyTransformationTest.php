<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\MakesHash
 */
class PrimaryKeyTransformationTest extends TestCase
{
    use MakesHash;

    public function setUp() :void
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
