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

namespace Tests\Feature\CompanyUser;

use App\Factory\CompanyUserFactory;
use App\Http\Middleware\PasswordProtection;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @see App\Http\Controllers\CompanyUserController
 * @see App\Repositories\UserRepository
 */
class CompanyUserAccountIdImmutabilityTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    private Account $foreign_account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->foreign_account = Account::factory()->create([
            'hosted_client_count' => 1000000,
            'hosted_company_count' => 1000000,
        ]);
    }

    public function testOwnerCannotChangeCompanyUserAccountIdViaCompanyUsersEndpoint(): void
    {
        $company_user = $this->membershipFor($this->user);
        $original_account_id = $company_user->account_id;

        $this->assertNotEquals($this->foreign_account->id, $original_account_id);

        $response = $this->putCompanyUser($this->user, [
            'company_user' => [
                'account_id' => $this->foreign_account->id,
                'settings' => [
                    'invoice' => 'unchanged-account',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $company_user->refresh();

        $this->assertSame(
            $original_account_id,
            $company_user->account_id,
            'company_user.account_id must not change via company_users update.',
        );
        $this->assertSame($this->account->id, $company_user->account_id);
    }

    public function testAdminCannotChangeCompanyUserAccountIdViaCompanyUsersEndpoint(): void
    {
        CompanyUser::query()
            ->whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update(['is_admin' => true, 'is_owner' => false]);

        $target_user = $this->createCompanyMember();
        $company_user = $this->membershipFor($target_user);
        $original_account_id = $company_user->account_id;

        $response = $this->putCompanyUser($target_user, [
            'company_user' => [
                'account_id' => $this->foreign_account->id,
                'permissions' => 'view_client',
            ],
        ]);

        $response->assertStatus(200);

        $company_user->refresh();

        $this->assertSame(
            $original_account_id,
            $company_user->account_id,
            'company_user.account_id must not change via company_users update.',
        );
    }

    public function testNonAdminCompanyUserUpdateIgnoresAccountIdInPayload(): void
    {
        CompanyUser::query()
            ->whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update([
                'is_admin' => false,
                'is_owner' => false,
            ]);

        $company_user = $this->membershipFor($this->user);
        $original_account_id = $company_user->account_id;

        $response = $this->putCompanyUser($this->user, [
            'company_user' => [
                'account_id' => $this->foreign_account->id,
                'settings' => [
                    'invoice' => 'self-service',
                ],
                'notifications' => [
                    'email' => ['invoice_sent_all'],
                ],
            ],
        ]);

        $response->assertStatus(200);

        $company_user->refresh();

        $this->assertSame($original_account_id, $company_user->account_id);
    }

    public function testOwnerCannotChangeCompanyUserAccountIdViaUserUpdateEndpoint(): void
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $target_user = $this->createCompanyMember();
        $company_user = $this->membershipFor($target_user);
        $original_account_id = $company_user->account_id;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/users/'.$this->encodePrimaryKey($target_user->id).'?include=company_user', [
            'first_name' => $target_user->first_name,
            'last_name' => $target_user->last_name,
            'email' => $target_user->email,
            'company_user' => [
                'account_id' => $this->foreign_account->id,
                'permissions' => 'view_client',
            ],
        ]);

        $response->assertStatus(200);

        $company_user->refresh();

        $this->assertSame(
            $original_account_id,
            $company_user->account_id,
            'company_user.account_id must not change via users update.',
        );
    }

    private function createCompanyMember(): User
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('member').'@gmail.com',
        ]);

        CompanyUserFactory::create($user->id, $this->company->id, $this->account->id)->save();

        return $user;
    }

    private function membershipFor(User $user): CompanyUser
    {
        return CompanyUser::query()
            ->whereUserId($user->id)
            ->whereCompanyId($this->company->id)
            ->firstOrFail();
    }

    private function putCompanyUser(User $user, array $payload)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($user->id), $payload);
    }
}
