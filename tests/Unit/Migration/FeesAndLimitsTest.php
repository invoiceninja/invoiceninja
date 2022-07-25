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

namespace Tests\Unit\Migration;

use App\DataMapper\BaseSettings;
use App\DataMapper\FeesAndLimits;
use Tests\TestCase;

class FeesAndLimitsTest extends TestCase
{
    protected function setUp(): void
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
        $data['fee_tax_name1'] = '';
        $data['fee_tax_rate1'] = '';
        $data['fee_tax_name2'] = '';
        $data['fee_tax_rate2'] = '';
        $data['fee_tax_name3'] = '';
        $data['fee_tax_rate3'] = 0;
        $data['fee_cap'] = 0;

        $fees_and_limits_array = [];
        $fees_and_limits_array[] = $data;

        $transformed = $this->cleanFeesAndLimits($fees_and_limits_array);

        $this->assertTrue(is_array($transformed));
    }

    public function cleanFeesAndLimits($fees_and_limits)
    {
        $new_arr = [];

        foreach ($fees_and_limits as $key => $value) {
            $fal = new FeesAndLimits;
            // $fal->{$key} = $value;

            foreach ($value as $k => $v) {
                $fal->{$k} = $v;
                $fal->{$k} = BaseSettings::castAttribute(FeesAndLimits::$casts[$k], $v);
            }

            $new_arr[$key] = (array) $fal;
        }

        return $new_arr;
    }
}
