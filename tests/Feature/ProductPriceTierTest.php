<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Factory\InvoiceItemFactory;
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Tests for quantity based product price tiers.
 *
 * @covers \App\Services\Product\ApplyPriceTiers
 * @covers \App\Models\Product::priceTierForQuantity
 */
class ProductPriceTierTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
        $this->withoutExceptionHandling();

        /* Price tiers are gated by a company flag which defaults to false */
        $this->company->enable_price_tiers = true;
        $this->company->save();
    }

    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    private function makeTieredProduct(): Product
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_key' => 'TIERED-PROD',
            'price' => 10,
            'cost' => 10,
            'quantity' => 1,
            'price_tiers' => [
                ['min_quantity' => 10, 'price' => 9],
                ['min_quantity' => 50, 'discount' => 20],
            ],
        ]);

        return $product;
    }

    private function postInvoice(array $item_overrides = []): array
    {
        $item = InvoiceItemFactory::create();
        $item->product_key = 'TIERED-PROD';
        $item->type_id = '1';
        $item->cost = 10;
        $item->quantity = 1;

        foreach ($item_overrides as $key => $value) {
            $item->{$key} = $value;
        }

        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/invoices', [
            'client_id' => $this->client->hashed_id,
            'line_items' => [$item],
        ]);

        $response->assertStatus(200);

        return $response->json('data');
    }

    public function testStoreProductWithPriceTiers()
    {
        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/products', [
            'product_key' => 'TIERED',
            'price' => 4,
            'price_tiers' => [
                ['min_quantity' => 200, 'price' => 3.2],
                ['min_quantity' => 50, 'discount' => 10],
            ],
        ])->assertStatus(200);

        $tiers = $response->json('data.price_tiers');

        /* Tiers are normalized and stored sorted by min_quantity */
        $this->assertEquals(50, $tiers[0]['min_quantity']);
        $this->assertEquals(200, $tiers[1]['min_quantity']);
    }

    public function testPriceTierValidationRequiresPriceOrDiscount()
    {
        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/products', [
            'product_key' => 'TIERED-BAD',
            'price' => 4,
            'price_tiers' => [
                ['min_quantity' => 50],
            ],
        ])->assertStatus(422);
    }

    public function testPriceTierAppliedToInvoiceLineCost()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 20]);

        /* quantity 20 hits the {"min_quantity": 10, "price": 9} tier */
        $this->assertEquals(9, (float) $data['line_items'][0]['cost']);
    }

    public function testDiscountTierAppliedToInvoiceLineDiscount()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 60]);

        /* quantity 60 hits the {"min_quantity": 50, "discount": 20} tier */
        $this->assertEquals(20, (float) $data['line_items'][0]['discount']);
        $this->assertFalse((bool) $data['line_items'][0]['is_amount_discount']);
        $this->assertEquals(10, (float) $data['line_items'][0]['cost']);
    }

    public function testNoTierBelowThreshold()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 5]);

        $this->assertEquals(10, (float) $data['line_items'][0]['cost']);
        $this->assertEquals(0, (float) ($data['line_items'][0]['discount'] ?? 0));
    }

    public function testManualLineDiscountIsNotOverwritten()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 60, 'discount' => 33]);

        $this->assertEquals(33, (float) $data['line_items'][0]['discount']);
    }

    public function testLineItemCanOptOutOfPriceTiers()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 20, 'use_price_tiers' => false]);

        $this->assertEquals(10, (float) $data['line_items'][0]['cost']);
    }

    public function testProductBasePriceNotOverwrittenByTieredCost()
    {
        $product = $this->makeTieredProduct();

        $this->postInvoice(['quantity' => 20]);

        /* UpdateOrCreateProduct must not sync the tiered line cost (9)
         * back onto the product base price (10) */
        $this->assertEquals(10, (float) $product->fresh()->price);
    }

    public function testPriceTierForQuantityModelMethod()
    {
        $product = $this->makeTieredProduct();

        $this->assertNull($product->priceTierForQuantity(5));
        $this->assertEquals(9, $product->priceTierForQuantity(10)['price']);
        $this->assertEquals(9, $product->priceTierForQuantity(49)['price']);
        $this->assertEquals(20, $product->priceTierForQuantity(50)['discount']);
        $this->assertEquals(20, $product->priceTierForQuantity(5000)['discount']);
    }

    public function testPriceTiersModeValidation()
    {
        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/products', [
            'product_key' => 'TIERED-MODE',
            'price' => 4,
            'price_tiers_mode' => 'banana',
        ])->assertStatus(422);

        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/products', [
            'product_key' => 'TIERED-MODE',
            'price' => 4,
            'price_tiers_mode' => 'progressive',
        ])->assertStatus(200);

        $this->assertEquals('progressive', $response->json('data.price_tiers_mode'));
    }

    public function testProgressiveModeAppliesBlendedUnitCost()
    {
        Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_key' => 'TIERED-PROD',
            'price' => 10,
            'price_tiers_mode' => 'progressive',
            'price_tiers' => [
                ['min_quantity' => 10, 'price' => 9],
                ['min_quantity' => 50, 'price' => 8],
            ],
        ]);

        $data = $this->postInvoice(['quantity' => 100]);

        /* units 1-9 @10 + units 10-49 @9 + units 50-100 @8 = 858 -> 8.58 per unit */
        $this->assertEquals(8.58, (float) $data['line_items'][0]['cost']);

        /* quantity below the second tier only fills the first bracket */
        $data = $this->postInvoice(['quantity' => 20]);
        $this->assertEquals(9.45, (float) $data['line_items'][0]['cost']);

        /* quantity below the first tier keeps the base price */
        $data = $this->postInvoice(['quantity' => 5]);
        $this->assertEquals(10, (float) $data['line_items'][0]['cost']);
    }

    public function testProgressiveModeKeepsManualDiscount()
    {
        Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'product_key' => 'TIERED-PROD',
            'price' => 10,
            'price_tiers_mode' => 'progressive',
            'price_tiers' => [
                ['min_quantity' => 10, 'discount' => 10],
            ],
        ]);

        $data = $this->postInvoice(['quantity' => 20, 'discount' => 33]);

        /* blended cost: units 1-9 @10 + units 10-20 @9 = 189 -> 9.45 per unit,
         * the manual 33% discount stays untouched */
        $this->assertEquals(9.45, (float) $data['line_items'][0]['cost']);
        $this->assertEquals(33, (float) $data['line_items'][0]['discount']);
    }

    public function testTierApplicationWritesNoteOnLineItem()
    {
        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 20]);

        $this->assertStringContainsString('Quantity pricing: 10+', (string) $data['line_items'][0]['notes']);
    }

    public function testPriceTiersSkippedWhenCompanyFlagDisabled()
    {
        $this->company->enable_price_tiers = false;
        $this->company->save();

        $this->makeTieredProduct();

        $data = $this->postInvoice(['quantity' => 20]);

        $this->assertEquals(10, (float) $data['line_items'][0]['cost']);
    }
}
