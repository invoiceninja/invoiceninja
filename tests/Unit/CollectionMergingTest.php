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

use App\Factory\ClientContactFactory;
use App\Factory\InvoiceItemFactory;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 */
class CollectionMergingTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();
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

    public function testExistenceInCollection()
    {
        $items = InvoiceItemFactory::generate(5);

        $this->assertFalse(collect($items)->contains('type_id', '3'));
        $this->assertFalse(collect($items)->contains('type_id', 3));

        $item = InvoiceItemFactory::create();
        $item->type_id = '3';
        $items[] = $item;

        $this->assertTrue(collect($items)->contains('type_id', '3'));
        $this->assertTrue(collect($items)->contains('type_id', 3));
    }

    public function testClientContactSendEmailExists()
    {
        $new_collection = collect();

        $cc = ClientContactFactory::create(1, 1);
        $cc->send_email = true;

        $new_collection->push($cc);

        $cc_false = ClientContactFactory::create(1, 1);
        $cc_false->send_email = false;

        $new_collection->push($cc_false);

        $this->assertTrue($new_collection->contains('send_email', true));
    }

    public function testClientContactSendEmailDoesNotExists()
    {
        $new_collection = collect();

        $cc = ClientContactFactory::create(1, 1);
        $cc->send_email = false;

        $new_collection->push($cc);

        $cc_false = ClientContactFactory::create(1, 1);
        $cc_false->send_email = false;

        $new_collection->push($cc_false);

        $this->assertFalse($new_collection->contains('send_email', true));
    }
}
