<?php

namespace Tests\Feature;

use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Util\ReminderJob
 */

class ReminderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testReminderQueryCatchesDate()
    {
        $this->invoice->next_send_date = now()->format('Y-m-d');
        $this->invoice->save();

        $invoices = Invoice::where('next_send_date', Carbon::today())->get();

        $this->assertEquals(1, $invoices->count());
    }

    public function testReminderHits()
    {
        
    }
}
