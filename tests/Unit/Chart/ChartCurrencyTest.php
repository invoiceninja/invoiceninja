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
namespace Tests\Unit\Chart;

use App\Services\Chart\ChartService;
use App\Utils\Ninja;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ChartCurrencyTest extends TestCase
{
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testClientServiceDataSetBuild()
    {

        $haystack = [
            [
            'currency_id' => null,
            'amount' => 10
            ],
            [
            'currency_id' => 1,
            'amount' => 11
            ],
            [
            'currency_id' => 2,
            'amount' => 12
            ],
            [
            'currency_id' => 3,
            'amount' => 13
            ],
        ];

        $cs = new ChartService($this->company);

        nlog($cs->totals(now()->subYears(10), now()));

        $this->assertTrue(is_array($cs->totals(now()->subYears(10), now())));

    }

    /* coalesces the company currency with the null currencies */
    public function testFindNullValueinArray()
    {

        $haystack = [
            [
            'currency_id' => null,
            'amount' => 10
            ],
            [
            'currency_id' => 1,
            'amount' => 11
            ],
            [
            'currency_id' => 2,
            'amount' => 12
            ],
            [
            'currency_id' => 3,
            'amount' => 13
            ],
        ];

        $company_currency_id = 1;

        $c_key = array_search($company_currency_id , array_column($haystack, 'currency_id')); 

        $this->assertNotEquals($c_key, 2);
        $this->assertEquals($c_key, 1);

        $key = array_search(null , array_column($haystack, 'currency_id')); 

        $this->assertNotEquals($key, 39);
        $this->assertEquals($key, 0);

        $null_currency_amount = $haystack[$key]['amount'];

        unset($haystack[$key]);

        $haystack[$c_key]['amount'] += $null_currency_amount;

        $this->assertEquals($haystack[$c_key]['amount'], 21);

    }


    public function testCollectionMerging()
    {
        $currencies = collect([1,2,3,4,5,6]);

        $expense_currencies = collect([4,5,6,7,8]);

        $currencies = $currencies->merge($expense_currencies);

        $this->assertEquals($currencies->count(), 11);

        $currencies = $currencies->unique();

        $this->assertEquals($currencies->count(), 8);

    }


}