<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Invoice\AutoBillInvoice
 */
class AutoBillInvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAutoBillFunctionality()
    {

        $this->assertEquals($this->client->balance, 10);
        $this->assertEquals($this->client->paid_to_date, 0);
        $this->assertEquals($this->client->credit_balance, 10);

        $this->invoice->service()->markSent()->autoBill()->save();

        $this->assertNotNull($this->invoice->payments());
        $this->assertEquals(10, $this->invoice->payments()->sum('payments.amount'));

        $this->assertEquals($this->client->balance, 0);
        $this->assertEquals($this->client->paid_to_date, 10);
        $this->assertEquals($this->client->credit_balance, 0);
    
    }

}
