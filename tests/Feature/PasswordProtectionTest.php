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

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Middleware\PasswordProtection
 */
class PasswordProtectionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->user->oauth_provider_id = 'google';
        $this->user->oauth_user_id = 'password-protection-google-user';
        $this->user->password = Hash::make('ALongAndBriliantPassword');
        $this->user->has_password = true;
        $this->user->save();

        $this->company->oauth_password_required = true;
        $this->company->save();

        // Another user signed in with Google
        User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::uuid() . '@example.test',
            'oauth_provider_id' => 'google',
            'oauth_user_id' => 'password-protection-other-google-user',
        ]);

        Cache::forget($this->user->hashed_id . '_' . $this->user->account_id . '_logged_in');
    }

    private function mockGoogleToken(string $sub): void
    {
        // Verify the id token without contacting Google.
        $google = \Mockery::mock('overload:App\Libraries\OAuth\Providers\Google');
        $google->shouldReceive('getTokenResponse')->once()->with('password-protection-id-token')->andReturn([
            'email' => $this->user->email,
            'sub' => $sub,
        ]);
        $google->shouldReceive('harvestSubField')->andReturnUsing(fn (array $payload) => $payload['sub']);
    }

    private function getUser(string $password)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-OAUTH-PASSWORD' => 'password-protection-id-token',
            'X-API-PASSWORD-BASE64' => base64_encode($password),
        ])->getJson('/api/v1/users/' . $this->user->hashed_id);
    }

    public function testGoogleReauthWithTheUsersTokenAndPassword()
    {
        $this->mockGoogleToken('password-protection-google-user');

        $this->getUser('ALongAndBriliantPassword')->assertStatus(200);
    }

    public function testGoogleReauthWithAnotherGoogleUsersToken()
    {
        $this->mockGoogleToken('password-protection-other-google-user');

        $this->getUser('ALongAndBriliantPassword')->assertStatus(412);
    }

    public function testGoogleReauthWithAWrongPassword()
    {
        $this->mockGoogleToken('password-protection-google-user');

        $this->getUser('NotTheRightPassword')->assertStatus(412);
    }
}
