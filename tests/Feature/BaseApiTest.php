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

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\ClientGatewayTokenFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\WebhookFactory;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Scheduler;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\BaseController
 */
class BaseApiTest extends TestCase
{
    use MockAccountData;

    public CompanyUser $owner_cu;

    public CompanyUser $low_cu;

    private $list_routes = [
        'products',
        'clients',
        'invoices',
        'recurring_invoices',
        'payments',
        'quotes',
        'credits',
        'projects',
        'tasks',
        'vendors',
        'purchase_orders',
        'expenses',
        'recurring_expenses',
        'task_schedulers',
        'bank_integrations',
        'bank_transactions',
        'tax_rates',
        'users',
        'payment_terms',
        'purchase_orders',
        'subscriptions',
        'webhooks',
        'group_settings',
        'designs',
        'expense_categories',
        'documents',
        'company_gateways',
        'client_gateway_tokens',
        'bank_transaction_rules',
    ];

    public string $low_token;

    public string $owner_token;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->company = $company;

        $company->client_registration_fields = ClientRegistrationFields::generate();
        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = 'test@example.com';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;
        $company->track_inventory = true;
        $company->settings = $settings;
        $company->save();

        $this->account->default_company_id = $company->id;
        $this->account->save();

        $owner_user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' =>  $this->faker->safeEmail(),
        ]);

        $this->owner_cu = CompanyUserFactory::create($owner_user->id, $company->id, $this->account->id);
        $this->owner_cu->is_owner = true;
        $this->owner_cu->is_admin = true;
        $this->owner_cu->is_locked = false;
        $this->owner_cu->permissions = '[]';
        $this->owner_cu->save();

        $this->owner_token = \Illuminate\Support\Str::random(64);

        $user_id = $owner_user->id;

        $company_token = new CompanyToken();
        $company_token->user_id = $owner_user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->owner_token;
        $company_token->is_system = true;
        $company_token->save();


        $lower_permission_user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' =>  $this->faker->safeEmail(),
        ]);

        $this->low_cu = CompanyUserFactory::create($lower_permission_user->id, $company->id, $this->account->id);
        $this->low_cu->is_owner = false;
        $this->low_cu->is_admin = false;
        $this->low_cu->is_locked = false;
        $this->low_cu->permissions = '["view_task"]';
        $this->low_cu->save();

        $this->low_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $lower_permission_user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->low_token;
        $company_token->is_system = true;
        $company_token->save();

        Product::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user_id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'amount' => 10,
        ]);

        $contact2 = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'send_email' => true,
        ]);

        $vendor = Vendor::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'currency_id' => 1,
        ]);

        $vendor_contact = VendorContact::factory()->create([
            'user_id' => $user_id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $vendor_contact2 = VendorContact::factory()->create([
            'user_id' => $user_id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $company->id,
            'send_email' => true,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $expense = Expense::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $recurring_expense = RecurringExpense::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'frequency_id' => 5,
            'remaining_cycles' => 5,
        ]);

        $recurring_quote = RecurringQuote::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $task = Task::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $quote = Quote::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $credit = Credit::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
        ]);


        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $task_status = TaskStatus::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $task->status_id = TaskStatus::where('company_id', $company->id)->first()->id;
        $task->save();

        $expense_category = ExpenseCategory::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $tax_rate = TaxRate::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $gs = new GroupSetting();
        $gs->name = 'Test';
        $gs->company_id = $client->company_id;
        $gs->settings = ClientSettings::buildClientSettings($company->settings, $client->settings);

        $gs_settings = $gs->settings;
        $gs_settings->website = 'http://staging.invoicing.co';
        $gs->settings = $gs_settings;
        $gs->save();

        $scheduler = Scheduler::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $bank_integration = BankIntegration::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'account_id' => $this->account->id,
        ]);

        $bank_transaction = BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'bank_integration_id' => $bank_integration->id,
        ]);

        $bank_transaction_rule = BankTransactionRule::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);


        $subscription = Subscription::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $webhook = WebhookFactory::create($company->id, $user_id);
        $webhook->save();

        $document = Document::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
        ]);

        $cg = new CompanyGateway();
        $cg->company_id = $company->id;
        $cg->user_id = $user_id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt('{"publishableKey":"pk_test_P1riKDKD0p","apiKey":"sk_test_Yorqvz45"}');
        $cg->fees_and_limits = [];
        $cg->save();

        $cgt = ClientGatewayTokenFactory::create($company->id);
        $cgt->save();
    }

    // public function testGeneratingClassName()
    // {

    // $this->assertEquals('user', Str::snake(User::class));

    // $this->assertEquals('user',lcfirst(class_basename(Str::snake(User::class))));


    // }

    /**
     * Tests admin/owner facing routes respond with the correct status and/or data set
     */
    public function testOwnerRoutes()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->owner_token,
        ])->get('/api/v1/users/');

        $response->assertStatus(200)
        ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)->etc());

        /*does not test the number of records however*/
        collect($this->list_routes)->filter(function ($route) {
            return !in_array($route, ['users','designs','payment_terms']);
        })->each(function ($route) {

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->owner_token,
            ])->get("/api/v1/{$route}/")
              ->assertJson(
                  fn (AssertableJson $json) =>
                $json->has('meta')
                 ->has('data', 1)
              );
        });
    }

    public function testOwnerAccessCompany()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->low_token,
        ])->get('/api/v1/companies/'.$this->company->hashed_id)
          ->assertStatus(200);
    }


    public function testAdminRoutes()
    {
        $this->owner_cu = CompanyUser::where('user_id', $this->owner_cu->user_id)->where('company_id', $this->owner_cu->company_id)->first();
        $this->owner_cu->is_owner = false;
        $this->owner_cu->is_admin = true;
        $this->owner_cu->is_locked = false;
        $this->owner_cu->permissions = '[]';
        $this->owner_cu->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->owner_token,
        ])->get('/api/v1/users/');

        $response->assertStatus(200)
        ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)->etc());

        collect($this->list_routes)->filter(function ($route) {
            return !in_array($route, ['users','designs','payment_terms']);
        })->each(function ($route) {
            // nlog($route);
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->owner_token,
            ])->get("/api/v1/{$route}/")
              ->assertStatus(200)
              ->assertJson(
                  fn (AssertableJson $json) =>
                $json->has('meta')
                 ->has('data', 1)
              );
        });
    }

    public function testAdminAccessCompany()
    {
        $response = $this->withHeaders([
         'X-API-SECRET' => config('ninja.api_secret'),
         'X-API-TOKEN' => $this->owner_token,
         ])->get('/api/v1/companies/'.$this->company->hashed_id)
           ->assertStatus(200);
    }

    public function testAdminLockedRoutes()
    {
        $this->owner_cu = CompanyUser::where('user_id', $this->owner_cu->user_id)->where('company_id', $this->owner_cu->company_id)->first();
        $this->owner_cu->is_owner = false;
        $this->owner_cu->is_admin = true;
        $this->owner_cu->is_locked = true;
        $this->owner_cu->permissions = '[]';
        $this->owner_cu->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->owner_token,
        ])->get('/api/v1/users/')
          ->assertStatus(403);

        collect($this->list_routes)->filter(function ($route) {
            return !in_array($route, ['users','designs','payment_terms']);
        })->each(function ($route) {
            // nlog($route);
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->owner_token,
            ])->get("/api/v1/{$route}/")
              ->assertStatus(403);
        });
    }

    public function testAdminLockedCompany()
    {
        $this->owner_cu = CompanyUser::where('user_id', $this->owner_cu->user_id)->where('company_id', $this->owner_cu->company_id)->first();
        $this->owner_cu->is_owner = false;
        $this->owner_cu->is_admin = true;
        $this->owner_cu->is_locked = true;
        $this->owner_cu->permissions = '[]';
        $this->owner_cu->save();

        $response = $this->withHeaders([
         'X-API-SECRET' => config('ninja.api_secret'),
         'X-API-TOKEN' => $this->owner_token,
         ])->get('/api/v1/companies/'.$this->company->hashed_id)
           ->assertStatus(403);
    }

    /**
     * Tests user facing routes respond with the correct status and/or data set
     */
    public function testRestrictedUserRoute()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)->etc());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/group_settings/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)->etc());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data', 11)->etc());


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->low_token,
        ])->get('/api/v1/users/');

        $response->assertStatus(200)
        ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)->etc());

        collect($this->list_routes)->filter(function ($route) {
            return !in_array($route, ['tasks', 'users', 'group_settings','designs','client_gateway_tokens']);
        })->each(function ($route) {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->low_token,
            ])->get("/api/v1/{$route}/")
              ->assertJson(
                  fn (AssertableJson $json) =>
                $json->has('meta')
                 ->has('data', 0)
              );
        });

        $response = $this->withHeaders([
                 'X-API-SECRET' => config('ninja.api_secret'),
                 'X-API-TOKEN' => $this->low_token,
             ])->get('/api/v1/companies/'.$this->company->hashed_id)
               ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->low_token,
        ])->get('/api/v1/client_gateway_tokens');

        $response->assertStatus(403);
    }
}
