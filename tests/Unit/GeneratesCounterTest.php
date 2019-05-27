<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesCounter
 */
class GeneratesCounterTest extends TestCase
{

    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {

        parent::setUp();

        $this->makeTestData();

    }

    public function testGeneric()
    {
    	$this->assertEquals(true, true);
    }


}