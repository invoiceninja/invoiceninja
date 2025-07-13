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

use App\Models\Invoice;
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'])
            ->assertJsonPath('errors.product_id.0', 'The product id field is required.');
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'])
            ->assertJsonPath('errors.product_id.0', 'The selected product id is invalid.');
    }
    public function testMissingEquipmentWhenRequired()
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_id'])
            ->assertJsonPath('errors.equipment_id.0', 'Required by product configuration.');
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['until'])
            ->assertJsonPath('errors.until.0', 'Has to be after from.');
    }
    public function testInvalidInvoiceAggregationKey()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
        ]);

        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'quantity' => 1,
            'invoice_aggregation_key' => 'invoice-product-mapper'
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_aggregation_key'])
            ->assertJsonPath('errors.invoice_aggregation_key.0', 'The selected invoice aggregation key is invalid.');
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonPath('errors.quantity.0', '0 not allowed in quantity based allocation.');
    }
    public function testValidQuantityBasedAllocationWithGrouping()
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from'])
            ->assertJsonPath('errors.from.0', 'Required for time based allocations.');
    }
    public function testValidTimeBasedAllocationWithLifeCycle()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED,
            'unit_of_measure' => 'H',
        ]);

        // Create Entry with only from (incomplete)
        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'from' => now()->toISOString(),
        ];
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)
            ->assertJsonPath('data.from', $data['from'])
            ->assertJsonPath('data.until', null)
            ->assertJsonPath('data.quantity', 0);

        // Now add the until (complete entry)
        $data['until'] = now()->addHours(2)->toISOString();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])
            ->putJson('/api/v1/product_allocations/' . $response->json('data.id'), [
                'until' => $data['until'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $response->json('data.id'))
            ->assertJsonPath('data.from', $data['from'])
            ->assertJsonPath('data.until', $data['until'])
            ->assertJsonPath('data.quantity', function ($value) {
                return $value !== 0;
            });
    }
    public function testValidTimeBasedAllocationCompleted()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED,
            'unit_of_measure' => 'H',
        ]);

        // Create already completed
        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'from' => now()->toISOString(),
            'until' => now()->addHours(2)->toISOString(),
        ];
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', function ($value) {
                return $value !== 0;
            });
    }

    // INVOICE INTEGRATION
    public function testValidCreationAssignedToInvoice()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_DRAFT,
            'line_items' => [],
        ]);
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
        ]);
        $data = [
            'company_id' => $this->company->hashed_id,
            'product_id' => $this->encodePrimaryKey($product->id),
            'invoice_id' => $invoice->hashed_id,
            'quantity' => 2,
        ];
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', function ($value) {
                return $value !== 0;
            });

        $invoice = Invoice::find($invoice->id);
        nlog($invoice);
    }
    public function testValidInvoiceCreationWithAutomaticProductAllocationMapperEntry()
    {
    }
    // RECURRING INTEGRATION
    public function testValidAutoBillOfRecurringWithOutstandingProductAllocations()
    {
    }
    // SUBSCRIPTION INTEGRATION
    public function testValidAutoBillOfSubscriptionWithOutstandingProductAllocations()
    {
    }
    // PROJECT INTEGRATION
    public function testValidProjectInvoiceGenerationWithOutstandingProductAllocations()
    {
    }
}
