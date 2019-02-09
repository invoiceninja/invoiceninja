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
        $payment_terms = collect(unserialize(CACHED_PAYMENT_TERMS));

    	$new_terms = $this->terms->map(function($term) {
            return $term['num_days'];
        });

        $payment_terms->merge($new_terms);

        $this->assertEquals($payment_terms->count(), 8);
    }

    public function testSortingCollection()
    {
        $payment_terms = collect(unserialize(CACHED_PAYMENT_TERMS));

        $new_terms = $this->terms->map(function($term) {
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
        $payment_terms = collect(unserialize(CACHED_PAYMENT_TERMS));

        $new_terms = $this->terms->map(function($term) {
            return $term['num_days'];
        });

        $payment_terms->merge($new_terms)
        ->sortBy('num_days')
        ->values()
        ->all();

        $term = $payment_terms->last();

        $this->assertEquals($term['num_days'], 90);
    }



}

