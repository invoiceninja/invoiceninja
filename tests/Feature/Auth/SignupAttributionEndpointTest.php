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

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignupAttributionEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Bus::fake();

        config(['ninja.environment' => 'hosted']);
    }

    public function testAttributionPayloadIsStoredOnTheAccount(): void
    {
        $email = $this->email();

        $this->postJson($this->signupUrl(), $this->payload($email, [
            'attribution' => [
                'utm_source'   => 'facebook',
                'utm_medium'   => 'cpc',
                'utm_campaign' => 'january_launch',
            ],
        ]))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame([
            'utm_source'   => 'facebook',
            'utm_medium'   => 'cpc',
            'utm_campaign' => 'january_launch',
        ], $account->attribution);

        $this->assertSame('facebook', $account->utm_source);
        $this->assertSame('cpc', $account->utm_medium);
        $this->assertSame('january_launch', $account->utm_campaign);
    }

    public function testAttributionCookieIsUsedWhenThePayloadHasNone(): void
    {
        $email = $this->email();

        $this->withCredentials()->withUnencryptedCookie('ninja_attribution', json_encode([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
        ]));

        $this->postJson($this->signupUrl(), $this->payload($email))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
        ], $account->attribution);

        $this->assertSame('google', $account->utm_source);
    }

    private function signupUrl(): string
    {
        return '/api/v1/signup?include=token,user.company_user,company,account';
    }

    private function email(): string
    {
        return 'signup-'.Str::lower(Str::random(12)).'@gmail.com';
    }

    private function payload(string $email, array $overrides = []): array
    {
        return array_merge([
            'email'                 => $email,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'terms_of_service'      => true,
            'privacy_policy'        => true,
            'name'                  => 'Attribution Test',
        ], $overrides);
    }
}
