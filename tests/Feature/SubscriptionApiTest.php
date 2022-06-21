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

use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\SubscriptionController
 */
class SubscriptionApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
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
        ])->post('/api/v1/subscriptions', ['product_ids' => $product->id, 'allow_cancellation' => true, 'name' => Str::random(5)]);

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
            ->post('/api/v1/subscriptions', ['product_ids' => $product->id, 'name' => Str::random(5)])
            ->assertStatus(200)
            ->json();

        // try {
        $response2 = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->token])
            ->put('/api/v1/subscriptions/'.$response1['data']['id'], ['allow_cancellation' => true])
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
