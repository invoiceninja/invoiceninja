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
use App\Http\Middleware\ThrottleRequestsWithPredis;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SignupAttributionEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['db-ninja-01'];

    protected function setUp(): void
    {
        \Mockery::mock('overload:' . \Modules\Admin\Jobs\Account\NinjaUser::class)
            ->shouldReceive('handle')
            ->once()
            ->andReturnNull();

        parent::setUp();

        config(['database.default' => 'db-ninja-01']);

        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithPredis::class]);

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

    public function testClickIdsAreStoredAndUnknownKeysAreIgnored(): void
    {
        $email = $this->email();

        $this->postJson($this->signupUrl(), $this->payload($email, [
            'attribution' => [
                'utm_source' => 'facebook',
                'fbclid'     => 'abc123',
                'gclid'      => 'def456',
                'rc'         => 'friend',
                'ref'        => 'newsletter',
            ],
        ]))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame('facebook', $account->attribution['utm_source']);
        $this->assertSame('abc123', $account->attribution['fbclid']);
        $this->assertSame('def456', $account->attribution['gclid']);
        $this->assertMatchesRegularExpression(
            '/^fb\.1\.\d{13}\.abc123$/',
            $account->attribution['fbc']
        );
    }

    public function testOversizedAttributionDoesNotBlockSignup(): void
    {
        $email = $this->email();

        $attribution = ['utm_source' => str_repeat('a', 501)];

        for ($i = 0; $i < 25; $i++) {
            $attribution['utm_key'.$i] = 'value';
        }

        $this->postJson($this->signupUrl(), $this->payload($email, [
            'attribution' => $attribution,
        ]))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertCount(20, $account->attribution);
        $this->assertArrayNotHasKey('utm_source', $account->attribution);
    }

    public function testAttributionValuesUpToFiveHundredCharactersAreKept(): void
    {
        $email = $this->email();

        $this->postJson($this->signupUrl(), $this->payload($email, [
            'attribution' => [
                'utm_source' => str_repeat('a', 500),
                'lp'         => '/lp/ads/inventory/',
            ],
        ]))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame(str_repeat('a', 500), $account->attribution['utm_source']);
        $this->assertSame('/lp/ads/inventory/', $account->attribution['lp']);
        $this->assertNotNull($account->first_touch_at);
        $this->assertSame('127.0.0.1', $account->signup_ip);
    }

    public function testOversizedLegacyUtmFieldsDoNotBlockSignup(): void
    {
        $email = $this->email();

        $this->postJson($this->signupUrl(), $this->payload($email, [
            'utm_source' => str_repeat('a', 500),
            'utm_medium' => ['nested'],
        ]))->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame(str_repeat('a', 191), $account->utm_source);
        $this->assertNull($account->utm_medium);
    }

    public function testGoogleSignupStoresAttribution(): void
    {
        $email = $this->email();

        $google = \Mockery::mock('overload:App\Libraries\OAuth\Providers\Google');
        $google->shouldReceive('getTokenResponse')->andReturn(['email' => $email]);
        $google->shouldReceive('harvestEmail')->andReturn($email);
        $google->shouldReceive('harvestSubField')->andReturn(Str::random(20));
        $google->shouldReceive('harvestName')->andReturn('Attribution Test');

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->postJson('/api/v1/oauth_login?provider=google&id_token=token&create=true', [
                'attribution' => ['gclid' => 'def456', 'rc' => 'friend'],
            ])
            ->assertSuccessful();

        $account = User::query()->where('email', $email)->firstOrFail()->account;

        $this->assertSame(['gclid' => 'def456'], $account->attribution);
        $this->assertSame('Mozilla/5.0', $account->user_agent);
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
