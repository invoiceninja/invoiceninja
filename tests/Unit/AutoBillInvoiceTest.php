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
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAutoBillFunctionality()
    {
        $this->assertEquals($this->client->balance, 10);
        $this->assertEquals($this->client->paid_to_date, 0);
        $this->assertEquals($this->client->credit_balance, 10);

        $this->invoice->service()->markSent()->autoBill();

        $this->assertNotNull($this->invoice->payments());
        $this->assertEquals(10, $this->invoice->payments()->sum('payments.amount'));

        $this->assertEquals($this->client->fresh()->balance, 0);
        $this->assertEquals($this->client->fresh()->paid_to_date, 10);
        $this->assertEquals($this->client->fresh()->credit_balance, 0);
    }

    // public function testAutoBillSetOffFunctionality()
    // {

    //     $settings = $this->company->settings;
    //     $settings->use_credits_payment = 'off';

    //     $this->company->settings = $settings;
    //     $this->company->save();

    //     $this->assertEquals($this->client->balance, 10);
    //     $this->assertEquals($this->client->paid_to_date, 0);
    //     $this->assertEquals($this->client->credit_balance, 10);

    //     $this->invoice->service()->markSent()->autoBill()->save();

    //     $this->assertNotNull($this->invoice->payments());
    //     $this->assertEquals(0, $this->invoice->payments()->sum('payments.amount'));

    //     $this->assertEquals($this->client->balance, 10);
    //     $this->assertEquals($this->client->paid_to_date, 0);
    //     $this->assertEquals($this->client->credit_balance, 10);

    // }
}
