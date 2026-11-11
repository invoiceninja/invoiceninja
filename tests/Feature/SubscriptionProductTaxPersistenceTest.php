<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\Tax\TaxModel;
use App\Models\Country;
use App\Models\Client;
use App\Services\Client\ClientService;
use App\Livewire\BillingPortal\Payments\Methods;
use Mockery;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Livewire\BillingPortalPurchasev2;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\RecurringInvoice;
use Livewire\Livewire;
use App\Livewire\BillingPortal\Summary;
use App\Models\InvoiceInvitation;
use App\Models\Product;
use App\Models\Subscription;
use App\Repositories\InvoiceRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Services\Subscription\ProductTaxes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockUnitData;
use Tests\TestCase;

class SubscriptionProductTaxPersistenceTest extends TestCase
{
    use DatabaseTransactions;
    use MockUnitData;

    private Subscription $subscription;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $this->company->calculate_taxes = false;
        $this->company->update_products = false;
        $this->company->track_inventory = false;
        $this->company->save();
        $this->client->settings = ClientSettings::defaults();
        $this->client->is_tax_exempt = false;
        $this->client->save();

        $this->product = Product::factory()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'product_key' => 'Subscription tax regression', 'notes' => '', 'price' => 100,
            'tax_name1' => 'GST', 'tax_rate1' => 10,
            'tax_name2' => 'Regional', 'tax_rate2' => 5,
            'tax_name3' => 'Local', 'tax_rate3' => 2,
        ]);
        $this->subscription = Subscription::factory()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'recurring_product_ids' => $this->product->hashed_id,
            'promo_code' => 'SAVE', 'promo_discount' => 0,
            'trial_enabled' => true, 'trial_duration' => 86400,
            'webhook_configuration' => [],
        ]);
    }

    private function bundle(string $version): array
    {
        if ($version === 'v2') {
            return [[
                'product_key' => $this->product->product_key, 'qty' => 2,
                'unit_cost' => $this->product->price, 'description' => '', 'is_recurring' => true,
                ...ProductTaxes::from($this->product),
            ]];
        }

        return [
            'recurring_products' => [$this->product->hashed_id => [
                'product' => array_merge($this->product->withoutRelations()->toArray(), ['is_recurring' => true]),
                'quantity' => 2,
            ]],
            'optional_recurring_products' => [], 'one_time_products' => [], 'optional_one_time_products' => [],
        ];
    }

    public static function purchaseCases(): iterable
    {
        foreach (['v2', 'v3'] as $version) {
            yield "$version exclusive" => [$version, false, false, 0, false, 234];
            yield "$version inclusive" => [$version, true, false, 0, false, 200];
            yield "$version exempt" => [$version, false, true, 0, false, 200];
            yield "$version percentage discount" => [$version, false, false, 10, false, 210.60];
            yield "$version fixed discount" => [$version, false, false, 20, true, 210.60];
            yield "$version full discount" => [$version, false, false, 100, false, 0];
        }
    }

    #[DataProvider('purchaseCases')]
    public function testPreviewMatchesPersistedInvoice(string $version, bool $inclusive, bool $exempt, float $discount, bool $amountDiscount, float $expected): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => $inclusive];
        $this->client->is_tax_exempt = $exempt;
        $this->client->save();
        $this->subscription->promo_discount = $discount;
        $this->subscription->is_amount_discount = $amountDiscount;
        $this->subscription->save();

        $bundle = $this->bundle($version);
        $calculator = $this->subscription->calc();
        $items = $version === 'v2' ? $calculator->buildV2Items($bundle) : $calculator->buildItems(['bundle' => $bundle]);
        $invoiceCount = Invoice::count();
        $invitationCount = InvoiceInvitation::count();
        $preview = $calculator->preview($items, $this->client, $discount > 0);
        $this->assertSame($invoiceCount, Invoice::count());
        $this->assertSame($invitationCount, InvoiceInvitation::count());

        $invoice = $version === 'v2'
            ? $this->subscription->service()->createInvoiceV2(collect($bundle), $this->client->id, $discount > 0)
            : $calculator->buildPurchaseInvoice([
                'bundle' => $bundle, 'client_id' => $this->client->hashed_id,
                'valid_coupon' => $discount > 0 ? 'SAVE' : null,
            ]);

        $invoice->refresh();
        $this->assertEqualsWithDelta($expected, $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($preview->getTotal(), $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($preview->getTotalTaxes(), $invoice->total_taxes, 0.001);
        $this->assertSame($inclusive, (bool) $invoice->uses_inclusive_taxes);
    }

    public static function renewalCases(): iterable
    {
        foreach (['v2', 'v3'] as $version) {
            foreach ([false, true] as $inclusive) {
                yield "$version " . ($inclusive ? 'inclusive' : 'exclusive') => [$version, $inclusive];
            }
        }
    }

    #[DataProvider('renewalCases')]
    public function testRenewalRetainsTaxesAndSettings(string $version, bool $inclusive): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => $inclusive];
        $this->client->save();
        $recurring = $this->subscription->service()->convertInvoiceToRecurringBundle(
            $this->client->id, json_decode(json_encode($this->bundle($version))),
        );
        $recurring = (new RecurringInvoiceRepository())->save([], $recurring);
        $recurring->refresh();
        $this->assertSame($inclusive, (bool) $recurring->uses_inclusive_taxes);
        $this->assertEquals(2, $recurring->line_items[0]->quantity);
        $this->assertEquals(ProductTaxes::from($this->product), ProductTaxes::from($recurring->line_items[0]));

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring, $this->client);
        $invoice = (new InvoiceRepository())->save([], $invoice);
        $this->assertEqualsWithDelta($inclusive ? 200 : 234, $invoice->fresh()->amount, 0.001);
    }

    public function testFreePurchaseRetainsInclusiveTaxesForRenewal(): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => true];
        $this->client->save();
        $this->subscription->promo_discount = 100;
        $this->subscription->save();
        $this->actingAs($this->primary_contact, 'contact');

        $component = new BillingPortalPurchasev2();
        $component->subscription_id = $this->subscription->id;
        $component->products = collect();
        $component->recurring_products = collect([$this->product]);
        $component->optional_products = collect();
        $component->optional_recurring_products = collect();
        $component->data = [['recurring_qty' => 2]];
        $component->valid_coupon = true;
        $component->handlePaymentNotRequired();

        $invoice = Invoice::where('subscription_id', $this->subscription->id)->firstOrFail();
        $this->assertEquals(0, $invoice->amount);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);
        $this->assertTrue((bool) $invoice->recurring_invoice->uses_inclusive_taxes);
        $this->assertEquals(10, $invoice->recurring_invoice->line_items[0]->tax_rate1);
    }

    public function testV3ResolvesProductTaxesBeforePaymentAndRetainsQuantity(): void
    {
        $bundle = $this->bundle('v3');
        $bundle['recurring_products'][$this->product->hashed_id]['product']['tax_rate1'] = 0;
        $resolved = $this->subscription->calc()->resolveBundle($bundle);

        $item = $resolved['recurring_products'][$this->product->hashed_id];
        $this->assertEquals(2, $item['quantity']);
        $this->assertEquals(ProductTaxes::from($this->product), ProductTaxes::from($item['product']));
    }


    public function testTrialPreservesProductTaxes(): void
    {
        $this->subscription->service()->startTrial([
            'contact_id' => $this->primary_contact->hashed_id,
            'client_id' => $this->client->hashed_id,
            'bundle' => collect($this->bundle('v2')),
        ]);

        $recurring = RecurringInvoice::where('subscription_id', $this->subscription->id)->firstOrFail();
        $this->assertEqualsWithDelta(234, $recurring->amount, 0.001);
        $this->assertEquals(ProductTaxes::from($this->product), ProductTaxes::from($recurring->line_items[0]));
    }

    #[DataProvider('renewalCases')]
    public function testPaymentCompletionUsesPurchasedTaxSnapshot(string $version, bool $inclusive): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => $inclusive];
        $this->client->save();
        $bundle = $this->bundle($version);
        $invoice = $this->subscription->service()->createInvoiceV2(collect($this->bundle('v2')), $this->client->id);
        $paymentHash = new PaymentHash();
        $paymentHash->fee_invoice_id = $invoice->id;
        $paymentHash->data = ['billing_context' => ['context' => 'purchase', 'bundle' => $bundle]];
        $paymentHash->setRelation('payment', (new Payment())->forceFill(['client_id' => $this->client->id]));
        $this->product->tax_rate1 = 20;
        $this->product->save();

        $this->subscription->service()->completePurchase($paymentHash);

        $recurring = $invoice->fresh()->recurring_invoice;
        $this->assertNotNull($recurring);
        $this->assertEquals(10, $recurring->line_items[0]->tax_rate1);
        $this->assertEqualsWithDelta($inclusive ? 200 : 234, $recurring->amount, 0.001);
    }

    public static function optionalRenewalTaxModes(): iterable
    {
        yield 'exclusive' => [false];
        yield 'inclusive' => [true];
    }

    #[DataProvider('optionalRenewalTaxModes')]
    public function testV3PaymentCompletionRetainsOptionalRecurringProducts(bool $inclusive): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => $inclusive];
        $this->client->save();
        $optional = $this->product->replicate();
        $optional->product_key = 'Optional recurring product';
        $optional->price = 50;
        $optional->save();
        $oneTime = Product::factory()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'product_key' => 'Setup product', 'notes' => '', 'price' => 25,
            'tax_name1' => '', 'tax_rate1' => 0, 'tax_name2' => '', 'tax_rate2' => 0,
            'tax_name3' => '', 'tax_rate3' => 0,
        ]);
        $this->subscription->optional_recurring_product_ids = $optional->hashed_id;
        $this->subscription->product_ids = $oneTime->hashed_id;
        $this->subscription->save();
        $bundle = $this->bundle('v3');
        $bundle['optional_recurring_products'][$optional->hashed_id] = [
            'product' => array_merge($optional->withoutRelations()->toArray(), ['is_recurring' => true]),
            'quantity' => 3,
        ];
        $bundle['one_time_products'][$oneTime->hashed_id] = [
            'product' => array_merge($oneTime->withoutRelations()->toArray(), ['is_recurring' => false]),
            'quantity' => 1,
        ];
        $invoice = $this->subscription->calc()->buildPurchaseInvoice([
            'bundle' => $bundle, 'client_id' => $this->client->hashed_id,
        ]);
        $this->assertEqualsWithDelta($inclusive ? 375 : 434.5, $invoice->amount, 0.001);
        $paymentHash = new PaymentHash();
        $paymentHash->fee_invoice_id = $invoice->id;
        $paymentHash->data = ['billing_context' => ['context' => 'purchase', 'bundle' => $bundle]];
        $paymentHash->setRelation('payment', (new Payment())->forceFill(['client_id' => $this->client->id]));
        $purchasedTaxes = ProductTaxes::from($optional);
        $optional->tax_rate1 = 20;
        $optional->save();

        $this->subscription->service()->completePurchase($paymentHash);

        $recurring = $invoice->fresh()->recurring_invoice;
        $this->assertNotNull($recurring);
        $this->assertSame($inclusive, (bool) $recurring->uses_inclusive_taxes);
        $lines = collect($recurring->line_items)->keyBy('product_key');
        $this->assertCount(2, $lines);
        $this->assertFalse($lines->has($oneTime->product_key));
        $this->assertEquals(2, $lines[$this->product->product_key]->quantity);
        $this->assertTrue($lines->has($optional->product_key));
        $this->assertEquals(3, $lines[$optional->product_key]->quantity);
        $this->assertEquals(50, $lines[$optional->product_key]->cost);
        $this->assertEquals($purchasedTaxes, ProductTaxes::from($lines[$optional->product_key]));
        $renewal = RecurringInvoiceToInvoiceFactory::create($recurring, $this->client);
        $renewal = (new InvoiceRepository())->save([], $renewal);
        $this->assertEqualsWithDelta($inclusive ? 350 : 409.5, $recurring->amount, 0.001);
        $this->assertEqualsWithDelta($recurring->amount, $renewal->fresh()->amount, 0.001);
    }

    public function testV2CheckoutRecalculatesAfterQuantityAndCouponChanges(): void
    {
        $this->subscription->promo_discount = 10;
        $this->subscription->is_amount_discount = false;
        $this->subscription->save();
        $this->actingAs($this->primary_contact, 'contact');

        Livewire::test(BillingPortalPurchasev2::class, [
            'subscription_id' => $this->subscription->id, 'db' => config('database.default'),
        ])
            ->set('data.0.recurring_qty', 2)
            ->assertSet('float_amount_total', 234)
            ->set('coupon', 'SAVE')->call('handleCoupon')
            ->assertSet('float_amount_total', 210.60)
            ->set('data.0.recurring_qty', 3)
            ->assertSet('float_amount_total', 315.90);
    }

    public function testV3SummaryRendersTaxedTotal(): void
    {
        $this->actingAs($this->primary_contact, 'contact');

        Livewire::test(Summary::class, [
            'subscription_id' => $this->subscription->hashed_id,
            'context' => ['bundle' => $this->bundle('v3')],
        ])->assertSee('234.00')->assertSee('34.00')->assertNotDispatched('purchase.context');
    }


    public function testV2LoginRecalculatesTheSelectedTaxedCart(): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => true];
        $this->client->save();
        $email = $this->primary_contact->email;
        \Illuminate\Support\Facades\Cache::put("subscriptions:otp:{$email}", '123456');
        $count = Invoice::count();

        Livewire::test(BillingPortalPurchasev2::class, [
            'subscription_id' => $this->subscription->id, 'db' => config('database.default'),
        ])
            ->set('data.0.recurring_qty', 2)
            ->assertSet('float_amount_total', 234)
            ->set('email', $email)
            ->call('handleLogin', '123456')
            ->assertSet('authenticated', true)
            ->assertSet('float_amount_total', 200);

        $this->assertSame($this->primary_contact->id, auth()->guard('contact')->id());
        $this->assertSame($count, Invoice::count());
    }

    public function testAutomaticTaxesAreNotAddedTwice(): void
    {
        $country = Country::where('iso_3166_2', 'AU')->firstOrFail();
        $settings = $this->company->settings;
        $settings->country_id = (string) $country->id;
        $this->company->settings = $settings;
        $taxData = new TaxModel();
        $taxData->regions->AU->tax_all_subregions = true;
        $taxData->regions->AU->has_sales_above_threshold = true;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->client->country_id = $country->id;
        $this->client->save();
        $this->client->unsetRelation('country');
        $this->client->setRelation('company', $this->company);
        $this->subscription->setRelation('company', $this->company);
        $bundle = $this->bundle('v3');
        $calculator = $this->subscription->calc();
        $preview = $calculator->preview($calculator->buildItems(['bundle' => $bundle]), $this->client);
        $invoice = $calculator->buildPurchaseInvoice(['bundle' => $bundle, 'client_id' => $this->client->hashed_id]);

        $this->assertEqualsWithDelta(220, $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($preview->getTotal(), $invoice->amount, 0.001);
    }

    public function testNoPaymentActionRejectsPaidPurchaseWithoutCreatingInvoice(): void
    {
        $this->actingAs($this->primary_contact, 'contact');
        $count = Invoice::count();

        Livewire::test(BillingPortalPurchasev2::class, [
            'subscription_id' => $this->subscription->id, 'db' => config('database.default'),
        ])
            ->call('handlePaymentNotRequired')->assertHasErrors('payment')
            ->set('data.0.recurring_qty', 0)->assertHasErrors('data.0.recurring_qty')
            ->call('handlePaymentNotRequired')->assertHasErrors('payment');

        $this->assertSame($count, Invoice::count());
    }

    public function testPaymentMethodsUseTaxedDiscountedTotal(): void
    {
        $this->subscription->promo_discount = 10;
        $this->subscription->is_amount_discount = false;
        $this->subscription->save();
        $service = Mockery::mock(ClientService::class);
        $service->shouldReceive('getPaymentMethods')->twice()->with(210.60)->andReturn([]);
        $service->shouldReceive('getPaymentMethods')->once()->with(234.0)->andReturn([['label' => 'Credit Card', 'is_paypal' => false]]);
        $client = Mockery::mock(Client::class)->makePartial();
        $client->setRawAttributes($this->client->getAttributes());
        $client->setRelation('company', $this->company);
        $client->setRelation('country', $this->client->country);
        $client->setRelation('group_settings', null);
        $client->shouldReceive('service')->andReturn($service);
        $this->primary_contact->setRelation('client', $client);
        $this->actingAs($this->primary_contact, 'contact');
        $component = new Methods();
        $component->subscription_id = $this->subscription->hashed_id;
        $component->context = ['bundle' => $this->bundle('v3'), 'valid_coupon' => 'SAVE'];
        $component->mount();

        $this->assertSame([], $component->methods);

        $v2 = new BillingPortalPurchasev2();
        $v2->subscription_id = $this->subscription->id;
        $v2->db = config('database.default');
        $v2->mount();
        $v2->data = [['recurring_qty' => 2]];
        $v2->buildBundle();
        $this->assertSame('Credit Card', $v2->methods[0]['label']);
        $v2->coupon = 'SAVE';
        $v2->handleCoupon();
        $this->assertSame([], $v2->methods);
    }

}
