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

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\User;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GoCardlessWebhookSignatureTest extends TestCase
{
    use DatabaseTransactions;

    private $faker;

    private $company;

    private $companyGateway;

    private $webhookSecret = 'test_webhook_secret_key_123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $this->company = Company::factory()->create(['account_id' => $account->id]);
        $this->company->settings = CompanySettings::defaults();
        $this->company->save();

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $user->id;
        $cg->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(json_encode([
            'accessToken' => 'fake_access_token',
            'webhookSecret' => $this->webhookSecret,
            'testMode' => true,
        ]));
        $cg->fees_and_limits = [];
        $cg->save();

        $this->companyGateway = $cg;
    }

    public function testRejectsWebhookWithNoSignatureHeader(): void
    {
        $payload = json_encode(['events' => [['id' => 'EV001', 'action' => 'confirmed']]]);

        $response = $this->call(
            'POST',
            route('payment_webhook', [
                'company_key' => $this->company->company_key,
                'company_gateway_id' => $this->companyGateway->hashed_id,
            ]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(403);
    }

    public function testRejectsWebhookWithInvalidSignature(): void
    {
        $payload = json_encode(['events' => [['id' => 'EV001', 'action' => 'confirmed']]]);

        $response = $this->call(
            'POST',
            route('payment_webhook', [
                'company_key' => $this->company->company_key,
                'company_gateway_id' => $this->companyGateway->hashed_id,
            ]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_SIGNATURE' => 'invalid_signature_value',
            ],
            $payload
        );

        $response->assertStatus(403);
    }

    public function testAcceptsWebhookWithValidSignature(): void
    {
        $payload = json_encode(['events' => [['id' => 'EV001', 'resource_type' => 'payments', 'action' => 'confirmed', 'links' => ['payment' => 'PM001']]]]);
        $validSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('payment_webhook', [
                'company_key' => $this->company->company_key,
                'company_gateway_id' => $this->companyGateway->hashed_id,
            ]),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_SIGNATURE' => $validSignature,
            ],
            $payload
        );

        // Should pass signature check — 200 means events were accepted
        $response->assertStatus(200);
    }
}
