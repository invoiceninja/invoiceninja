<?php

namespace Tests\Unit\Migration;

use App\DataMapper\BaseSettings;
use App\DataMapper\FeesAndLimits;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class FeesAndLimitsTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testFeesAndLimitsFunctionWorks()
    {
    	$data = [];
	    $data['min_limit'] = 234;
	    $data['max_limit'] = 65317;
	    $data['fee_amount'] = 0.00;
	    $data['fee_percent'] = 0.000;
	    $data['tax_name1'] = '' ;
	    $data['tax_rate1'] = '';
	    $data['tax_name2'] = '';
	    $data['tax_rate2'] = '';
	    $data['tax_name3'] = '';
	    $data['tax_rate3'] = 0;

	    $transformed = $this->cleanFeesAndLimits($data);

	    $this->assertTrue(is_array($transformed));
    }


    public function cleanFeesAndLimits($fees_and_limits)
    {
        $new_arr = [];

        foreach ($fees_and_limits as $key => $value) {
            $fal = new FeesAndLimits;

			$fal->{$key} = $value;
            // foreach ($value as $k => $v) {
            //     $fal->{$k} = $v;
            //     $fal->{$k} = BaseSettings::castAttribute(FeesAndLimits::$casts[$k], $v);
            // }

//            $new_arr[$key] = (array)$fal;
        }

        return $new_arr;
    }


}