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

namespace Tests\Feature\ClientPortal;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\ClientGatewayTokenFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\GatewayType;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private $faker;

    private $account;

    private $user;

    private $company;

    private $client;

    private $contact;

    private $otherClient;

    private $otherContact;

    private $companyGateway;

    private $cgt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $this->company = Company::factory()->create(['account_id' => $this->account->id]);
        $this->company->settings = CompanySettings::defaults();
        $this->company->save();

        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $settings = ClientSettings::defaults();
        $settings->language_id = '1';
        $this->client->settings = $settings;
        $this->client->save();

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $otherSettings = ClientSettings::defaults();
        $otherSettings->language_id = '1';
        $this->otherClient->settings = $otherSettings;
        $this->otherClient->save();

        $this->otherContact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->otherClient->id,
            'company_id' => $this->company->id,
        ]);

        $cg = new \App\Models\CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt('{}');
        $cg->fees_and_limits = [];
        $cg->save();
        $this->companyGateway = $cg;

        $cgt = ClientGatewayTokenFactory::create($this->company->id);
        $cgt->client_id = $this->client->id;
        $cgt->token = 'test_token';
        $cgt->gateway_customer_reference = 'cus_test';
        $cgt->company_gateway_id = $this->companyGateway->id;
        $cgt->gateway_type_id = GatewayType::CREDIT_CARD;
        $cgt->save();

        $this->cgt = $cgt;
    }

    public function testShowPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->get(route('client.payment_methods.show', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function testShowPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $response = $this->get(route('client.payment_methods.show', $this->cgt->hashed_id));

        $response->assertStatus(403);
    }

    public function testDestroyPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->withoutExceptionHandling()
            ->delete(route('client.payment_methods.destroy', $this->cgt->hashed_id));
    }

    public function testDestroyPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->delete(route('client.payment_methods.destroy', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function testVerifyPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $response = $this->get(route('client.payment_methods.verification', $this->cgt->hashed_id));

        $response->assertStatus(403);
    }

    public function testVerifyPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->get(route('client.payment_methods.verification', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
