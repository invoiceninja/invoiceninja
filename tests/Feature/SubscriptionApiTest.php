<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Product;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

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
            'product_id' => $product->id,
            'company_id' => $this->company->id,
        ]);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/subscriptions/' . $this->encodePrimaryKey($billing_subscription->id));
        
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
        ])->post('/api/v1/subscriptions', ['product_id' => $product->id, 'allow_cancellation' => true]);

        $response->assertStatus(200);
    }

    public function testSubscriptionPut()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $response1 = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'),'X-API-TOKEN' => $this->token])
            ->post('/api/v1/subscriptions', ['product_id' => $product->id])
            ->assertStatus(200)
            ->json();

        $response2 = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'),'X-API-TOKEN' => $this->token])
            ->put('/api/v1/subscriptions/' . $response1['data']['id'], ['allow_cancellation' => true])
            ->assertStatus(200)
            ->json();

        $this->assertNotEquals($response1['data']['allow_cancellation'], $response2['data']['allow_cancellation']);
    }

    /*
    TypeError : Argument 1 passed to App\Transformers\SubscriptionTransformer::transform() must be an instance of App\Models\Subscription, bool given, called in /var/www/html/vendor/league/fractal/src/Scope.php on line 407
    /var/www/html/app/Transformers/SubscriptionTransformer.php:35
    /var/www/html/vendor/league/fractal/src/Scope.php:407
    /var/www/html/vendor/league/fractal/src/Scope.php:349
    /var/www/html/vendor/league/fractal/src/Scope.php:235
    /var/www/html/app/Http/Controllers/BaseController.php:395
    /var/www/html/app/Http/Controllers/SubscriptionController.php:408
    */
    public function testSubscriptionDeleted()
    {

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $billing_subscription = Subscription::factory()->create([
            'product_id' => $product->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this
            ->withHeaders(['X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->token])
            ->delete('/api/v1/subscriptions/' . $this->encodePrimaryKey($billing_subscription->id))
            ->assertStatus(200)
            ->json();

    }
}
