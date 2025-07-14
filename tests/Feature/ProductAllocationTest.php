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

use App\DataMapper\CompanySettings;
use App\DataMapper\InvoiceItem;
use App\Jobs\ProductAllocation\UpdateOrCreateProductAllocation;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Factory\RecurringInvoiceFactory;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Str;
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
        // TODO: 401 problem
        // $data['until'] = now()->addHours(2)->toISOString();
        // $this->withHeaders([
        //     'X-API-SECRET' => config('ninja.api_secret'),
        //     'X-API-TOKEN' => $this->token,
        // ])
        //     ->putJson('/api/v1/product_allocations/' . $response->json('data.id'), [
        //         'until' => $data['until'],
        //     ])
        //     ->assertStatus(200)
        //     ->assertJsonPath('data.id', $response->json('data.id'))
        //     ->assertJsonPath('data.from', $data['from'])
        //     ->assertJsonPath('data.until', $data['until'])
        //     ->assertJsonPath('data.quantity', function ($value) {
        //         return $value !== 0;
        //     });
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
    public function testInvalidInvoiceNotDraft()
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
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
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_id'])
            ->assertJsonPath('errors.invoice_id.0', 'The selected invoice id is invalid.');
    }
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
        $response1 = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200);

        $invoice = Invoice::find($invoice->id);
        $this->assertEquals(count($invoice->line_items), 1);
        $this->assertEquals($invoice->line_items[0]->quantity, $data['quantity']);
        $this->assertEquals($invoice->line_items[0]->product_key, $product->product_key);
        $this->assertEquals(count($invoice->line_items[0]->product_allocation_ids), 1);
        $this->assertEquals($invoice->line_items[0]->product_allocation_ids, [$response1->json('data.id')]);

        // Append another product_allocation
        $response2 = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/product_allocations', $data)
            ->assertStatus(200);

        $invoice = Invoice::find($invoice->id);
        $this->assertEquals(count($invoice->line_items), 1);
        $this->assertEquals($invoice->line_items[0]->quantity, $data['quantity'] * 2);
        $this->assertEquals($invoice->line_items[0]->product_key, $product->product_key);
        $this->assertEquals(count($invoice->line_items[0]->product_allocation_ids), 2);
        $this->assertEquals($invoice->line_items[0]->product_allocation_ids, [$response1->json('data.id'), $response2->json('data.id')]);

        UpdateOrCreateProductAllocation::dispatchSync($invoice->line_items, $invoice, $invoice->company); // should be ignored => alternativeSave is not executed in tests, because of db transaction => we therefore simulate the call to the job to sync the invoice
        $productAllocations = ProductAllocation::where('company_id', $this->company->id)
            ->where('invoice_id', $invoice->id)
            ->where('product_id', $product->id)
            ->where('invoice_aggregation_key', 'invoice-product-mapper')
            ->get();
        $this->assertEquals(count($productAllocations), 0);
    }
    public function testValidInvoiceCreationWithAutomaticProductAllocationMapperEntry()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
            'product_key' => Str::random(6),
        ]);

        $ii = new InvoiceItem();
        $ii->cost = 100;
        $ii->quantity = 5;
        $ii->product_key = $product->product_key;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_DRAFT,
            'line_items' => [$ii],
        ]);

        UpdateOrCreateProductAllocation::dispatchSync($invoice->line_items, $invoice, $invoice->company); // should be ignored => alternativeSave is not executed in tests, because of db transaction => we therefore simulate the call to the job to sync the invoice
        $productAllocations = ProductAllocation::where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->where('invoice_id', $invoice->id)
            ->where('client_id', $this->client->id)
            ->where('product_id', $product->id)
            ->where('invoice_aggregation_key', 'invoice-product-mapper')
            ->get();
        $this->assertEquals(count($productAllocations), 1);
    }
    // public function testValidInvoiceCreationWithAutomaticProductAllocationMapperEntry()
    // {
    //     $product = Product::factory()->create([
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
    //         'product_key' => Str::random(6),
    //     ]);

    //     $line_items = [];

    //     $item = InvoiceItemFactory::create();
    //     $item->quantity = 5;
    //     $item->cost = 100;
    //     $item->product_key = $product->product_key;
    //     $item->product_allocation_ids = '[]';

    //     $line_items[] = (array) $item;

    //     $invoice = [
    //         'status_id' => 1,
    //         'number' => '',
    //         'discount' => 0,
    //         'is_amount_discount' => 1,
    //         'po_number' => '3434343',
    //         'public_notes' => 'notes',
    //         'is_deleted' => 0,
    //         'partial' => 6000,
    //         'custom_value1' => 0,
    //         'custom_value2' => 0,
    //         'custom_value3' => 0,
    //         'custom_value4' => 0,
    //         'client_id' => $this->client->hashed_id,
    //         'line_items' => (array) $line_items,
    //     ];

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->post('/api/v1/invoices/', $invoice)
    //         ->assertStatus(200);

    //     $invoice = $response->json('data');

    //     $productAllocations = ProductAllocation::where('company_id', $this->company->id)
    //         ->where('user_id', $this->user->id)
    //         ->where('invoice_id', $invoice['id'])
    //         ->where('client_id', $this->client->id)
    //         ->where('product_id', $product->id)
    //         ->where('invoice_aggregation_key', 'invoice-product-mapper')
    //         ->get();
    //     $this->assertEquals(count($productAllocations), 1);
    // }
    // RECURRING INTEGRATION
    public function testValidAutoBillOfRecurringWithOutstandingProductAllocations()
    {
        $settings = CompanySettings::defaults();
        $settings->timezone_id = '15'; // New York

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
            'product_key' => Str::random(6),
            'cost' => 100,
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $client->id;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '1';
        $recurring_invoice->next_send_date = now();
        $recurring_invoice->save();
        $recurring_invoice = $recurring_invoice->calc()->getInvoice();

        $productAllocation = ProductAllocation::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'recurring_id' => $recurring_invoice->id,
            'client_id' => $client->id,
            'quantity' => 5,
            'should_be_invoiced' => true,
        ]);

        $recurring_invoice->service()->sendNow();
        $invoice = $recurring_invoice->invoices()->latest()->first();

        $productAllocation = $productAllocation->fresh();
        $this->assertEquals($productAllocation->invoice_id, $invoice->id);
    }
    // PROJECT INTEGRATION
    public function testValidProjectInvoiceGenerationWithOutstandingProductAllocations()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'allocation_type' => Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED,
            'product_key' => Str::random(6),
            'cost' => 100,
        ]);

        $project = Project::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $productAllocation = ProductAllocation::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'project_id' => $project->id,
            'client_id' => $this->client->id,
            'quantity' => 5,
            'should_be_invoiced' => true,
        ]);

        $data = [
            'action' => 'invoice',
            'ids' => [$project->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/projects/bulk", $data);

        $invoice = $response->json('data');
        $this->assertEquals(count($invoice['line_items']), 1);
        $this->assertEquals($invoice['line_items'][0]['quantity'], $productAllocation->quantity);
        $this->assertEquals($invoice['line_items'][0]['product_key'], $product->product_key);
        $this->assertEquals(count($invoice['line_items'][0]['product_allocation_ids']), 1);
        $this->assertEquals($invoice['line_items'][0]['product_allocation_ids'], [$productAllocation->hashed_id]);

        $productAllocation = $productAllocation->fresh();
        $this->assertEquals($productAllocation->invoice_id, null); // should not be updated, because its an invoice draft
    }
}
