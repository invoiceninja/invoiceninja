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

namespace Tests\Feature\PaymentDrivers\CheckoutCom;

use App\Factory\ClientGatewayTokenFactory;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ResolveGatewayCustomerReferenceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $companyGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->companyGateway = new CompanyGateway();
        $this->companyGateway->company_id = $this->company->id;
        $this->companyGateway->user_id = $this->user->id;
        $this->companyGateway->gateway_key = '3758e7f7c6f4cecf0f4f348b9a00f456';
        $this->companyGateway->require_cvv = true;
        $this->companyGateway->require_billing_address = true;
        $this->companyGateway->require_shipping_address = true;
        $this->companyGateway->update_details = true;
        $this->companyGateway->config = encrypt(json_encode([
            'secretApiKey' => 'sk_test',
            'publicApiKey' => 'pk_test',
            'testMode' => true,
        ]));
        $this->companyGateway->fees_and_limits = [];
        $this->companyGateway->save();
    }

    public function testReturnsLatestReferenceIgnoringDefaultFlag(): void
    {
        $this->createToken('cus_old', isDefault: true, createdAt: now()->subDay());
        $this->createToken('cus_new', isDefault: false, createdAt: now());

        $driver = new CheckoutComPaymentDriver($this->companyGateway, $this->client);

        $this->assertSame('cus_new', $driver->resolveGatewayCustomerReference());
    }

    public function testReturnsNullWhenNoReferenceExists(): void
    {
        $this->createToken('', isDefault: true, createdAt: now());

        $driver = new CheckoutComPaymentDriver($this->companyGateway, $this->client);

        $this->assertNull($driver->resolveGatewayCustomerReference());
    }

    public function testIgnoresTokensFromOtherCompanyGateways(): void
    {
        $otherGateway = $this->companyGateway->replicate();
        $otherGateway->save();

        $this->createToken('cus_other', isDefault: true, createdAt: now(), companyGatewayId: $otherGateway->id);
        $this->createToken('cus_mine', isDefault: false, createdAt: now()->subMinute());

        $driver = new CheckoutComPaymentDriver($this->companyGateway, $this->client);

        $this->assertSame('cus_mine', $driver->resolveGatewayCustomerReference());
    }

    private function createToken(
        string $reference,
        bool $isDefault,
        $createdAt,
        ?int $companyGatewayId = null
    ): void {
        $token = ClientGatewayTokenFactory::create($this->company->id);
        $token->client_id = $this->client->id;
        $token->token = 'src_' . uniqid();
        $token->gateway_customer_reference = $reference === '' ? null : $reference;
        $token->company_gateway_id = $companyGatewayId ?? $this->companyGateway->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->is_default = $isDefault;
        $token->created_at = $createdAt;
        $token->save();
    }
}
