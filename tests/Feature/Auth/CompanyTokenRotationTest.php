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
use App\Libraries\MultiDB;
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

        config(['ninja.company_token_grace_days' => 7]);
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

    public function test_old_token_no_longer_authenticates_after_rotation_without_grace_period(): void
    {
        config(['ninja.company_token_grace_days' => 0]);

        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_rotation_keeps_previous_token_for_grace_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));

        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->assertSame($old_token, $token->previous_token);
        $this->assertTrue(Carbon::parse($token->previous_token_expires_at)->equalTo(now()->addDays(7)));
    }

    public function test_rotation_without_grace_period_keeps_no_previous_token(): void
    {
        config(['ninja.company_token_grace_days' => 0]);

        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->assertNull($token->previous_token);
        $this->assertNull($token->previous_token_expires_at);
    }

    public function test_old_token_authenticates_during_grace_period(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/clients')->assertStatus(200);
    }

    public function test_refresh_with_old_token_returns_new_token(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $response = $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->postJson('/api/v1/refresh');

        $response->assertStatus(200);

        $this->assertSame($token->token, $response->json('data.0.token.token'));
        $this->assertStringNotContainsString($old_token, $response->getContent());
    }

    public function test_old_token_no_longer_authenticates_after_grace_period(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_old_token_of_deleted_token_does_not_authenticate(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();
        $token->delete();

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_previous_token_of_non_system_token_does_not_authenticate(): void
    {
        config(['ninja.environment' => 'selfhost']);

        $context = $this->createLoginContext();
        $manual_token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(1), false);
        $previous_token = Str::random(64);

        CompanyToken::query()->whereKey($manual_token->id)->update([
            'previous_token' => $previous_token,
            'previous_token_expires_at' => now()->addDays(7),
        ]);

        $this->withHeaders([
            'X-API-TOKEN' => $manual_token->token,
        ])->getJson('/api/v1/statics')->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $previous_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_previous_token_is_not_serialized(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->assertSame($old_token, $token->previous_token);
        $this->assertArrayNotHasKey('previous_token', $token->toArray());
        $this->assertStringNotContainsString($old_token, json_encode($token->withoutRelations()));
    }

    public function test_grace_period_disabled_rejects_old_token_already_in_grace(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        config(['ninja.company_token_grace_days' => 0]);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_logout_ends_old_token_grace_period(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $this->withHeaders([
            'X-API-TOKEN' => $token->token,
        ])->postJson('/api/v1/logout')->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_logout_with_old_token_during_grace_period(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();
        $new_token = $token->token;

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->postJson('/api/v1/logout')->assertStatus(200);

        $this->withHeaders([
            'X-API-TOKEN' => $old_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);

        $this->withHeaders([
            'X-API-TOKEN' => $new_token,
        ])->getJson('/api/v1/statics')->assertStatus(403);
    }

    public function test_old_token_found_across_databases_during_grace_period(): void
    {
        $context = $this->createLoginContext();
        $token = $this->createCompanyToken($context['user'], $context['company'], now()->subDays(31));
        $old_token = $token->token;

        $this->login($context['user']->email)->assertStatus(200);

        $token->refresh();

        $databases = MultiDB::$dbs;
        $default = config('database.default');

        try {
            MultiDB::$dbs = [$default];

            $this->assertTrue(MultiDB::findAndSetDb($old_token));
            $this->assertSame($token->id, MultiDB::getCompanyToken($old_token)?->id);

            $this->travel(8)->days();

            $this->assertFalse(MultiDB::findAndSetDb($old_token));
            $this->assertNull(MultiDB::getCompanyToken($old_token));
        } finally {
            MultiDB::$dbs = $databases;
            MultiDB::setDb($default);
        }
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
            'email' => 'token-rotation-' . Str::uuid() . '@gmail.com',
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
