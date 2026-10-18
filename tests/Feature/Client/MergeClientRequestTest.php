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

namespace Tests\Feature\Client;

use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

class MergeClientRequestTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testAdminCanMergeClientsInTheirCompany(): void
    {
        $source = $this->createClientForUser($this->user);

        $response = $this->withHeaders($this->apiHeaders($this->token))
            ->postJson("/api/v1/clients/{$this->client->hashed_id}/{$source->hashed_id}/merge");

        $response->assertOk();
        $this->assertNull(Client::withTrashed()->find($source->id));
        $this->assertNotNull(Client::withTrashed()->find($this->client->id));
    }

    public function testNonAdminIsForbiddenEvenWhenTheyOwnTheClient(): void
    {
        [$staff, $staffToken] = $this->createStaffUser();
        $target = $this->createClientForUser($staff);
        $source = $this->createClientForUser($staff);

        $response = $this->withHeaders($this->apiHeaders($staffToken))
            ->postJson("/api/v1/clients/{$target->hashed_id}/{$source->hashed_id}/merge");

        $response->assertStatus(401)
            ->assertJson(['message' => 'This action is unauthorized.']);
        $this->assertNotNull(Client::withTrashed()->find($target->id));
        $this->assertNotNull(Client::withTrashed()->find($source->id));
    }

    public function testAdminCannotMergeAClientFromAnotherCompany(): void
    {
        $foreignClient = $this->createForeignClient();
        $source = $this->createClientForUser($this->user);

        $response = $this->withHeaders($this->apiHeaders($this->token))
            ->postJson("/api/v1/clients/{$foreignClient->hashed_id}/{$source->hashed_id}/merge");

        $response->assertStatus(401)
            ->assertJson(['message' => 'This action is unauthorized.']);
        $this->assertNotNull(Client::withTrashed()->find($foreignClient->id));
        $this->assertNotNull(Client::withTrashed()->find($source->id));
    }

    /** @return array<string, string> */
    private function apiHeaders(string $token): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
            'X-API-PASSWORD' => config('ninja.testvars.password'),
        ];
    }

    private function createClientForUser(User $user): Client
    {
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => true,
        ]);

        return $client;
    }

    /** @return array{0: User, 1: string} */
    private function createStaffUser(): array
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('staff') . '@gmail.com',
            'password' => Hash::make(config('ninja.testvars.password')),
        ]);

        $companyUser = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $companyUser->is_owner = false;
        $companyUser->is_admin = false;
        $companyUser->is_locked = false;
        $companyUser->save();

        $token = Str::random(64);
        $companyToken = new CompanyToken();
        $companyToken->user_id = $user->id;
        $companyToken->company_id = $this->company->id;
        $companyToken->account_id = $this->account->id;
        $companyToken->name = 'staff test token';
        $companyToken->token = $token;
        $companyToken->is_system = true;
        $companyToken->save();

        return [$user, $token];
    }

    private function createForeignClient(): Client
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('foreign') . '@gmail.com',
        ]);

        return Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }
}
