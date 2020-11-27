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
namespace Tests\Integration;

use App\Events\Client\ClientWasArchived;
use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasDeleted;
use App\Events\Client\ClientWasRestored;
use App\Events\Client\ClientWasUpdated;
use App\Events\Credit\CreditWasArchived;
use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasDeleted;
use App\Events\Credit\CreditWasRestored;
use App\Events\Credit\CreditWasUpdated;
use App\Events\Expense\ExpenseWasArchived;
use App\Events\Expense\ExpenseWasCreated;
use App\Events\Expense\ExpenseWasDeleted;
use App\Events\Expense\ExpenseWasRestored;
use App\Events\Expense\ExpenseWasUpdated;
use App\Events\Invoice\InvoiceWasArchived;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasDeleted;
use App\Events\Invoice\InvoiceWasRestored;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasArchived;
use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasDeleted;
use App\Events\Payment\PaymentWasRestored;
use App\Events\Payment\PaymentWasUpdated;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasArchived;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasDeleted;
use App\Events\Quote\QuoteWasRestored;
use App\Events\Quote\QuoteWasUpdated;
use App\Events\Task\TaskWasArchived;
use App\Events\Task\TaskWasCreated;
use App\Events\Task\TaskWasDeleted;
use App\Events\Task\TaskWasRestored;
use App\Events\Task\TaskWasUpdated;
use App\Events\Vendor\VendorWasArchived;
use App\Events\Vendor\VendorWasCreated;
use App\Events\Vendor\VendorWasDeleted;
use App\Events\Vendor\VendorWasRestored;
use App\Events\Vendor\VendorWasUpdated;
use App\Models\Invoice;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class EventTest extends TestCase
{
    use MockAccountData;
    use MakesHash;

    public function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();
    }

    public function testExpenseEvents()
    {
        $this->expectsEvents([
            ExpenseWasCreated::class,
            ExpenseWasUpdated::class,
            ExpenseWasArchived::class,
            ExpenseWasRestored::class,
            ExpenseWasDeleted::class,
        ]);

        $data = [
            'public_notes' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'public_notes' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/expenses/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    public function testVendorEvents()
    {
        $this->expectsEvents([
            VendorWasCreated::class,
            VendorWasUpdated::class,
            VendorWasArchived::class,
            VendorWasRestored::class,
            VendorWasDeleted::class,
        ]);

        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/vendors/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    public function testTaskEvents()
    {

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'dude',
        ];

        $this->expectsEvents([
            TaskWasCreated::class,
            TaskWasUpdated::class,
            TaskWasArchived::class,
            TaskWasRestored::class,
            TaskWasDeleted::class,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tasks/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tasks/bulk?action=delete', $data)
        ->assertStatus(200);
    }

    public function testCreditEvents()
    {

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $this->expectsEvents([
            CreditWasCreated::class,
            CreditWasUpdated::class,
            CreditWasArchived::class,
            CreditWasRestored::class,
            CreditWasDeleted::class,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk?action=delete', $data)
        ->assertStatus(200);
    }

    public function testQuoteEvents()
    {

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $this->expectsEvents([
            QuoteWasCreated::class,
            QuoteWasUpdated::class,
            QuoteWasArchived::class,
            QuoteWasRestored::class,
            QuoteWasDeleted::class,
            QuoteWasApproved::class,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/quotes/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $quote = Quote::find($this->decodePrimaryKey($arr['data']['id']));
        $quote->status_id = Quote::STATUS_SENT;
        $quote->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk?action=approve', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    //@TODO paymentwasvoided
    //@TODO paymentwasrefunded

    public function testPaymentEvents()
    {
        $this->expectsEvents([
            PaymentWasCreated::class,
            PaymentWasUpdated::class,
            PaymentWasArchived::class,
            PaymentWasRestored::class,
            PaymentWasDeleted::class,
        ]);

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments?include=invoices', $data)
        ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'transaction_reference' => 'testing'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/payments/' . $arr['data']['id'], $data)
        ->assertStatus(200);

        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    public function testInvoiceEvents()
    {

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $this->expectsEvents([
            InvoiceWasCreated::class,
            InvoiceWasUpdated::class,
            InvoiceWasArchived::class,
            InvoiceWasRestored::class,
            InvoiceWasDeleted::class,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    public function testClientEvents()
    {
        $this->expectsEvents([
            ClientWasCreated::class,
            ClientWasUpdated::class,
            ClientWasArchived::class,
            ClientWasRestored::class,
            ClientWasDeleted::class,
        ]);

        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=delete', $data)
        ->assertStatus(200);
    }
}
