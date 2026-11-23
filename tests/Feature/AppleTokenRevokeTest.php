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

use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Company;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Libraries\OAuth\OAuth::revokeAppleToken
 */
class AppleTokenRevokeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private string $public_key = '';

    private string $apple_private_key = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        // Deleting a hosted account dispatches a job from the hosted-only Admin module
        config(['ninja.environment' => 'selfhost']);

        // A P-256 key, as Apple issues. private_key_bits is ignored for EC keys, but some OpenSSL configs refuse a missing one
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
        openssl_pkey_export($key, $private_key);
        $this->public_key = openssl_pkey_get_details($key)['key'];

        config([
            'services.apple.client_id' => 'com.invoiceninja.client',
            'services.apple.client_ids' => ['com.invoiceninja.client', 'com.invoiceninja.admin'],
            'services.apple.revoke.team_id' => 'APPLETEAM1',
            'services.apple.revoke.key_id' => 'APPLEKEY01',
            'services.apple.revoke.private_key' => $private_key,
        ]);

        // Apple signs identity tokens with an RSA key from its published key set
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $this->apple_private_key);
        $rsa = openssl_pkey_get_details($key)['rsa'];

        Cache::put('socialite:Apple-JWKSet', ['keys' => [[
            'kty' => 'RSA',
            'kid' => 'APPLEJWK01',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
        ]]], 300);

        // An Apple user the company lets through without a password
        $this->user->oauth_provider_id = 'apple';
        $this->user->oauth_user_id = 'apple-token-revoke-user';
        $this->user->save();

        $this->company->oauth_password_required = false;
        $this->company->save();
    }

    protected function tearDown(): void
    {
        Cache::forget('socialite:Apple-JWKSet');

        parent::tearDown();
    }

    private function fakeApple(int $token_status = 200, int $revoke_status = 200, array $token = []): void
    {
        Http::fake([
            'https://appleid.apple.com/auth/token' => Http::response(array_merge(['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'id_token' => $this->appleIdToken('com.invoiceninja.admin')], $token), $token_status),
            'https://appleid.apple.com/auth/revoke' => Http::response('', $revoke_status),
        ]);
    }

    /* The identity token Apple returns from the code exchange */
    private function appleIdToken(string $aud, string $sub = 'apple-token-revoke-user', ?string $private_key = null): string
    {
        return JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => $aud,
            'sub' => $sub,
            'iat' => time(),
            'exp' => time() + 600,
        ], $private_key ?? $this->apple_private_key, 'RS256', 'APPLEJWK01');
    }

    private function idToken(string $aud): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256']) . '.' . $part(['aud' => $aud, 'sub' => 'apple-token-revoke-user']) . '.signature';
    }

    private function deleteCompany(Company $company, array $data, string $aud = 'com.invoiceninja.admin')
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-OAUTH-PASSWORD' => $this->idToken($aud),
        ])->deleteJson('/api/v1/companies/' . $company->hashed_id, $data);
    }

    public function testDeletingTheAccountRevokesTheAppleToken()
    {
        $this->fakeApple();

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) {
            if ($request->url() != 'https://appleid.apple.com/auth/token') {
                return false;
            }

            $client_secret = JWT::decode($request['client_secret'], new Key($this->public_key, 'ES256'));

            return $request['client_id'] == 'com.invoiceninja.admin'
                && $request['code'] == 'authorization-code'
                && $request['grant_type'] == 'authorization_code'
                && !isset($request['redirect_uri'])
                && $client_secret->iss == 'APPLETEAM1'
                && $client_secret->sub == 'com.invoiceninja.admin'
                && $client_secret->aud == 'https://appleid.apple.com';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() == 'https://appleid.apple.com/auth/revoke'
                && $request['client_id'] == 'com.invoiceninja.admin'
                && $request['token'] == 'refresh-token'
                && $request['token_type_hint'] == 'refresh_token';
        });
    }

    public function testAWebCodeIsExchangedForTheServiceIdWithItsRedirectUri()
    {
        $this->fakeApple(token: ['id_token' => $this->appleIdToken('com.invoiceninja.client')]);

        $this->deleteCompany($this->company, [
            'apple_authorization_code' => 'authorization-code',
            'apple_redirect_uri' => 'https://admin.invoicing.co',
        ], 'com.invoiceninja.client')->assertStatus(200);

        Http::assertSent(function (Request $request) {
            return $request->url() == 'https://appleid.apple.com/auth/token'
                && $request['client_id'] == 'com.invoiceninja.client'
                && $request['redirect_uri'] == 'https://admin.invoicing.co';
        });

        Http::assertSent(fn (Request $request) => $request->url() == 'https://appleid.apple.com/auth/revoke' && $request['client_id'] == 'com.invoiceninja.client');
    }

    public static function appleFailures(): array
    {
        return [
            'the exchange is refused' => [400, 200, 1],
            'the exchange is unavailable' => [503, 200, 1],
            'the revoke is unavailable' => [200, 503, 2],
        ];
    }

    #[DataProvider('appleFailures')]
    public function testAnAppleFailureDoesNotBlockTheDeletion(int $token_status, int $revoke_status, int $sent_count)
    {
        $this->fakeApple($token_status, $revoke_status);

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertSentCount($sent_count);
    }

    public function testAnUnreachableAppleDoesNotBlockTheDeletion()
    {
        Http::fake([
            'https://appleid.apple.com/*' => Http::failedConnection(),
        ]);

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));
    }

    public function testACodeForADifferentAppleUserIsNotRevoked()
    {
        $this->fakeApple(token: ['id_token' => $this->appleIdToken('com.invoiceninja.admin', 'another-apple-user')]);

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->url() == 'https://appleid.apple.com/auth/revoke');
    }

    public function testNothingIsRevokedWithoutAnIdentityTokenFromApple()
    {
        $this->fakeApple(token: ['id_token' => null]);

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertNotSent(fn (Request $request) => $request->url() == 'https://appleid.apple.com/auth/revoke');
    }

    public function testAnIdentityTokenNotSignedByAppleIsNotTrusted()
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $other_private_key);

        $this->fakeApple(token: ['id_token' => $this->appleIdToken('com.invoiceninja.admin', 'apple-token-revoke-user', $other_private_key)]);

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertNotSent(fn (Request $request) => $request->url() == 'https://appleid.apple.com/auth/revoke');
    }

    public function testDeletingOneOfTheAccountsCompaniesRevokesNothing()
    {
        $this->fakeApple();

        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $other_company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->save();

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        $this->assertNotNull(Account::find($this->account->id));

        Http::assertNothingSent();
    }

    public function testNothingIsRevokedWithoutTheSignInWithAppleKey()
    {
        $this->fakeApple();

        config(['services.apple.revoke.private_key' => null]);

        $this->deleteCompany($this->company, ['apple_authorization_code' => 'authorization-code'])->assertStatus(200);

        Http::assertNothingSent();
    }

    public function testAnEmptyAuthorizationCodeStillDeletes()
    {
        $this->fakeApple();

        $account_id = $this->account->id;

        $this->deleteCompany($this->company, ['apple_authorization_code' => '', 'apple_redirect_uri' => ''])->assertStatus(200);

        $this->assertNull(Account::find($account_id));

        Http::assertNothingSent();
    }

    public function testNothingIsRevokedWithoutAnAuthorizationCode()
    {
        $this->fakeApple();

        $this->deleteCompany($this->company, ['cancellation_message' => 'Bye'])->assertStatus(200);

        Http::assertNothingSent();
    }
}
