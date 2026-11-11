<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentWebhook;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Support\Testing\Fakes\BusFake;
use Mockery;
use Modules\Admin\Jobs\Stripe\CampaignCharge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PaymentIntentPromoWebhookTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);

        parent::tearDown();
    }

    #[DataProvider('paymentIntents')]
    public function testPromoDispatchUsesTheCorrectPayload(array $intent, ?array $expectedCharge, string $environment = 'hosted'): void
    {
        if (!class_exists(CampaignCharge::class)) {
            $this->markTestSkipped('The Admin module is required for campaign dispatch.');
        }

        Container::getInstance()->instance('config', new \Illuminate\Config\Repository(['ninja' => ['environment' => $environment]]));

        // Exercise the real handler and CampaignCharge constructor without a database
        // or running the campaign job's account provisioning and accounting effects.
        Mockery::mock('alias:' . MultiDB::class)
            ->shouldReceive('findAndSetDbByCompanyKey')->once()->with('promo-company')->andReturn(true);

        $companyQuery = Mockery::mock();
        $companyQuery->shouldReceive('where')->with('company_key', 'promo-company')->andReturnSelf();
        $companyQuery->shouldReceive('firstOrFail')->andReturn((object) ['id' => 123]);
        Mockery::mock('alias:' . Company::class)->shouldReceive('query')->andReturn($companyQuery);

        $paymentQuery = Mockery::mock();
        $paymentQuery->shouldReceive('where')->with('company_id', 123)->andReturnSelf();
        $paymentQuery->shouldReceive('whereIn')->andReturnSelf();
        $paymentQuery->shouldReceive('first')->andReturnNull();
        Mockery::mock('alias:' . Payment::class)->shouldReceive('query')->andReturn($paymentQuery);

        // Stop at the unrelated gateway-processing stage after the promo loop.
        $gatewayQuery = Mockery::mock();
        $gatewayQuery->shouldReceive('find')->with(null)->andReturnNull();
        Mockery::mock('alias:' . CompanyGateway::class)->shouldReceive('query')->andReturn($gatewayQuery);

        $bus = new BusFake(Mockery::mock(QueueingDispatcher::class));
        Container::getInstance()->instance(Dispatcher::class, $bus);

        (new PaymentIntentWebhook(['object' => $intent], 'promo-company', null))->handle();

        if ($expectedCharge === null) {
            $bus->assertNothingDispatched();

            return;
        }

        $bus->assertDispatched(CampaignCharge::class, 1);
        $job = $bus->dispatched(CampaignCharge::class)->sole();
        $this->assertSame($expectedCharge, $job->charge);
        $this->assertSame(5, $job->delay);
    }

    public static function paymentIntents(): array
    {
        $metadata = [
            'product' => 'annual_promo',
            'customer_email' => 'promo@example.test',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
        ];
        $charge = [
            'id' => 'ch_promo',
            'amount' => 6000,
            'metadata' => $metadata,
        ];
        $legacy = [
            'id' => 'pi_legacy',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'metadata' => [],
            'charges' => ['data' => [$charge]],
        ];
        $basil = [
            'id' => 'pi_basil',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'latest_charge' => 'ch_promo',
            // Use the received amount, even when the requested amount differs.
            'amount' => 6500,
            'amount_received' => 6000,
            'metadata' => $metadata,
        ];

        return [
            'non-hosted promo does not dispatch' => [$basil, null, 'selfhost'],
            'legacy embedded charge' => [$legacy, $charge],
            'Basil without charges' => [$basil, $charge],
            'empty legacy charges falls back to intent' => [$basil + ['charges' => ['data' => []]], $charge],
            'legacy charge metadata takes precedence' => [array_replace($legacy, [
                'metadata' => ['product' => 'different_product'],
                'latest_charge' => 'ch_different',
                'amount_received' => 9999,
            ]), $charge],
            'legacy without promo' => [array_replace_recursive($legacy, [
                'charges' => ['data' => [['metadata' => ['product' => '']]]],
            ]), null],
            'Basil without promo' => [array_replace($basil, ['metadata' => []]), null],
            'Basil without metadata' => [array_diff_key($basil, ['metadata' => true]), null],
        ];
    }
}
