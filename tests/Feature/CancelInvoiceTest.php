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

namespace Tests\Feature;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Invoice\HandleCancellation
 */
class CancelInvoiceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
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

    public function testCancelInvoice()
    {
        $this->assertTrue($this->invoice->invoiceCancellable($this->invoice));

        $client_balance = $this->client->balance;
        $invoice_balance = $this->invoice->balance;

        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status_id);

        $this->invoice->fresh()->service()->handleCancellation()->save();

        $this->assertEquals(0, $this->invoice->fresh()->balance);
        $this->assertEquals($this->client->fresh()->balance, ($client_balance - $invoice_balance));
        $this->assertNotEquals($client_balance, $this->client->fresh()->balance);
        $this->assertEquals(Invoice::STATUS_CANCELLED, $this->invoice->fresh()->status_id);
    }
}
