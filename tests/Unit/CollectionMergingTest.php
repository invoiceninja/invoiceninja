<?php

namespace Tests\Unit;

use App\Models\PaymentTerm;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\NumberHelper
 */
class CollectionMergingTest extends TestCase
{

    use UserSessionAttributes;

    public function setUp()
    {
	    parent::setUp();
	    Session::start();

	    $this->setCurrentCompanyId(1);

		$this->terms = PaymentTerm::scope()->get();
    }

    public function testBlankCollectionReturned()
    {
    	$this->assertEquals($this->terms->count(), 0);
    }

    public function testMergingCollection()
    {
    	$new_terms = $this->terms->each(function($term) {
            return $term['num_days'];
        })->merge([0,7,10,14,15,30,60,90]);

        $this->assertEquals($new_terms->count(), 8);
    }

    public function testSortingCollection()
    {
    	$new_terms = $this->terms->each(function($term) {
            return $term['num_days'];
        })->merge([0,7,10,14,15,30,60,90])
        ->sort()
        ->values()
        ->all();

        $this->assertEquals($new_terms->first(), 0);
    }


}

