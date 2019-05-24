<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Cron\RecurringInvoicesCron
 */
    
class RecurringInvoicesCronTest extends TestCase
{

    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {

        parent::setUp();

        $this->makeTestData();


    }

    public function testCountCorrectNumberOfRecurringInvoicesDue()
    {
        //spin up 5 valid and 1 invalid recurring invoices
        
        
    }

}
