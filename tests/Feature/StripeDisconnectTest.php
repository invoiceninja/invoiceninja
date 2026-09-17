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

use App\Factory\CompanyGatewayFactory;
use App\Http\Middleware\PasswordProtection;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

class StripeDisconnectTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';
    private const PAYPAL_GATEWAY_KEY = '3b6621f970ab18887c4f6dca78d3f8bb';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->withoutMiddleware(PasswordProtection::class);
    }

    public function testAdminCanDisconnectStripeGateway(): void
    {
        $company_gateway = $this->createCompanyGateway($this->company->id, $this->user->id, self::STRIPE_GATEWAY_KEY);

        $this->withApiHeaders()
            ->postJson("/api/v1/stripe/disconnect/{$company_gateway->hashed_id}")
            ->assertOk();
    }

    public function testNonAdminCannotDisconnectStripeGateway(): void
    {
        $company_gateway = $this->createCompanyGateway($this->company->id, $this->user->id, self::STRIPE_GATEWAY_KEY);
        $this->makeCurrentUserNonAdmin();

        $this->withApiHeaders()
            ->postJson("/api/v1/stripe/disconnect/{$company_gateway->hashed_id}")
            ->assertStatus(401);
    }

    public function testCannotDisconnectStripeGatewayFromAnotherCompany(): void
    {
        [, $otherCompany, $otherUser] = $this->createOtherTenant();
        $company_gateway = $this->createCompanyGateway($otherCompany->id, $otherUser->id, self::STRIPE_GATEWAY_KEY);

        $this->withApiHeaders()
            ->postJson("/api/v1/stripe/disconnect/{$company_gateway->hashed_id}")
            ->assertStatus(401);
    }

    public function testCannotDisconnectNonStripeGateway(): void
    {
        $company_gateway = $this->createCompanyGateway($this->company->id, $this->user->id, self::PAYPAL_GATEWAY_KEY);

        $this->withApiHeaders()
            ->postJson("/api/v1/stripe/disconnect/{$company_gateway->hashed_id}")
            ->assertStatus(401);
    }

    private function withApiHeaders(): self
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }

    private function createCompanyGateway(int $company_id, int $user_id, string $gateway_key): CompanyGateway
    {
        $company_gateway = CompanyGatewayFactory::create($company_id, $user_id);
        $company_gateway->gateway_key = $gateway_key;
        $company_gateway->save();

        return $company_gateway;
    }

    private function makeCurrentUserNonAdmin(): void
    {
        CompanyUser::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->update([
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => '[]',
            ]);
    }

    /**
     * @return array{0: Account, 1: Company, 2: User, 3: Client}
     */
    private function createOtherTenant(): array
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create(['account_id' => $account->id]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => Str::uuid().'@example.test',
        ]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return [$account, $company, $user, $client];
    }
}
