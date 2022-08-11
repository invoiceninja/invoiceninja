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

namespace Tests\Feature\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class YodleeApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if(!config('ninja.yodlee.client_id'))
            $this->markTestSkipped('Skip test no Yodlee API credentials found');
        
    }

    public function testDataMatching()
    {

        $transaction = collect([
            (object)[
                'description' => 'tinkertonkton'
            ],
            (object)[
                'description' => 'spud'
            ],
        ]);

        $this->assertEquals(2, $transaction->count());

        $hit = $transaction->where('description', 'spud')->first();

        $this->assertNotNull($hit);

        $hit = $transaction->where('description', 'tinkertonkton')->first();

        $this->assertNotNull($hit);

        $hit = $transaction->contains('description', 'tinkertonkton');

        $this->assertTrue($hit);


        $transaction = collect([
            (object)[
                'description' => 'tinker and spice'
            ],
            (object)[
                'description' => 'spud with water'
            ],
        ]);

        $hit = $transaction->contains('description', 'tinker and spice');

        $this->assertTrue($hit);


        $invoice = $transaction->first(function ($value, $key) {

            return str_contains($value->description, 'tinker');
            
        });

        $this->assertNotNull($invoice);


    }

    public function testYodleeInstance()
    {

        $yodlee = new Yodlee();
        $yodlee->setTestMode();

        $this->assertNotNull($yodlee);

        $this->assertInstanceOf(Yodlee::class, $yodlee);
    }

    public function testAccessTokenGeneration()
    {

        $yodlee = new Yodlee('sbMem62e1e69547bfb1');
        $yodlee->setTestMode();

        $access_token = $yodlee->getAccessToken();

        $this->assertNotNull($access_token);
    }

/**

   [transactionCategory] => Array
        (
            [0] => stdClass Object
                (
                    [id] => 1
                    [source] => SYSTEM
                    [classification] => PERSONAL
                    [category] => Uncategorized
                    [type] => UNCATEGORIZE
                    [highLevelCategoryId] => 10000017
                    [highLevelCategoryName] => Uncategorized
                    [defaultCategoryName] => Uncategorized
                    [defaultHighLevelCategoryName] => Uncategorized
                )

            [1] => stdClass Object
                (
                    [id] => 2
                    [source] => SYSTEM
                    [classification] => PERSONAL
                    [category] => Automotive/Fuel
                    [type] => EXPENSE
                    [detailCategory] => Array
                        (
                            [0] => stdClass Object
                                (
                                    [id] => 1041
                                    [name] => Registration/Licensing
                                )

                            [1] => stdClass Object
                                (
                                    [id] => 1145
                                    [name] => Automotive
                                )

                            [2] => stdClass Object
                                (
                                    [id] => 1218
                                    [name] => Auto Fees/Penalties
                                )

                            [3] => stdClass Object
                                (
                                    [id] => 1260
                                    [name] => Car Appraisers
                                )

                            [4] => stdClass Object
                                (
                                    [id] => 1261
                                    [name] => Car Dealers
                                )

                            [5] => stdClass Object
                                (
                                    [id] => 1262
                                    [name] => Car Dealers and Leasing
                                )

                            [6] => stdClass Object
                                (
                                    [id] => 1263
                                    [name] => Car Parts and Accessories
                                )

                            [7] => stdClass Object
                                (
                                    [id] => 1264
                                    [name] => Car Wash and Detail
                                )

                            [8] => stdClass Object
                                (
                                    [id] => 1265
                                    [name] => Classic and Antique Car
                                )

                            [9] => stdClass Object
                                (
                                    [id] => 1267
                                    [name] => Maintenance and Repair
                                )

                            [10] => stdClass Object
                                (
                                    [id] => 1268
                                    [name] => Motorcycles/Mopeds/Scooters
                                )

                            [11] => stdClass Object
                                (
                                    [id] => 1269
                                    [name] => Oil and Lube
                                )

                            [12] => stdClass Object
                                (
                                    [id] => 1270
                                    [name] => Motorcycle Repair
                                )

                            [13] => stdClass Object
                                (
                                    [id] => 1271
                                    [name] => RVs and Motor Homes
                                )

                            [14] => stdClass Object
                                (
                                    [id] => 1272
                                    [name] => Motorcycle Sales
                                )

                            [15] => stdClass Object
                                (
                                    [id] => 1273
                                    [name] => Salvage Yards
                                )

                            [16] => stdClass Object
                                (
                                    [id] => 1274
                                    [name] => Smog Check
                                )

                            [17] => stdClass Object
                                (
                                    [id] => 1275
                                    [name] => Tires
                                )

                            [18] => stdClass Object
                                (
                                    [id] => 1276
                                    [name] => Towing
                                )

                            [19] => stdClass Object
                                (
                                    [id] => 1277
                                    [name] => Transmissions
                                )

                            [20] => stdClass Object
                                (
                                    [id] => 1278
                                    [name] => Used Cars
                                )

                            [21] => stdClass Object
                                (
                                    [id] => 1240
                                    [name] => e-Charging
                                )

                            [22] => stdClass Object
                                (
                                    [id] => 1266
                                    [name] => Gas Stations
                                )

                        )

                    [highLevelCategoryId] => 10000003
                    [highLevelCategoryName] => Automotive Expenses
                    [defaultCategoryName] => Automotive Expenses
                    [defaultHighLevelCategoryName] => Automotive Expenses
                )

*/


    public function testGetCategories()
    {


        $yodlee = new Yodlee('sbMem62e1e69547bfb2');
        $yodlee->setTestMode();

        $transactions = $yodlee->getTransactionCategories();
 
// nlog($transactions);

        $this->assertIsArray($transactions->transactionCategory);

    }

/**
[2022-08-05 01:29:45] local.INFO: stdClass Object
(
    [account] => Array
        (
            [0] => stdClass Object
                (
                    [CONTAINER] => bank
                    [providerAccountId] => 11308693
                    [accountName] => My CD - 8878
                    [accountStatus] => ACTIVE
                    [accountNumber] => xxxx8878
                    [aggregationSource] => USER
                    [isAsset] => 1
                    [balance] => stdClass Object
                        (
                            [currency] => USD
                            [amount] => 49778.07
                        )

                    [id] => 12331861
                    [includeInNetWorth] => 1
                    [providerId] => 18769
                    [providerName] => Dag Site Captcha
                    [isManual] => 
                    [currentBalance] => stdClass Object
                        (
                            [currency] => USD
                            [amount] => 49778.07
                        )

                    [accountType] => CD
                    [displayedName] => LORETTA
                    [createdDate] => 2022-07-28T06:55:33Z
                    [lastUpdated] => 2022-07-28T06:56:09Z
                    [dataset] => Array
                        (
                            [0] => stdClass Object
                                (
                                    [name] => BASIC_AGG_DATA
                                    [additionalStatus] => AVAILABLE_DATA_RETRIEVED
                                    [updateEligibility] => ALLOW_UPDATE
                                    [lastUpdated] => 2022-07-28T06:55:50Z
                                    [lastUpdateAttempt] => 2022-07-28T06:55:50Z
                                )

                        )

                )
        [1] => stdClass Object
                (
                    [CONTAINER] => bank
                    [providerAccountId] => 11308693
                    [accountName] => Joint Savings - 7159
                    [accountStatus] => ACTIVE
                    [accountNumber] => xxxx7159
                    [aggregationSource] => USER
                    [isAsset] => 1
                    [balance] => stdClass Object
                        (
                            [currency] => USD
                            [amount] => 186277.45
                        )

                    [id] => 12331860
                    [includeInNetWorth] => 1
                    [providerId] => 18769
                    [providerName] => Dag Site Captcha
                    [isManual] => 
                    [availableBalance] => stdClass Object
                        (
                            [currency] => USD
                            [amount] => 186277.45
                        )

                    [currentBalance] => stdClass Object
                        (
                            [currency] => USD
                            [amount] => 186277.45
                        )

                    [accountType] => SAVINGS
                    [displayedName] => LYDIA
                    [createdDate] => 2022-07-28T06:55:33Z
                    [classification] => PERSONAL
                    [lastUpdated] => 2022-07-28T06:56:09Z
                    [dataset] => Array
                        (
                            [0] => stdClass Object
                                (
                                    [name] => BASIC_AGG_DATA
                                    [additionalStatus] => AVAILABLE_DATA_RETRIEVED
                                    [updateEligibility] => ALLOW_UPDATE
                                    [lastUpdated] => 2022-07-28T06:55:50Z
                                    [lastUpdateAttempt] => 2022-07-28T06:55:50Z
                                )

                        )

                )
*/
    public function testGetAccounts()
    {

        $yodlee = new Yodlee('sbMem62e1e69547bfb1');
        $yodlee->setTestMode();

        $accounts = $yodlee->getAccounts();
nlog($accounts);
        $this->assertIsArray($accounts);
    }


/**
[2022-08-05 01:36:34] local.INFO: stdClass Object
(
    [transaction] => Array
        (
            [0] => stdClass Object
                (
                    [CONTAINER] => bank
                    [id] => 103953585
                    [amount] => stdClass Object
                        (
                            [amount] => 480.66
                            [currency] => USD
                        )

                    [categoryType] => UNCATEGORIZE
                    [categoryId] => 1
                    [category] => Uncategorized
                    [categorySource] => SYSTEM
                    [highLevelCategoryId] => 10000017
                    [createdDate] => 2022-08-04T21:50:17Z
                    [lastUpdated] => 2022-08-04T21:50:17Z
                    [description] => stdClass Object
                        (
                            [original] => CHEROKEE NATION TAX TA TAHLEQUAH OK
                        )

                    [isManual] => 
                    [sourceType] => AGGREGATED
                    [date] => 2022-08-03
                    [transactionDate] => 2022-08-03
                    [postDate] => 2022-08-03
                    [status] => POSTED
                    [accountId] => 12331794
                    [runningBalance] => stdClass Object
                        (
                            [amount] => 480.66
                            [currency] => USD
                        )

                    [checkNumber] => 998
                )

 
 */

    public function testGetTransactions()
    {

        $yodlee = new Yodlee('sbMem62e1e69547bfb1');
        $yodlee->setTestMode();

        $transactions = $yodlee->getTransactions(['categoryId' => 2, 'fromDate' => '2000-01-01']);

        $this->assertIsArray($transactions);

    }


    public function testGetTransactionsWithParams()
    {

        $yodlee = new Yodlee('sbMem62e1e69547bfb1');
        $yodlee->setTestMode();

        $data = [
            'basetype' => 'DEBIT', //CREDIT
            'CONTAINER' => 'bank',
            'top' => 500,
            'fromDate' => '2000-10-10', /// YYYY-MM-DD
        ];

        $accounts = $yodlee->getTransactions($data); 


        nlog($accounts);
    }





}
