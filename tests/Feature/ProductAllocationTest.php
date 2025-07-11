<?php
/**
 * ProductAllocation Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. ProductAllocation Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 *
 *  App\Http\Controllers\ProductAllocationController
 */
class ProductAllocationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    // GENERAL TESTING
    public function testMissingProductId()
    {
        $data = [
            'company_id' => $this->company->hashed_id,
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(422);
        // ->assertJson(["message" => "Missing product_id."]); // overwritten by request required
    }
    public function testNoProductAllocation()
    {
        $product = Product::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'allocation_type' => null]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Allocation not allowed by product configuration."]);
    }
    public function testMissingSerialNumberWhenRequired()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
            'allocation_equipment_required' => true,
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Missing serial_number."]);
    }
    public function testInvalidFromAfterUntil()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED,
            'unit_of_measure' => 'H',
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'from' => now()->addDays(1)->toISOString(),
            'until' => now()->toISOString(),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Invalid from/until."]);
    }

    // QUANTITY BASED ALLOCATION
    public function testInvalidQuantityBasedAllocationQuantityZero()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'quantity' => 0,
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Invalid quantity. 0 not allowed."]);
    }
    public function testValidQuantityBasedAllocationSucceedsAndTestGrouping()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
            'allocation_aggregation_interval' => 'daily',
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'quantity' => 1,
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', 1);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', 2);
    }

    // TIME BASED ALLOCATIONS
    public function testInvalidTimeBasedAllocationFromDateMissing()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED,
            'unit_of_measure' => 'H',
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'until' => now()->addHours(2)->toISOString(),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Invalid from/until. From required for time based allocations."]);
    }
    public function testInvalidTimeBasedAllocationDuplicateSerialNumberCheck()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED,
            'allocation_equipment_required' => true,
        ]);

        // First allocation
        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'quantity' => 1,
            'serial_number' => 'ABC123',
            'from' => now()->toISOString(),
            'until' => now()->addHours(2)->toISOString(),
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)->assertStatus(200);

        // Duplicate serial allocation
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(400)
            ->assertJson(["message" => "Duplicate detected."]);
    }
}
