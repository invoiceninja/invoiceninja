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

use App\Http\Middleware\ThrottleRequestsWithPredis;
use App\Models\PasskeyCredential;
use App\Services\Auth\Passkeys\PasskeyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use lbuchs\WebAuthn\WebAuthnException;
use Tests\MockAccountData;
use Tests\PasskeyTestHelpers;
use Tests\TestCase;

/**
 * Signing in with a passkey from the native apps.
 *
 * A browser reports its page origin (`https://app.invoicing.co`); an Android
 * app reports `android:apk-key-hash:<hash of its signing certificate>`, which
 * lbuchs/webauthn accepts only for hashes passed to addAndroidKeyHashes().
 * Until the service passed any, every Android assertion died on
 * `in_array(): Argument #2 must be of type array, null given`.
 *
 * Every assertion here is signed with a real P-256 key and verified by the
 * real library.
 */
class PasskeyNativeOriginTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use PasskeyTestHelpers;

    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();

        // Hosted limits /api/v1/login to four requests a minute per IP, and the
        // counters outlive a test.
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithPredis::class]);

        $this->makeTestData();
        $this->passkeyConfig();

        $this->credentialId = random_bytes(16);

        $credential = new PasskeyCredential();
        $credential->account_id = $this->account->id;
        $credential->user_id = $this->user->id;
        $credential->name = 'Pixel';
        $credential->credential_id = base64_encode($this->credentialId);
        $credential->credential_public_key = base64_encode($this->publicKeyPem);
        $credential->signature_counter = 0;
        $credential->save();
    }

    public function testAnAndroidPasskeySignsIn(): void
    {
        $options = $this->loginOptions();

        $this->assertSame($this->rpId, $options['publicKey']['rpId']);
        $this->assertSame([$this->b64url($this->credentialId)], array_column($options['publicKey']['allowCredentials'], 'id'));

        $response = $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->playOriginHash))
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.0.token.token'));
        $this->assertNotNull($this->storedCredential()->last_used_at);
    }

    public function testSignInOptionsListTheAndroidOriginsTheServerAccepts(): void
    {
        // An app checks this before the OS sheet: a server that would reject its
        // origin costs a lockout strike, so the ceremony is never started there.
        $this->assertSame(
            [$this->androidOrigin($this->playOriginHash), $this->androidOrigin($this->fdroidOriginHash)],
            $this->loginOptions()['android_origins']
        );

        config(['ninja.app_links.android_fingerprints' => '']);

        $this->assertSame([], $this->loginOptions()['android_origins']);
    }

    public function testAnAndroidPasskeyAdvancesTheStoredSignatureCounter(): void
    {
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->playOriginHash), 7)
        )->assertOk();

        $this->assertSame(7, (int) $this->storedCredential()->signature_counter);
    }

    public function testEveryPublishedSigningCertificateIsTrusted(): void
    {
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->fdroidOriginHash))
        )->assertOk();
    }

    public function testABrowserOnTheRelyingPartyStillSignsIn(): void
    {
        // React, and the iOS / macOS apps, which report https://<rpId>.
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], 'https://'.$this->rpId)
        )->assertOk();
    }

    public function testAnAppSignedWithAnotherCertificateCannotSignIn(): void
    {
        $options = $this->loginOptions();

        $response = $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->b64url(random_bytes(32))))
        );

        $response->assertStatus(400);
        $response->assertJsonPath('message', ctrans('texts.invalid_credentials'));
        $this->assertNull($this->storedCredential()->last_used_at);
    }

    public function testAnUnknownSigningCertificateIsAnInvalidOrigin(): void
    {
        $this->expectOriginRejected($this->b64url(random_bytes(32)));
    }

    public function testTheStandardBase64FormOfATrustedHashIsNotAccepted(): void
    {
        // Android sends base64url; a standard-alphabet or padded hash would mean
        // the server had built the list in the wrong encoding.
        $this->expectOriginRejected(base64_encode($this->b64urlDecode($this->playOriginHash)));
    }

    public function testNoAndroidAppIsTrustedWhenNoFingerprintsAreConfigured(): void
    {
        config(['ninja.app_links.android_fingerprints' => '']);

        $this->expectOriginRejected($this->playOriginHash);
    }

    /** Straight through the service, so the reason for the rejection is visible. */
    private function expectOriginRejected(string $hash): void
    {
        $service = app(PasskeyService::class);
        $options = json_decode(json_encode($service->getAuthenticationOptions($this->user, true)['data']), true);

        $this->expectException(WebAuthnException::class);
        $this->expectExceptionMessage('invalid origin');

        $service->authenticate(
            $this->user,
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->androidOrigin($hash))
        );
    }

    private function loginOptions(): array
    {
        $response = $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')])
            ->postJson('/api/v1/passkeys/login/options', ['email' => $this->user->email]);

        $response->assertOk();

        return $response->json('data');
    }

    private function passkeyLogin(string $challengeToken, array $assertion)
    {
        return $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')])
            ->postJson('/api/v1/login', [
                'email' => $this->user->email,
                'passkey_challenge_token' => $challengeToken,
                'passkey_authentication' => $assertion,
            ]);
    }

    private function storedCredential(): PasskeyCredential
    {
        return PasskeyCredential::query()
            ->where('user_id', $this->user->id)
            ->where('credential_id', base64_encode($this->credentialId))
            ->firstOrFail();
    }
}
