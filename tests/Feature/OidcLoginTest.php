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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\Auth\LoginController
 *  App\Libraries\OAuth\Providers\Oidc\Provider
 */
class OidcLoginTest extends TestCase
{
    use DatabaseTransactions;

    private string $well_known = 'https://sso.example.com/application/o/invoiceninja/.well-known/openid-configuration';

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        Cache::flush();
    }

    private function configureOidc(): void
    {
        Config::set('services.oidc.well_known', $this->well_known);
        Config::set('services.oidc.client_id', 'invoiceninja-test-client');
        Config::set('services.oidc.client_secret', 'shhh');
        Config::set('services.oidc.redirect', 'https://app.example.com/auth/oidc');
        Config::set('services.oidc.scopes', 'openid profile email');
        Config::set('services.oidc.provider_label', 'Authentik');
    }

    /**
     * Seed the discovery cache so the provider never performs a live
     * HTTP round-trip to the identity provider during the test.
     */
    private function seedDiscoveryCache(): void
    {
        Cache::put('oidc.discovery.' . sha1($this->well_known), [
            'issuer' => 'https://sso.example.com/application/o/invoiceninja/',
            'authorization_endpoint' => 'https://sso.example.com/application/o/authorize/',
            'token_endpoint' => 'https://sso.example.com/application/o/token/',
            'userinfo_endpoint' => 'https://sso.example.com/application/o/userinfo/',
            'jwks_uri' => 'https://sso.example.com/application/o/invoiceninja/jwks/',
        ], now()->addHour());
    }

    public function testOidcConfigReportsDisabledWhenUnconfigured()
    {
        Config::set('services.oidc.well_known', '');
        Config::set('services.oidc.client_id', '');

        $response = $this->get('/api/v1/oidc/config');

        $response->assertStatus(200);
        $this->assertFalse($response->json('oidc_enabled'));
    }

    public function testOidcConfigReportsEnabledWithLabel()
    {
        $this->configureOidc();

        $response = $this->get('/api/v1/oidc/config');

        $response->assertStatus(200);
        $this->assertTrue($response->json('oidc_enabled'));
        $this->assertSame('Authentik', $response->json('oidc_provider_label'));
    }

    public function testRedirectAbortsWhenOidcIsNotConfigured()
    {
        Config::set('services.oidc.well_known', '');

        $response = $this->get('/auth/oidc');

        $response->assertStatus(404);
    }

    public function testRedirectSendsUserToDiscoveredAuthorizationEndpointWithPkce()
    {
        $this->configureOidc();
        $this->seedDiscoveryCache();

        $response = $this->get('/auth/oidc');

        $response->assertStatus(302);

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://sso.example.com/application/o/authorize/', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('invoiceninja-test-client', $query['client_id']);
        $this->assertSame('https://app.example.com/auth/oidc', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertNotEmpty($query['state']);
        $this->assertStringContainsString('openid', $query['scope']);
    }

    public function testProviderErrorResponseDoesNotBounceBackToTheIdp()
    {
        $this->configureOidc();
        $this->seedDiscoveryCache();

        $response = $this->get('/auth/oidc?error=access_denied');

        $response->assertStatus(400);
    }

    public function testExchangeRejectsMalformedCode()
    {
        $response = $this->postJson('/api/v1/oidc/exchange', ['code' => 'too-short']);

        $response->assertStatus(400);
    }

    public function testExchangeRejectsUnknownCode()
    {
        $response = $this->postJson('/api/v1/oidc/exchange', ['code' => Str::random(64)]);

        $response->assertStatus(400);
    }

    public function testExchangeReturnsTokenExactlyOnce()
    {
        $code = Str::random(64);

        Cache::put('oidc.exchange.' . $code, 'company-token-value', now()->addSeconds(60));

        $response = $this->postJson('/api/v1/oidc/exchange', ['code' => $code]);

        $response->assertStatus(200);
        $this->assertSame('company-token-value', $response->json('token'));

        $replay = $this->postJson('/api/v1/oidc/exchange', ['code' => $code]);

        $replay->assertStatus(400);
    }

    public function testCallbackWithInvalidStateIsRejected()
    {
        $this->configureOidc();
        $this->seedDiscoveryCache();

        $response = $this->get('/auth/oidc?code=fake-authorization-code&state=not-the-session-state');

        $response->assertStatus(400);
    }
}
