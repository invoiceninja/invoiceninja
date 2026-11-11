<?php

namespace Tests\Unit\BillingPortal;

use App\Factory\InvoiceFactory;
use App\DataMapper\CompanySettings;
use App\DataMapper\ClientSettings;
use App\Models\GroupSetting;
use App\Helpers\Invoice\InvoiceSum;
use App\Livewire\BillingPortal\Summary;
use App\Livewire\BillingPortalPurchasev2;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use App\Services\Subscription\SubscriptionCalculator;
use App\Services\Subscription\SubscriptionService;
use App\Utils\Number;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * Regression specifications: taxed purchases must agree with the default route.
 * Only persistence and model lookups are stubbed; line mapping, bundle building,
 * summary arithmetic and InvoiceSum are production code. No application DB is used.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SubscriptionProductTaxTest extends TestCase
{
    private Company $company;
    private Client $client;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);

        $currency = (new Currency())->forceFill([
            'id' => 1, 'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.',
            'symbol' => '$', 'code' => 'USD', 'swap_currency_symbol' => false,
        ]);
        $country = new Country();
        $this->app->instance('currencies', collect([$currency]));

        $this->company = Mockery::mock(Company::class)->makePartial();
        $this->company->forceFill(['id' => 1, 'calculate_taxes' => false, 'settings' => CompanySettings::defaults()]);
        $this->company->shouldReceive('currency')->andReturn($currency);
        $this->company->shouldReceive('country')->andReturn($country);
        $this->company->shouldReceive('getSetting')->with('show_currency_code')->andReturn(false);

        $this->client = Mockery::mock(Client::class)->makePartial();
        $this->client->forceFill(['id' => 1, 'is_tax_exempt' => false, 'settings' => ClientSettings::defaults()]);
        $this->client->setRelation('group_settings', null);
        $this->client->setRelation('company', $this->company);
        $this->client->setRelation('country', $country);
        $this->client->shouldReceive('currency')->andReturn($currency);

        $this->subscription = (new Subscription())->forceFill([
            'id' => 1, 'company_id' => 1, 'user_id' => 1, 'frequency_id' => 5,
            'promo_code' => '', 'promo_discount' => 0, 'is_amount_discount' => false,
        ]);
        $this->subscription->setRelation('company', $this->company);
    }

    private function product(bool $taxed = true): Product
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->forceFill([
            'id' => 1, 'company_id' => 1, 'product_key' => 'Taxed subscription',
            'notes' => 'Subscription product', 'price' => 100, 'tax_id' => '1',
            'tax_name1' => $taxed ? 'GST' : '', 'tax_rate1' => $taxed ? 10 : 0,
            'tax_name2' => $taxed ? 'Regional' : '', 'tax_rate2' => $taxed ? 5 : 0,
            'tax_name3' => $taxed ? 'Local' : '', 'tax_rate3' => $taxed ? 2 : 0,
        ]);
        $product->setRelation('tags', collect());
        $product->shouldReceive('markdownNotes')->andReturn('Subscription product');

        return $product;
    }

    private function v2Component(string $category = 'recurring_products', bool $taxed = true): BillingPortalPurchasev2
    {
        $component = Mockery::mock(BillingPortalPurchasev2::class)->makePartial();
        $component->shouldReceive('subscription')->andReturn($this->subscription);
        foreach (['products', 'recurring_products', 'optional_products', 'optional_recurring_products'] as $property) {
            $component->{$property} = collect();
        }
        $property = match ($category) {
            'one_time_products' => 'products',
            'optional_one_time_products' => 'optional_products',
            default => $category,
        };
        $component->{$property} = collect([$this->product($taxed)]);
        $quantityKey = match ($category) {
            'recurring_products' => 'recurring_qty',
            'optional_recurring_products' => 'optional_recurring_qty',
            'optional_one_time_products' => 'optional_qty',
            default => null,
        };
        $component->data = $quantityKey ? [[$quantityKey => 2]] : [];
        $component->buildBundle();

        return $component;
    }

    private function v3Bundle(string $category = 'recurring_products', bool $taxed = true): array
    {
        $bundle = array_fill_keys(['recurring_products', 'optional_recurring_products', 'one_time_products', 'optional_one_time_products'], []);
        $product = $this->product($taxed)->getAttributes();
        $product['is_recurring'] = in_array($category, ['recurring_products', 'optional_recurring_products']);
        $bundle[$category] = [['product' => $product, 'quantity' => 2]];

        return $bundle;
    }

    private function assertProductTaxes(Product $product, object|array $line): void
    {
        $expected = $actual = [];
        foreach (['tax_name1', 'tax_rate1', 'tax_name2', 'tax_rate2', 'tax_name3', 'tax_rate3'] as $field) {
            $expected[$field] = $product->{$field};
            $actual[$field] = ((array) $line)[$field] ?? null;
        }
        $this->assertEquals($expected, $actual, 'All three product tax names and rates must survive line construction.');
    }

    private function total(Invoice $invoice): float
    {
        $invoice->setRelation('client', $this->client);
        $invoice->setRelation('company', $this->company);

        return (new InvoiceSum($invoice))->build()->getTotal();
    }

    private function purchaseInvoice(string $version, string $category, bool $taxed = true): Invoice
    {
        // The service constructs its repository directly. Intercept only the save
        // boundary, leaving the actual invoice/line construction under test.
        $repository = Mockery::mock('overload:App\\Repositories\\InvoiceRepository');
        $repository->shouldReceive('save')->once()->andReturnUsing(fn ($data, $invoice) => $invoice);

        if ($version === 'v2') {
            return (new SubscriptionService($this->subscription))->createInvoiceV2(
                $this->v2Component($category, $taxed)->bundle, 1,
            );
        }

        return (new SubscriptionCalculator($this->subscription))->buildPurchaseInvoice([
            'client_id' => $this->client->hashed_id,
            'bundle' => $this->v3Bundle($category, $taxed),
        ]);
    }

    public static function categories(): iterable
    {
        foreach (['recurring_products', 'one_time_products', 'optional_recurring_products', 'optional_one_time_products'] as $category) {
            yield $category => [$category];
        }
    }

    public static function purchasePaths(): iterable
    {
        foreach (['v2', 'v3'] as $version) {
            foreach (self::categories() as $category => $_) {
                yield "$version $category" => [$version, $category];
            }
        }
    }

    #[DataProvider('categories')]
    public function testV2BundlePreservesProductTaxes(string $category): void
    {
        $this->assertProductTaxes($this->product(), $this->v2Component($category)->bundle->first());
    }

    #[DataProvider('purchasePaths')]
    public function testPurchaseInvoicePreservesProductTaxes(string $version, string $category): void
    {
        $invoice = $this->purchaseInvoice($version, $category);
        $this->assertCount(1, $invoice->line_items);
        $this->assertProductTaxes($this->product(), $invoice->line_items[0]);
    }

    public static function versions(): iterable
    {
        yield 'v2' => ['v2'];
        yield 'v3' => ['v3'];
    }

    #[DataProvider('versions')]
    public function testPurchaseTotalIncludesProductTaxes(string $version): void
    {
        // Two $100 units with 10%, 5%, and 2% exclusive taxes = $234.
        $this->assertEqualsWithDelta(234, $this->total($this->purchaseInvoice($version, 'recurring_products')), 0.001);
    }

    #[DataProvider('versions')]
    public function testUntaxedPurchaseIsUnchanged(string $version): void
    {
        $this->assertEqualsWithDelta(200, $this->total($this->purchaseInvoice($version, 'recurring_products', false)), 0.001);
    }

    public function testDefaultRoutePreservesTaxesAndCalculatesTaxedTotal(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $service = Mockery::mock(SubscriptionService::class);
        $subscription->shouldReceive('service')->andReturn($service);
        $service->shouldReceive('products')->andReturn(collect());
        $service->shouldReceive('recurring_products')->andReturn(collect([$this->product()]));
        $repository = new SubscriptionRepository();
        $repository->quantity = 2;
        $lines = $repository->generateLineItems($subscription);
        $this->assertProductTaxes($this->product(), $lines[0]);
        $invoice = InvoiceFactory::create(1, 1);
        $invoice->line_items = $lines;
        $this->assertEqualsWithDelta(234, $this->total($invoice), 0.001);
    }

    #[DataProvider('versions')]
    public function testCheckoutTotalIncludesProductTaxes(string $version): void
    {
        if ($version === 'v2') {
            $component = $this->v2Component();
            $this->assertEqualsWithDelta(234, $component->float_amount_total, 0.001);
            $actual = $component->total;
        } else {
            $component = Mockery::mock(Summary::class)->makePartial();
            $component->shouldReceive('subscription')->andReturn($this->subscription);
            $component->context = ['bundle' => $this->v3Bundle()];
            $actual = $component->total();
        }
        $this->assertSame(Number::formatMoney(234, $this->company), $actual);
    }

    #[DataProvider('versions')]
    public function testRenewalLinesPreserveProductTaxes(string $version): void
    {
        if ($version === 'v2') {
            // Supply taxes explicitly to isolate the downstream conversion defect
            // from the independent omission in V2 buildBundle().
            $bundle = [(object) array_merge($this->product()->getAttributes(), [
                'qty' => 2, 'unit_cost' => 100, 'description' => 'Subscription product', 'is_recurring' => true,
            ])];
        } else {
            // Payment completion receives the bundle after JSON serialization.
            $bundle = json_decode(json_encode($this->v3Bundle()));
        }
        $lines = (new SubscriptionRepository())->generateBundleLineItems($bundle, true);
        $this->assertCount(1, $lines);
        $this->assertEquals(2, $lines[0]['quantity']);
        $this->assertProductTaxes($this->product(), $lines[0]);
    }

    public static function optionalRenewalCases(): iterable
    {
        yield 'optional only' => [false, 2, false];
        yield 'required and optional' => [true, 3, false];
        yield 'unselected optional' => [true, 0, false];
        yield 'legacy bundle without optional field' => [true, 0, true];
    }

    #[DataProvider('optionalRenewalCases')]
    public function testV3RenewalIncludesSelectedOptionalRecurringProducts(bool $required, int $quantity, bool $legacy): void
    {
        $bundle = $this->v3Bundle();
        $bundle['recurring_products'] = $required ? $bundle['recurring_products'] : [];
        $optional = $this->v3Bundle('optional_recurring_products')['optional_recurring_products'][0];
        $optional['product']['product_key'] = 'Optional recurring product';
        $optional['quantity'] = $quantity;
        $bundle['optional_recurring_products'] = [$optional];
        if ($legacy) {
            unset($bundle['optional_recurring_products']);
        }
        $bundle['one_time_products'] = $this->v3Bundle('one_time_products')['one_time_products'];
        $bundle['optional_one_time_products'] = $this->v3Bundle('optional_one_time_products')['optional_one_time_products'];

        $lines = (new SubscriptionRepository())->generateBundleLineItems(json_decode(json_encode($bundle)), true);
        $expected = $required ? ['Taxed subscription' => 2] : [];
        if ($quantity > 0) {
            $expected['Optional recurring product'] = $quantity;
        }
        $this->assertEquals($expected, array_column($lines, 'quantity', 'product_key'));
        $this->assertCount(count($expected), $lines);
        foreach ($lines as $line) {
            $this->assertEquals(100, $line['cost']);
            $this->assertProductTaxes($this->product(), $line);
        }
    }

    public static function pricingCases(): iterable
    {
        yield 'exclusive' => [false, false, false, 0, 234];
        yield 'inclusive' => [true, false, false, 0, 200];
        yield 'exempt' => [false, true, false, 0, 200];
        yield 'percentage coupon' => [false, false, false, 10, 210.60];
        yield 'fixed coupon' => [false, false, true, 20, 210.60];
        yield 'inclusive percentage coupon' => [true, false, false, 10, 180];
        yield 'free purchase' => [false, false, false, 100, 0];
        yield 'over-discounted untaxed purchase' => [false, true, true, 220, -20];
    }

    #[DataProvider('pricingCases')]
    public function testPreviewUsesInvoiceTaxAndDiscountRules(bool $inclusive, bool $exempt, bool $amountDiscount, float $discount, float $expected): void
    {
        $this->client->settings = (object) ['inclusive_taxes' => $inclusive];
        $this->client->is_tax_exempt = $exempt;
        $this->subscription->promo_discount = $discount;
        $this->subscription->is_amount_discount = $amountDiscount;
        $calculator = $this->subscription->calc();
        $items = $calculator->buildItems(['bundle' => $this->v3Bundle()]);
        $before = json_encode($items);
        $preview = $calculator->preview($items, $this->client, $discount != 0);

        $this->assertEqualsWithDelta($expected, $preview->getTotal(), 0.001);
        $this->assertFalse($preview->getTempEntity()->exists);
        $this->assertSame($before, json_encode($items), 'Calculating a quote must not mutate its source product taxes.');
    }

    public function testGuestPreviewUsesSubscriptionGroupInclusiveTaxes(): void
    {
        $this->subscription->setRelation('group_settings', (new GroupSetting())->forceFill([
            'settings' => (object) ['inclusive_taxes' => true],
        ]));
        $calculator = $this->subscription->calc();
        $preview = $calculator->preview($calculator->buildItems(['bundle' => $this->v3Bundle()]));

        $this->assertEqualsWithDelta(200, $preview->getTotal(), 0.001);
        $this->assertGreaterThan(0, $preview->getTotalTaxes());
    }

    public function testLegacyRenewalBundleWithoutTaxesStillWorks(): void
    {
        $lines = (new SubscriptionRepository())->generateBundleLineItems([(object) [
            'product_key' => 'Legacy', 'qty' => 2, 'unit_cost' => 100,
            'description' => '', 'is_recurring' => true,
        ]], true);

        $this->assertCount(1, $lines);
        $this->assertProductTaxes($this->product(false), $lines[0]);
        $this->assertEquals(2, $lines[0]['quantity']);
    }

    public function testInvalidV3CouponCannotChangeInvoicePricing(): void
    {
        $this->subscription->promo_code = 'SAVE10';
        $calculator = $this->subscription->calc();
        $this->assertFalse($calculator->hasValidCoupon([]));
        $this->assertFalse($calculator->hasValidCoupon(['valid_coupon' => true]));
        $this->assertFalse($calculator->hasValidCoupon(['valid_coupon' => 'INVALID']));
        $this->assertTrue($calculator->hasValidCoupon(['valid_coupon' => 'SAVE10']));
    }

}
