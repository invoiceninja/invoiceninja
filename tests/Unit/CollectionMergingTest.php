<?php

namespace Tests\Unit;

use App\Models\PaymentTerm;
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

        $this->setCurrentCompanyId(1);

        $this->terms = PaymentTerm::all();
    }

    public function testBlankCollectionReturned()
    {
        $this->assertEquals($this->terms->count(), 0);
    }

    public function testMergingCollection()
    {
        $payment_terms = collect(config('ninja.payment_terms'));

        $new_terms = $this->terms->map(function ($term) {
            return $term['num_days'];
        });

        $payment_terms->merge($new_terms);

        $this->assertEquals($payment_terms->count(), 8);
    }

    public function testSortingCollection()
    {
        $payment_terms = collect(config('ninja.payment_terms'));

        $new_terms = $this->terms->map(function ($term) {
            return $term['num_days'];
        });

        $payment_terms->merge($new_terms)
        ->sortBy('num_days')
        ->values()
        ->all();

        $term = $payment_terms->first();

        $this->assertEquals($term['num_days'], 0);
    }

    public function testSortingCollectionLast()
    {
        $payment_terms = collect(config('ninja.payment_terms'));

        $new_terms = $this->terms->map(function ($term) {
            return $term['num_days'];
        });

        $payment_terms->merge($new_terms)
        ->sortBy('num_days')
        ->values()
        ->all();

        $term = $payment_terms->last();

        $this->assertEquals($term['num_days'], 90);
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
