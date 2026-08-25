<?php

namespace Tests\Integration\PaymentDrivers;

use App\Models\CompanyGateway;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Tests\TestCase;

class NinjaPlanStripeAuthorizationTest extends TestCase
{
    public function test_stripe_test_mode_card_can_be_authorized_and_released_without_capture(): void
    {
        if (! config('services.stripe.run_trial_integration_tests')) {
            $this->markTestSkipped(
                'Set RUN_STRIPE_TRIAL_INTEGRATION_TESTS=true to run against Stripe test mode.'
            );
        }

        $companyId = (int) config('ninja.ninja_default_company_id');
        $companyGatewayId = (int) config('ninja.ninja_default_company_gateway_id');

        if ($companyId <= 0 || $companyGatewayId <= 0) {
            $this->markTestSkipped('A hosted Stripe test gateway is not configured.');
        }

        $companyGateway = CompanyGateway::on('db-ninja-01')
            ->where('company_id', $companyId)
            ->find($companyGatewayId);

        if (! $companyGateway) {
            $this->markTestSkipped('The configured hosted Stripe test gateway does not exist.');
        }

        $secret = (string) $companyGateway->getConfigField('apiKey');

        if (! str_starts_with($secret, 'sk_test_')) {
            $this->markTestSkipped('Stripe trial integration tests require a test-mode secret key.');
        }

        $stripe = new StripeClient($secret);

        try {
            $stripe->balance->retrieve();
        } catch (ApiErrorException) {
            $this->markTestSkipped('The configured Stripe test-mode credentials are not valid.');
        }

        $customer = $stripe->customers->create([
            'description' => 'Invoice Ninja trial authorization integration test',
        ]);

        try {
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => 100,
                'currency' => 'usd',
                'capture_method' => 'manual',
                'customer' => $customer->id,
                'payment_method' => 'pm_card_visa',
                'payment_method_types' => ['card'],
                'confirm' => true,
            ]);

            $this->assertSame(PaymentIntent::STATUS_REQUIRES_CAPTURE, $paymentIntent->status);
            $this->assertSame(100, $paymentIntent->amount_capturable);
            $this->assertSame(0, $paymentIntent->amount_received);

            $paymentIntent = $stripe->paymentIntents->cancel($paymentIntent->id, [
                'cancellation_reason' => 'requested_by_customer',
            ]);

            $this->assertSame(PaymentIntent::STATUS_CANCELED, $paymentIntent->status);
            $this->assertSame(0, $paymentIntent->amount_received);
        } finally {
            $stripe->customers->delete($customer->id, []);
        }
    }
}
