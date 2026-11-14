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
 * Passkeys from a web app on another host than the relying party's
 * (WebAuthn Related Origin Requests): the Flutter web app on
 * https://admin.invoicing.co signing in with passkeys that belong to
 * app.invoicing.co. The browser allows it when the relying party's
 * /.well-known/webauthn lists the page; the server only for the origins in
 * PASSKEY_RELATED_ORIGINS, and the authenticator data must still be for the
 * relying party.
 *
 * Every assertion here is signed with a real P-256 key and verified by the
 * real library.
 */
class PasskeyRelatedOriginTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use PasskeyTestHelpers;

    private string $related = 'https://admin.invoicing.co';

    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithPredis::class]);

        $this->makeTestData();
        $this->passkeyConfig();
        config(['ninja.passkeys.related_origins' => $this->related]);

        $this->credentialId = random_bytes(16);

        $credential = new PasskeyCredential();
        $credential->account_id = $this->account->id;
        $credential->user_id = $this->user->id;
        $credential->name = 'Chrome';
        $credential->credential_id = base64_encode($this->credentialId);
        $credential->credential_public_key = base64_encode($this->publicKeyPem);
        $credential->signature_counter = 0;
        $credential->save();
    }

    public function testARelatedOriginSignsIn(): void
    {
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $this->related)
        )->assertOk();
    }

    public function testTheOptionsListTheRelatedOrigins(): void
    {
        // A web app checks this before the browser sheet: a server that would reject
        // its origin costs a lockout strike, so the ceremony is never started there.
        $this->assertSame([$this->related], $this->loginOptions()['related_origins']);
        $this->assertSame([$this->related], $this->registrationOptions()['related_origins']);

        config(['ninja.passkeys.related_origins' => '']);

        $this->assertSame([], $this->loginOptions()['related_origins']);
    }

    public function testOnlyHttpsOriginsAreListedAsABrowserWritesThem(): void
    {
        config(['ninja.passkeys.related_origins' => ' https://Admin.Invoicing.co/ , https://admin.invoicing.co:443, http://admin.invoicing.co, admin.invoicing.co, https://a.example/path, https://b.example:99999']);

        $this->assertSame([$this->related], $this->loginOptions()['related_origins']);
    }

    public function testAListedOriginWithAPortSignsIn(): void
    {
        config(['ninja.passkeys.related_origins' => 'https://admin.invoicing.co:8443']);
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], 'https://admin.invoicing.co:8443')
        )->assertOk();
    }

    public function testAnOriginThatIsNotListedCannotSignIn(): void
    {
        config(['ninja.passkeys.related_origins' => '']);

        $this->expectInvalid($this->related, 'invalid origin');
    }

    public function testALookalikeOfTheListedOriginCannotSignIn(): void
    {
        $this->expectInvalid('https://evil-admin.invoicing.co', 'invalid origin');
    }

    public function testOnlyTheExactOriginIsRelated(): void
    {
        // The library's own check would take a subdomain of the host it is given.
        $this->expectInvalid('https://x.admin.invoicing.co', 'invalid origin');
    }

    public function testADifferentPortIsAnotherOrigin(): void
    {
        $this->expectInvalid('https://admin.invoicing.co:8443', 'invalid origin');
    }

    public function testARelatedOriginFramedByAnotherSiteCannotSignIn(): void
    {
        $this->expectInvalid($this->related, 'invalid origin', crossOrigin: true);
    }

    public function testARelatedOriginStillNeedsTheRelyingPartysAuthenticatorData(): void
    {
        // Signed by an authenticator for the related host itself, not for app.invoicing.co.
        $this->rpId = 'admin.invoicing.co';

        $this->expectInvalid($this->related, 'invalid rpId hash');
    }

    public function testTheRelyingPartyItselfStillSignsIn(): void
    {
        $options = $this->loginOptions();

        $this->passkeyLogin(
            $options['challenge_token'],
            $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], 'https://'.$this->rpId)
        )->assertOk();
    }

    public function testARelatedOriginRegistersAPasskey(): void
    {
        $credentialId = random_bytes(16);
        $options = $this->registrationOptions();

        $this->assertSame($this->rpId, $options['publicKey']['rp']['id']);

        $this->authenticated()->postJson('/api/v1/settings/passkeys', [
            'challenge_token' => $options['challenge_token'],
            'credential' => $this->attestationPayload($credentialId, $options['publicKey']['challenge'], $this->related),
            'name' => 'Chrome',
        ])->assertOk();

        $this->assertTrue(PasskeyCredential::query()
            ->where('user_id', $this->user->id)
            ->where('credential_id', base64_encode($credentialId))
            ->exists());
    }

    public function testAnUnlistedOriginCannotRegister(): void
    {
        config(['ninja.passkeys.related_origins' => '']);
        $options = $this->registrationOptions();

        $this->authenticated()->postJson('/api/v1/settings/passkeys', [
            'challenge_token' => $options['challenge_token'],
            'credential' => $this->attestationPayload(random_bytes(16), $options['publicKey']['challenge'], $this->related),
            'name' => 'Chrome',
        ])->assertStatus(422);

        $this->assertSame(1, PasskeyCredential::query()->where('user_id', $this->user->id)->count());
    }

    /** Straight through the service, so the reason for the rejection is visible. */
    private function expectInvalid(string $origin, string $message, bool $crossOrigin = false): void
    {
        $service = app(PasskeyService::class);
        $options = json_decode(json_encode($service->getAuthenticationOptions($this->user, true)['data']), true);
        $payload = $this->assertionPayload($this->credentialId, $options['publicKey']['challenge'], $origin);

        if ($crossOrigin) {
            // Re-signed client data with crossOrigin set, as a browser reports a ceremony inside a foreign iframe.
            $clientData = json_decode($this->b64urlDecode($payload['clientDataJSON']), true);
            $clientData['crossOrigin'] = true;
            $clientDataJson = json_encode($clientData, JSON_UNESCAPED_SLASHES);
            $authenticatorData = $this->b64urlDecode($payload['authenticatorData']);
            $payload['clientDataJSON'] = $this->b64url($clientDataJson);
            $payload['signature'] = $this->b64url($this->signAssertion($authenticatorData, $clientDataJson));
        }

        $this->expectException(WebAuthnException::class);
        $this->expectExceptionMessage($message);

        $service->authenticate($this->user, $options['challenge_token'], $payload);
    }

    private function loginOptions(): array
    {
        return $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')])
            ->postJson('/api/v1/passkeys/login/options', ['email' => $this->user->email])
            ->assertOk()
            ->json('data');
    }

    private function registrationOptions(): array
    {
        return $this->authenticated()
            ->postJson('/api/v1/settings/passkeys/options')
            ->assertOk()
            ->json('data');
    }

    private function authenticated()
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
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
}
