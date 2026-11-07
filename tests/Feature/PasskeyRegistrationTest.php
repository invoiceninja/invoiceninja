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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\PasskeyTestHelpers;
use Tests\TestCase;

/**
 * Adding a passkey from the native apps (Settings → Two-Factor), with a real
 * `none` attestation verified by the real library.
 */
class PasskeyRegistrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use PasskeyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithPredis::class]);

        $this->makeTestData();
        $this->passkeyConfig();
    }

    public function testAnAndroidAppRegistersAPasskeyItCanThenSignInWith(): void
    {
        $credentialId = random_bytes(16);
        $options = $this->registrationOptions();

        $this->assertSame($this->rpId, $options['publicKey']['rp']['id']);
        $this->assertContains($this->androidOrigin($this->playOriginHash), $options['android_origins']);

        $this->authenticated()->postJson('/api/v1/settings/passkeys', [
            'challenge_token' => $options['challenge_token'],
            'credential' => $this->attestationPayload($credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->playOriginHash)),
            'name' => 'Pixel',
        ])->assertOk()->assertJsonPath('data.name', 'Pixel');

        $this->assertTrue(PasskeyCredential::query()
            ->where('user_id', $this->user->id)
            ->where('credential_id', base64_encode($credentialId))
            ->exists());

        // The key the library stored is the one the authenticator holds.
        $login = $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')])
            ->postJson('/api/v1/passkeys/login/options', ['email' => $this->user->email])
            ->assertOk()
            ->json('data');

        $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')])
            ->postJson('/api/v1/login', [
                'email' => $this->user->email,
                'passkey_challenge_token' => $login['challenge_token'],
                'passkey_authentication' => $this->assertionPayload($credentialId, $login['publicKey']['challenge'], $this->androidOrigin($this->playOriginHash)),
            ])->assertOk();
    }

    public function testAnAppSignedWithAnotherCertificateCannotRegister(): void
    {
        $credentialId = random_bytes(16);
        $options = $this->registrationOptions();

        $this->authenticated()->postJson('/api/v1/settings/passkeys', [
            'challenge_token' => $options['challenge_token'],
            'credential' => $this->attestationPayload($credentialId, $options['publicKey']['challenge'], $this->androidOrigin($this->b64url(random_bytes(32)))),
        ])->assertStatus(422)->assertJsonPath('message', 'invalid origin');

        $this->assertFalse(PasskeyCredential::query()->where('user_id', $this->user->id)->exists());
    }

    public function testRegistrationOptionsExcludeThePasskeysTheUserAlreadyHas(): void
    {
        // Without the list, iCloud Keychain and Google Password Manager quietly
        // replace the existing passkey for this account, and the server keeps a
        // row for a credential that no longer exists.
        $ids = [random_bytes(16), random_bytes(32)];
        foreach ($ids as $id) {
            $credential = new PasskeyCredential();
            $credential->account_id = $this->account->id;
            $credential->user_id = $this->user->id;
            $credential->name = 'Existing';
            $credential->credential_id = base64_encode($id);
            $credential->credential_public_key = base64_encode($this->publicKeyPem);
            $credential->signature_counter = 0;
            $credential->save();
        }

        $excluded = $this->registrationOptions()['publicKey']['excludeCredentials'];

        $this->assertEqualsCanonicalizing(array_map(fn ($id) => $this->b64url($id), $ids), array_column($excluded, 'id'));
        $this->assertSame(['public-key', 'public-key'], array_column($excluded, 'type'));
    }

    public function testRegistrationOptionsExcludeNothingForAUserWithoutPasskeys(): void
    {
        $this->assertSame([], $this->registrationOptions()['publicKey']['excludeCredentials']);
    }

    private function registrationOptions(): array
    {
        return $this->authenticated()
            ->postJson('/api/v1/settings/passkeys/options')
            ->assertOk()
            ->json('data');
    }

    private function authenticated(): self
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ]);
    }
}
