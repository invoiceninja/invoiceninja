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

use App\Factory\CompanyUserFactory;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
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

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $lower_permission_user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' =>  $this->faker->safeEmail(),
        ]);

        $this->low_cu = CompanyUserFactory::create($lower_permission_user->id, $this->company->id, $this->account->id);
        $this->low_cu->is_owner = false;
        $this->low_cu->is_admin = false;
        $this->low_cu->is_locked = false;
        $this->low_cu->permissions = '["view_task"]';
        $this->low_cu->save();

        $this->low_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken;
        $company_token->user_id = $lower_permission_user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->low_token;
        $company_token->is_system = true;
        $company_token->save();

    }

    // public function testGeneratingClassName()
    // {

        // $this->assertEquals('user', Str::snake(User::class));

        // $this->assertEquals('user',lcfirst(class_basename(Str::snake(User::class))));


    // }

    public function testRestrictedRoute()
    {
        // $permissions = ["view_invoice","view_client","edit_client","edit_invoice","create_invoice","create_client"];
       
        // $response = $this->withHeaders([
        //     'X-API-SECRET' => config('ninja.api_secret'),
        //     'X-API-TOKEN' => $this->token,
        // ])->get('/api/v1/clients/')
        //   ->assertStatus(200)
        //   ->assertJson(fn (AssertableJson $json) => $json->has('data',1)->etc());


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data',1)->etc());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/group_settings/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data',2)->etc());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs/')
          ->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data',11)->etc());


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->low_token,
        ])->get('/api/v1/users/');


          $response->assertStatus(200)
          ->assertJson(fn (AssertableJson $json) => $json->has('data',1)->etc());


        collect($this->list_routes)->filter(function ($route){
            return !in_array($route, ['tasks','users','group_settings','designs']);
        })->each(function($route){
            nlog($route);
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->low_token,
            ])->get("/api/v1/{$route}/")
              ->assertJson(fn (AssertableJson $json) =>
                $json->has('meta')
                 ->has('data',0)
                );

        });

       $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->low_token,
            ])->get('/api/v1/companies/'.$this->company->hashed_id)
              ->assertStatus(401);

           

    }
}
