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

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\CompanyToken;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Http\Controllers\SubscriptionController
 */
class SubscriptionApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testSubscriptionCronLocalization()
    {

        $settings = CompanySettings::defaults();
        $settings->timezone_id = '50'; //europe/vienna

        $c2 = Company::factory()->create([
            'account_id' => $this->company->account_id,
            'settings' => $settings
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $c2->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = true;
        $cu->permissions = '["view_client"]';
        $cu->save();

        $different_company_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $c2->id;
        $company_token->account_id = $c2->account_id;
        $company_token->name = 'test token';
        $company_token->token = $different_company_token;
        $company_token->is_system = true;
        $company_token->save();


        $s = Subscription::factory()->create([
            'company_id' => $c2->id,
            'user_id' => $this->user->id,
        ]);

        $client2 = Client::factory()->create([
            'company_id' => $c2->id,
            'user_id' => $this->user->id,
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $c2->id,
            'user_id' => $this->user->id,
            'subscription_id' => $s->id,
            'due_date' => now()->startOfDay(),
            'client_id' => $client2->id,
            'status_id' => Invoice::STATUS_SENT
        ]);

        $settings = CompanySettings::defaults();
        $settings->timezone_id = '110'; //sydney/australia

        $c = Company::factory()->create([
            'account_id' => $this->company->account_id,
            'settings' => $settings,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $c->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = true;
        $cu->permissions = '["view_client"]';
        $cu->save();

        $different_company_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $c->id;
        $company_token->account_id = $c->account_id;
        $company_token->name = 'test token';
        $company_token->token = $different_company_token;
        $company_token->is_system = true;
        $company_token->save();

        $s1 = Subscription::factory()->create([
            'company_id' => $c->id,
            'user_id' => $this->user->id,
        ]);


        $client = Client::factory()->create([
            'company_id' => $c2->id,
            'user_id' => $this->user->id,
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $c->id,
            'user_id' => $this->user->id,
            'subscription_id' => $s1->id,
            'due_date' => now()->startOfDay(),
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT
        ]);

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $company = Company::find($c->id); //sydney

        $timezone_now = now()->setTimezone($company->timezone()->name);

        $this->assertEquals('Australia/Sydney', $timezone_now->timezoneName);

        $this->travelTo($timezone_now->copy()->startOfDay()->subHour());

        $i = false;

        //Capture companies within the window of 00:00 and 00:30
        if($timezone_now->gte($timezone_now->copy()->startOfDay()) && $timezone_now->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->where('balance', '>', 0)
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertFalse($i);

        $this->travelTo($timezone_now->copy()->startOfDay());

        if(now()->gte($timezone_now->copy()->startOfDay()) && now()->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertEquals(1, $i->count());

        $i = false;

        $this->travelTo($timezone_now->copy()->startOfDay()->addHours(2));

        if($timezone_now->gte($timezone_now->copy()->startOfDay()) && $timezone_now->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->where('balance', '>', 0)
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertFalse($i);

        $count = Invoice::whereNotNull('subscription_id')->whereIn('company_id', [$c2->id, $c->id])->count();

        $this->assertEquals(2, $count);

        $this->travelBack();
        //////////////////////////////////////////// vienna //////////////////////////////////////////////////

        $company = Company::find($c2->id); //vienna

        $timezone_now = now()->setTimezone($company->timezone()->name);

        $this->assertEquals('Europe/Vienna', $timezone_now->timezoneName);

        $this->travelTo($timezone_now->startOfDay()->subHours(2));

        $i = false;

        //Capture companies within the window of 00:00 and 00:30
        if($timezone_now->gte($timezone_now->copy()->startOfDay()) && $timezone_now->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->where('balance', '>', 0)
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertFalse($i);

        $this->travelTo($timezone_now->copy()->startOfDay());

        if(now()->gte($timezone_now->copy()->startOfDay()) && now()->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertEquals(1, $i->count());

        $i = false;

        $this->travelTo($timezone_now->copy()->startOfDay()->addHours(2));

        if($timezone_now->gte($timezone_now->copy()->startOfDay()) && $timezone_now->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

            $i = Invoice::query()
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_proforma', 0)
                    ->whereNotNull('subscription_id')
                    ->where('balance', '>', 0)
                    ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                    ->get();

        }

        $this->assertFalse($i);



    }

    public function testAssignInvoice()
    {
        $i = Invoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);


        $s = Subscription::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,

        ]);

        $data = [
            'ids' => [$s->hashed_id],
            'entity' => 'invoice',
            'entity_id' => $i->hashed_id,
            'action' => 'assign_invoice'
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk', $data);

        $response->assertStatus(200);

        $i = $i->fresh();

        $this->assertEquals($s->id, $i->subscription_id);

    }

    public function testAssignRecurringInvoice()
    {
        $i = RecurringInvoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);


        $s = Subscription::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,

        ]);

        $data = [
            'ids' => [$s->hashed_id],
            'entity' => 'recurring_invoice',
            'entity_id' => $i->hashed_id,
            'action' => 'assign_invoice'
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk', $data);

        $response->assertStatus(200);

        $i = $i->fresh();

        $this->assertEquals($s->id, $i->subscription_id);

    }

    public function testSubscriptionFilter()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/subscriptions?filter=xx')
          ->assertStatus(200);
    }

    public function testSubscriptionsGet()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $billing_subscription = Subscription::factory()->create([
            'product_ids' => $product->id,
            'company_id' => $this->company->id,
            'name' => Str::random(5),
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/subscriptions/'.$this->encodePrimaryKey($billing_subscription->id));

        // nlog($response);

        $response->assertStatus(200);
    }

    public function testSubscriptionsPost()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/subscriptions', ['steps' => "cart,auth.login-or-register",'product_ids' => $product->hashed_id, 'allow_cancellation' => true, 'name' => Str::random(5)]);

        // nlog($response);
        $response->assertStatus(200);
    }

    public function testSubscriptionPut()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $response1 = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->token])
            ->post('/api/v1/subscriptions', ['steps' => "cart,auth.login-or-register",'product_ids' => $product->hashed_id, 'name' => Str::random(5)])
            ->assertStatus(200)
            ->json();

        // try {
        $response2 = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->token])
            ->put('/api/v1/subscriptions/'.$response1['data']['id'], ['steps' => "cart,auth.login-or-register",'allow_cancellation' => true])
            ->assertStatus(200)
            ->json();
        // }catch(ValidationException $e) {
        //    nlog($e->validator->getMessageBag());
        // }

        $this->assertNotEquals($response1['data']['allow_cancellation'], $response2['data']['allow_cancellation']);
    }

    public function testSubscriptionDeleted()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $billing_subscription = Subscription::factory()->create([
            'product_ids' => $product->id,
            'company_id' => $this->company->id,
            'name' => Str::random(5),
        ]);

        $response = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->token])
            ->delete('/api/v1/subscriptions/'.$this->encodePrimaryKey($billing_subscription->id))
            ->assertStatus(200)
            ->json();
    }
}
