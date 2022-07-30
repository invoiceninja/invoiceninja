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

        // $this->markTestSkipped('Skip test no company gateways installed');
        
    }

    public function testAccessTokenGeneration()
    {

        $yodlee = new Yodlee(true);

        $access_token = $yodlee->getAccessToken('sbMem62e1e69547bfb1');

        nlog($access_token);

        $this->assertNotNull($access_token);
    }

    public function testGetCategories()
    {


        $yodlee = new Yodlee(true);
   
        $access_token = $yodlee->getAccessToken('sbMem62e1e69547bfb1');

        $transactions = $yodlee->getTransactionCategories($access_token);

//        nlog($transactions);

    }

    public function testGetAccounts()
    {

        $yodlee = new Yodlee(true);
   
        $access_token = $yodlee->getAccessToken('sbMem62e1e69547bfb1');

        $accounts = $yodlee->getAccounts($access_token);

        // nlog($accounts);
    }

    public function testGetTransactions()
    {

        $yodlee = new Yodlee(true);
   
        $access_token = $yodlee->getAccessToken('sbMem62e1e69547bfb1');

        $transactions = $yodlee->getTransactions($access_token);

        nlog($transactions);

    }


}
