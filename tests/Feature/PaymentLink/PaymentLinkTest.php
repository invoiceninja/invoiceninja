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

namespace Tests\Feature\PaymentLink;

use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockUnitData;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use App\Helpers\Invoice\ProRata;
use App\Models\RecurringInvoice;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Subscription\SubscriptionCalculator;

/**
 * 
 */
class PaymentLinkTest extends TestCase
{
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCalcUpgradePrice()
    {
        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'price' => 10,
        ]);

        $target = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'price' => 20,
        ]);

        $recurring_invoice = RecurringInvoice::factory()->create([
            'line_items' => $this->buildLineItems(),
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'discount' => 0,
            'subscription_id' => $subscription->id,
            'date' => now()->subWeeks(2),
            'next_send_date_client' => now(),
        ]);


        $invoice = Invoice::factory()->create([
            'line_items' => $this->buildLineItems(),
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'discount' => 0,
            'subscription_id' => $subscription->id,
            'date' => now()->subWeeks(2),
            'recurring_id' => $recurring_invoice->id,
        ]);

        $recurring_invoice = $recurring_invoice->calc()->getInvoice();
        $invoice = $invoice->calc()->getInvoice();
        $this->assertEquals(10, $invoice->amount);
        $invoice->service()->markSent()->save();
        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(10, $invoice->balance);
        $invoice = $invoice->service()->markPaid()->save();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(10, $invoice->paid_to_date);

        $status = $recurring_invoice
                    ->subscription
                    ->status($recurring_invoice);

        $this->assertFalse($status->is_trial);
        $this->assertFalse($status->is_refundable);
        $this->assertTrue($status->is_in_good_standing);

        $days = $recurring_invoice->subscription->service()->getDaysInFrequency();

        $ratio = 1 - (14 / $days);

        $this->assertEquals($ratio, $status->getProRataRatio());

        $price = $target->link_service()->calculateUpgradePriceV2($recurring_invoice, $target);

        $refund = round($invoice->paid_to_date * $ratio, 2);

        $this->assertEquals(($target->price - $refund), $price);

    }

    // public function testProrataDiscountRatioPercentage()
    // {
    //     $subscription = Subscription::factory()->create([
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'price' => 100,
    //     ]);

    //     $item = InvoiceItemFactory::create();
    //     $item->quantity = 1;

    //     $item->cost = 100;
    //     $item->product_key = 'xyz';
    //     $item->notes = 'test';
    //     $item->custom_value1 = 'x';
    //     $item->custom_value2 = 'x';
    //     $item->custom_value3 = 'x';
    //     $item->custom_value4 = 'x';

    //     $line_items[] = $item;

    //     $invoice = Invoice::factory()->create([
    //         'line_items' => $line_items,
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'client_id' => $this->client->id,
    //         'tax_rate1' => 0,
    //         'tax_name1' => '',
    //         'tax_rate2' => 0,
    //         'tax_name2' => '',
    //         'tax_rate3' => 0,
    //         'tax_name3' => '',
    //         'discount' => 0,
    //         'subscription_id' => $subscription->id,
    //         'date' => '2021-01-01',
    //         'discount' => 10,
    //         'is_amount_discount' => false,
    //         'status_id' => 1,
    //     ]);

    //     $invoice = $invoice->calc()->getInvoice();
    //     $this->assertEquals(90, $invoice->amount);
    //     $this->assertEquals(0, $invoice->balance);

    //     $invoice->service()->markSent()->save();

    //     $this->assertEquals(90, $invoice->amount);
    //     $this->assertEquals(90, $invoice->balance);


    //     $ratio = $subscription->service()->calculateDiscountRatio($invoice);

    //     $this->assertEquals(.1, $ratio);
    // }

    // public function testProrataDiscountRatioAmount()
    // {
    //     $subscription = Subscription::factory()->create([
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'price' => 100,
    //     ]);

    //     $item = InvoiceItemFactory::create();
    //     $item->quantity = 1;

    //     $item->cost = 100;
    //     $item->product_key = 'xyz';
    //     $item->notes = 'test';
    //     $item->custom_value1 = 'x';
    //     $item->custom_value2 = 'x';
    //     $item->custom_value3 = 'x';
    //     $item->custom_value4 = 'x';

    //     $line_items[] = $item;

    //     $invoice = Invoice::factory()->create([
    //         'line_items' => $line_items,
    //         'company_id' => $this->company->id,
    //         'user_id' => $this->user->id,
    //         'client_id' => $this->client->id,
    //         'tax_rate1' => 0,
    //         'tax_name1' => '',
    //         'tax_rate2' => 0,
    //         'tax_name2' => '',
    //         'tax_rate3' => 0,
    //         'tax_name3' => '',
    //         'discount' => 0,
    //         'subscription_id' => $subscription->id,
    //         'date' => '2021-01-01',
    //         'discount' => 20,
    //         'is_amount_discount' => true,
    //         'status_id' => 1,
    //     ]);

    //     $invoice = $invoice->calc()->getInvoice();
    //     $this->assertEquals(80, $invoice->amount);
    //     $this->assertEquals(0, $invoice->balance);

    //     $invoice->service()->markSent()->save();

    //     $this->assertEquals(80, $invoice->amount);
    //     $this->assertEquals(80, $invoice->balance);


    //     $ratio = $subscription->service()->calculateDiscountRatio($invoice);

    //     $this->assertEquals(.2, $ratio);
    // }
}
