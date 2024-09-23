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
use App\Events\PurchaseOrder\PurchaseOrderWasArchived;
use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Events\PurchaseOrder\PurchaseOrderWasDeleted;
use App\Events\PurchaseOrder\PurchaseOrderWasRestored;
use App\Events\PurchaseOrder\PurchaseOrderWasUpdated;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasArchived;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasDeleted;
use App\Events\Quote\QuoteWasRestored;
use App\Events\Quote\QuoteWasUpdated;
use App\Events\RecurringInvoice\RecurringInvoiceWasArchived;
use App\Events\RecurringInvoice\RecurringInvoiceWasCreated;
use App\Events\RecurringInvoice\RecurringInvoiceWasDeleted;
use App\Events\RecurringInvoice\RecurringInvoiceWasRestored;
use App\Events\RecurringInvoice\RecurringInvoiceWasUpdated;
use App\Events\Subscription\SubscriptionWasArchived;
use App\Events\Subscription\SubscriptionWasCreated;
use App\Events\Subscription\SubscriptionWasDeleted;
use App\Events\Subscription\SubscriptionWasRestored;
use App\Events\Subscription\SubscriptionWasUpdated;
use App\Events\Task\TaskWasArchived;
use App\Events\Task\TaskWasCreated;
use App\Events\Task\TaskWasDeleted;
use App\Events\Task\TaskWasRestored;
use App\Events\Task\TaskWasUpdated;
use App\Events\User\UserWasArchived;
use App\Events\User\UserWasCreated;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasRestored;
use App\Events\User\UserWasUpdated;
use App\Events\Vendor\VendorWasArchived;
use App\Events\Vendor\VendorWasCreated;
use App\Events\Vendor\VendorWasDeleted;
use App\Events\Vendor\VendorWasRestored;
use App\Events\Vendor\VendorWasUpdated;
use App\Http\Middleware\PasswordProtection;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class EventTest extends TestCase
{
    use MockAccountData;
    use MakesHash;
    use DatabaseTransactions;

    public $faker;

    public function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class,
            PasswordProtection::class
        );
    }

    public function testExpenseEvents()
    {
        Event::fake();

        $data = [
            'public_notes' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'public_notes' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/expenses/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(ExpenseWasCreated::class);
        Event::assertDispatched(ExpenseWasUpdated::class);
        Event::assertDispatched(ExpenseWasArchived::class);
        Event::assertDispatched(ExpenseWasRestored::class);
        Event::assertDispatched(ExpenseWasDeleted::class);

    }


    public function testVendorEvents()
    {
        Event::fake();


        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/vendors/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(VendorWasCreated::class);
        Event::assertDispatched(VendorWasUpdated::class);
        Event::assertDispatched(VendorWasArchived::class);
        Event::assertDispatched(VendorWasRestored::class);
        Event::assertDispatched(VendorWasDeleted::class);

    }


    public function testTaskEvents()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'dude',
        ];

        Event::fake();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'description' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tasks/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(TaskWasCreated::class);
        Event::assertDispatched(TaskWasUpdated::class);
        Event::assertDispatched(TaskWasArchived::class);
        Event::assertDispatched(TaskWasRestored::class);
        Event::assertDispatched(TaskWasDeleted::class);

    }

    public function testCreditEvents()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        Event::fake();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/credits/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(CreditWasCreated::class);
        Event::assertDispatched(CreditWasUpdated::class);
        Event::assertDispatched(CreditWasArchived::class);
        Event::assertDispatched(CreditWasRestored::class);
        Event::assertDispatched(CreditWasDeleted::class);

    }


    public function testQuoteEvents()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        Event::fake();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $quote = Quote::find($this->decodePrimaryKey($arr['data']['id']));
        $quote->due_date = now()->addYear();
        $quote->status_id = Quote::STATUS_SENT;
        $quote->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk?action=approve', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(QuoteWasCreated::class);
        Event::assertDispatched(QuoteWasUpdated::class);
        Event::assertDispatched(QuoteWasArchived::class);
        Event::assertDispatched(QuoteWasRestored::class);
        Event::assertDispatched(QuoteWasDeleted::class);
        Event::assertDispatched(QuoteWasApproved::class);

    }


    //@TODO paymentwasvoided
    //@TODO paymentwasrefunded

    public function testPaymentEvents()
    {
        Event::fake();


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
        ])->postJson('/api/v1/payments?include=invoices', $data)
        ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'transaction_reference' => 'testing'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/' . $arr['data']['id'], $data)
        ->assertStatus(200);

        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(PaymentWasCreated::class);
        Event::assertDispatched(PaymentWasUpdated::class);
        Event::assertDispatched(PaymentWasArchived::class);
        Event::assertDispatched(PaymentWasRestored::class);
        Event::assertDispatched(PaymentWasDeleted::class);

    }


    public function testInvoiceEvents()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        Event::fake();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(InvoiceWasCreated::class);
        Event::assertDispatched(InvoiceWasUpdated::class);
        Event::assertDispatched(InvoiceWasArchived::class);
        Event::assertDispatched(InvoiceWasRestored::class);
        Event::assertDispatched(InvoiceWasDeleted::class);

    }



    public function testRecurringInvoiceEvents()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dudex',
            'frequency_id' => 1,
        ];

        Event::fake();


        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/recurring_invoices/', $data);
        } catch (ValidationException $e) {
            // $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $response->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
            'frequency_id' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/recurring_invoices/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk?action=delete', $data)
        ->assertStatus(200);

        Event::assertDispatched(RecurringInvoiceWasCreated::class);
        Event::assertDispatched(RecurringInvoiceWasUpdated::class);
        Event::assertDispatched(RecurringInvoiceWasArchived::class);
        Event::assertDispatched(RecurringInvoiceWasRestored::class);
        Event::assertDispatched(RecurringInvoiceWasDeleted::class);

    }



    public function testClientEvents()
    {
        Event::fake();


        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/clients/' . $arr['data']['id'], $data)
        ->assertStatus(200);

        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(ClientWasCreated::class);
        Event::assertDispatched(ClientWasUpdated::class);
        Event::assertDispatched(ClientWasArchived::class);
        Event::assertDispatched(ClientWasRestored::class);
        Event::assertDispatched(ClientWasDeleted::class);

    }


    public function testUserEvents()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        Event::fake();

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                    'is_admin' => false,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data)
          ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'first_name' => 'hasdasdy',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                    'is_admin' => false,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk?action=delete', $data)
        ->assertStatus(200);




        Event::assertDispatched(UserWasCreated::class);

        Event::assertDispatched(UserWasUpdated::class);

        Event::assertDispatched(UserWasArchived::class);

        Event::assertDispatched(UserWasRestored::class);

        Event::assertDispatched(UserWasDeleted::class);


    }

    public function testSubscriptionEvents()
    {
        Event::fake();


        $data = [
            'name' => $this->faker->firstName,
            'steps' => "cart,auth.login-or-register",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'steps' => "cart,auth.login-or-register",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/subscriptions/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
            'steps' => "cart,auth.login-or-register",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk?action=delete', $data)
        ->assertStatus(200);


        Event::assertDispatched(SubscriptionWasCreated::class);
        Event::assertDispatched(SubscriptionWasUpdated::class);
        Event::assertDispatched(SubscriptionWasArchived::class);
        Event::assertDispatched(SubscriptionWasRestored::class);
        Event::assertDispatched(SubscriptionWasDeleted::class);

    }


    public function testPurchaseOrderEvents()
    {
        /* Test fire new invoice */
        $data = [
            'vendor_id' => $this->vendor->hashed_id,
            'number' => 'dude',
        ];

        Event::fake();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'vendor_id' => $this->vendor->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/purchase_orders/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $quote = PurchaseOrder::find($this->decodePrimaryKey($arr['data']['id']));
        $quote->status_id = PurchaseOrder::STATUS_SENT;
        $quote->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders/bulk?action=mark_sent', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders/bulk?action=delete', $data)
        ->assertStatus(200);

        Event::assertDispatched(PurchaseOrderWasCreated::class);
        Event::assertDispatched(PurchaseOrderWasUpdated::class);
        Event::assertDispatched(PurchaseOrderWasArchived::class);
        Event::assertDispatched(PurchaseOrderWasRestored::class);
        Event::assertDispatched(PurchaseOrderWasDeleted::class);

    }
}
