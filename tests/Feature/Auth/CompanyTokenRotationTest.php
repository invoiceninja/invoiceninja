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

namespace Tests\Feature\Auth;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyTokenRotationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function test_stale_system_token_rotates_on_successful_login(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $response = $this->login($context['user']->email);

        $response->assertStatus(200);

        $token->refresh();

        $this->assertNotSame($old_token, $token->token);
        $this->assertStringContainsString($token->token, $response->getContent());
        $this->assertStringNotContainsString($old_token, $response->getContent());
    }

    public function test_fresh_system_token_does_not_rotate_on_successful_login(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(10));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->assertSame($old_token, $token->token);
    }

    public function test_non_system_token_does_not_rotate_on_successful_login(): void
    {
        $context = $this->createLoginContext();
        $system_token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(10));
        $manual_token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31), false);
        $old_manual_token = $manual_token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $system_token->refresh();
        $manual_token->refresh();

        $this->assertSame($old_manual_token, $manual_token->token);
        $this->assertTrue($manual_token->created_at <= now()->subDays(31)->timestamp);
    }

    public function test_rotation_updates_created_at_to_now(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));

        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->assertSame(now()->timestamp, $token->created_at);
    }

    public function test_old_token_no_longer_authenticates_after_rotation(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_new_token_authenticates_after_rotation(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->withHeaders([
            'X-API-TOKEN' => $token->token,
        ])->getJson('/api/v1/statics')->assertStatus(200);
    }

    public function test_due_system_tokens_rotate_across_user_companies(): void
    {
        $context = $this->createLoginContext();
        $first_token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $second_company = Company::factory()->create([
            'account_id' => $context['account']->id,
        ]);

        $context['user']->companies()->attach($second_company->id, [
            'account_id' => $context['account']->id,
            'is_owner' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'is_admin' => 1,
        ]);

        $second_token = $this->createCompanyToken($context['user'], $second_company, now()->subDays(31));
        $old_first_token = $first_token->token;
        $old_second_token = $second_token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $first_token->refresh();
        $second_token->refresh();

        $this->assertNotSame($old_first_token, $first_token->token);
        $this->assertNotSame($old_second_token, $second_token->token);
    }

    /**
     * @return array{account: Account, user: User, company: Company}
     */
    private function createLoginContext(): array
    {
        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'token-rotation-' . Str::uuid() . '@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'is_admin' => 1,
        ]);

        return [
            'account' => $account,
            'user' => $user,
            'company' => $company,
        ];
    }

    private function createCompanyToken(User $user, Company $company, Carbon $created_at, bool $is_system = true): CompanyToken
    {
        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $user->account_id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = $is_system;
        $company_token->save();

        $company_token->created_at = $created_at;
        $company_token->updated_at = $created_at;
        $company_token->save();

        return $company_token->fresh();
    }

    private function login(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-React' => 'true',
        ])->postJson('/api/v1/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }
}
