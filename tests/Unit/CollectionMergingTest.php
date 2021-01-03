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

use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 */
class CollectionMergingTest extends TestCase
{
    use UserSessionAttributes;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();
    }

    public function testUniqueValues()
    {
        $methods[] = [1 => 1];
        $methods[] = [1 => 2];
        $methods[] = [1 => 3];
        $methods[] = [1 => 4];
        $methods[] = [1 => 5];
        $methods[] = [1 => 6];

        $other_methods[] = [2 => 1];
        $other_methods[] = [2 => 7];
        $other_methods[] = [2 => 8];
        $other_methods[] = [2 => 9];
        $other_methods[] = [2 => 10];

        $array = array_merge($methods, $other_methods);

        $this->assertEquals(11, count($array));

        $collection = collect($array);

        $intersect = $collection->intersectByKeys($collection->flatten(1)->unique());

        $this->assertEquals(10, $intersect->count());

        $third_methods[] = [3 => 1];
        $third_methods[] = [2 => 11];

        $array = array_merge($array, $third_methods);

        $collection = collect($array);
        $intersect = $collection->intersectByKeys($collection->flatten(1)->unique());
        $this->assertEquals(11, $intersect->count());
    }
}
