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

declare(strict_types=1);

namespace App\Services\Auth\Passkeys;

use App\Models\PasskeyCredential;
use App\Models\User;
use Illuminate\Support\Str;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PasskeyService
{
    private const CACHE_PREFIX = 'passkey:challenge:';

    private const CACHE_TTL_SECONDS = 300;

    private const MAX_CREDENTIALS_PER_USER = 10;

    public function getRegistrationOptions(User $user, ?string $displayName = null): array
    {
        $name = $displayName ?: trim($user->first_name . ' ' . $user->last_name);

        $webAuthn = $this->makeWebAuthn();
        $args = (array) $webAuthn->getCreateArgs(
            (string) $user->id."-".$user->referral_code,
            $user->email,
            $name ?: $user->email,
            240,
            true,
            'required',
            null,
            // Without these, iCloud Keychain and Google Password Manager silently replace the account's passkey.
            $user->passkey_credentials->pluck('credential_id')->map(fn (string $id) => base64_decode($id))->filter()->values()->toArray()
        );

        $token = $this->storeChallenge($webAuthn->getChallenge(), [
            'action' => 'registration',
            'user_id' => $user->id,
        ]);

        return ['data' => array_merge($args, ['challenge_token' => $token, 'android_origins' => $this->androidOrigins(), 'related_origins' => $this->relatedOrigins()])];
    }

    public function registerCredential(User $user, string $challengeToken, array $payload, ?string $name = null): ?PasskeyCredential
    {
        if ($user->passkey_credentials()->count() >= self::MAX_CREDENTIALS_PER_USER) {
            throw new \RuntimeException('Maximum number of passkeys reached.');
        }

        $challengeData = $this->getChallenge($challengeToken, 'registration', $user->id);
        $clientDataJSON = $this->decodeBase64Input($payload['clientDataJSON'] ?? null);
        $webAuthn = $this->makeWebAuthn($clientDataJSON);

        $result = $webAuthn->processCreate(
            $clientDataJSON,
            $this->decodeBase64Input($payload['attestationObject'] ?? null),
            $challengeData['challenge'],
            false,
            true,
            false
        );

        $credentialId = base64_encode($result->credentialId);
        $credential = PasskeyCredential::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->where('credential_id', $credentialId)
            ->first();


        if ($credential) {
            throw new \RuntimeException('Passkey credential already exists.');
        }

        $credential = new PasskeyCredential();
        $credential->account_id = $user->account_id;
        $credential->user_id = $user->id;
        $credential->credential_id = $credentialId;
        $credential->name = $name ?: ctrans('texts.passkey') . " " . now()->format('Y-m-d H:i:s');
        $credential->credential_public_key = base64_encode($result->credentialPublicKey);
        $credential->signature_counter = (int) ($result->signatureCounter ?? 0);
        $credential->transports = $payload['transports'] ?? null;
        $credential->save();

        return $credential;

    }

    public function getAuthenticationOptions(?User $user = null, bool $passwordless = false): array
    {
        $webAuthn = $this->makeWebAuthn();

        $credentialIds = [];
        if ($user) {
            $credentialIds = $user->passkey_credentials
                ->pluck('credential_id')
                ->map(fn(string $value) => base64_decode($value))
                ->filter()
                ->values()
                ->toArray();
        }

        $args = (array) $webAuthn->getGetArgs(
            $credentialIds,
            240,
            true,
            true,
            true,
            true,
            true,
            'preferred'
        );

        $token = $this->storeChallenge($webAuthn->getChallenge(), [
            'action' => 'authentication',
            'user_id' => $user?->id,
            'passwordless' => $passwordless,
        ]);

        return ['data' => array_merge($args, ['challenge_token' => $token, 'android_origins' => $this->androidOrigins(), 'related_origins' => $this->relatedOrigins()])];

    }

    public function authenticate(User $user, string $challengeToken, array $payload): User
    {
        $challengeData = $this->getChallenge($challengeToken, 'authentication', $user->id);
        $credentialId = base64_encode($this->decodeBase64Input($payload['id'] ?? null));

        $credentialQuery = PasskeyCredential::query()
            ->where('credential_id', $credentialId)
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id);

        /** @var PasskeyCredential|null $credential */
        $credential = $credentialQuery->first();

        if (!$credential) {
            throw new \RuntimeException('Passkey credential not found.');
        }

        $clientDataJSON = $this->decodeBase64Input($payload['clientDataJSON'] ?? null);
        $webAuthn = $this->makeWebAuthn($clientDataJSON);
        $webAuthn->processGet(
            $clientDataJSON,
            $this->decodeBase64Input($payload['authenticatorData'] ?? null),
            $this->decodeBase64Input($payload['signature'] ?? null),
            base64_decode($credential->credential_public_key),
            $challengeData['challenge'],
            (int) ($credential->signature_counter ?? 0),
            false
        );

        $currentCounter = $webAuthn->getSignatureCounter();
        nlog("currentCounter: $currentCounter");
        if (!is_null($currentCounter)) {
            $credential->signature_counter = $currentCounter;
        }

        nlog("last_used_at: " . Carbon::now());
        $credential->last_used_at = Carbon::now();
        $credential->save();

        return $user;
    }

    public function relyingPartyId(): string
    {
        return parse_url(config('ninja.react_url'), PHP_URL_HOST) ?: request()->getHost();
    }

    /**
     * @param string|null $clientDataJSON The ceremony's client data, when verifying one:
     *                                    a related origin needs its own instance.
     */
    private function makeWebAuthn(?string $clientDataJSON = null): WebAuthn
    {
        $rpId = $this->relyingPartyId();
        $relatedOrigin = $clientDataJSON === null ? null : $this->relatedOriginOf($clientDataJSON);

        // The 4th argument enables base64url encoding for binary fields (challenge,
        // user.id, credential ids) in the JSON options. Without it the library emits
        // its default "=?BINARY?B?...?=" RFC-1342 wrapper, which the browser/WebAuthn
        // JSON the client decodes cannot parse. NOTE: the WebAuthn constructor assigns
        // ByteBuffer::$useBase64UrlEncoding from this flag, so it must be passed here —
        // setting the static separately is overwritten by every new WebAuthn().
        $host = $relatedOrigin ? (string) parse_url($relatedOrigin, PHP_URL_HOST) : $rpId;

        $webAuthn = new WebAuthn(config('ninja.app_name'), $host, ['none', 'packed', 'fido-u2f', 'android-key', 'android-safetynet', 'apple', 'tpm'], true);

        // The Android app's origin is `android:apk-key-hash:<hash>`, accepted only for hashes added here.
        $webAuthn->addAndroidKeyHashes(self::androidKeyHashes((string) config('ninja.app_links.android_fingerprints')));

        if ($relatedOrigin) {
            // lbuchs/webauthn (checked against 2.2.0) checks the origin against the RP ID alone and has no
            // allow-list, so the instance is built for the related origin's host (passing that check, for this
            // listed origin only) and given back the real relying party's hash, which the authenticator data must
            // still carry. processCreate()'s returned rpId is then the related host; it is never stored.
            if (! property_exists(WebAuthn::class, '_rpIdHash')) {
                throw new \LogicException('lbuchs/webauthn no longer has _rpIdHash; related passkey origins need updating.');
            }

            (new \ReflectionProperty(WebAuthn::class, '_rpIdHash'))->setValue($webAuthn, hash('sha256', $rpId, true));
        }

        return $webAuthn;
    }

    /**
     * Sent with the options, so a web app on one of these origins can avoid a ceremony this server would reject.
     *
     * @return array<int, string>
     */
    private function relatedOrigins(): array
    {
        $origins = [];

        foreach (explode(',', (string) config('ninja.passkeys.related_origins')) as $origin) {
            // As a browser serialises an origin: lowercase, no trailing slash, no default port. IDNs in punycode.
            $origin = preg_replace('#:443\z#', '', strtolower(rtrim(trim($origin), '/')));
            $port = parse_url($origin, PHP_URL_PORT);

            if (preg_match('#\Ahttps://[a-z0-9.-]+(:\d+)?\z#', $origin) === 1
                && parse_url($origin, PHP_URL_HOST)
                && ($port === null || ($port >= 1 && $port <= 65535))) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * The client data's origin, when it is one of the related origins exactly and not framed by another site
     * (a cross-origin iframe then fails the library's origin check, as anywhere else).
     */
    private function relatedOriginOf(string $clientDataJSON): ?string
    {
        $clientData = json_decode($clientDataJSON, true);
        $origin = is_array($clientData) ? ($clientData['origin'] ?? null) : null;

        if (! is_string($origin) || ($clientData['crossOrigin'] ?? false) === true) {
            return null;
        }

        return in_array($origin, $this->relatedOrigins(), true) ? $origin : null;
    }

    /**
     * Unpadded base64url SHA-256 of each app-links signing certificate, as Android puts it after `android:apk-key-hash:`.
     *
     * @return array<int, string>
     */
    public static function androidKeyHashes(string $fingerprints): array
    {
        $hashes = [];

        foreach (explode(',', $fingerprints) as $fingerprint) {
            $hex = str_replace(':', '', trim($fingerprint));

            if (preg_match('/\A[0-9A-Fa-f]{64}\z/', $hex) === 1) {
                $hashes[] = rtrim(strtr(base64_encode((string) hex2bin($hex)), '+/', '-_'), '=');
            }
        }

        return array_values(array_unique($hashes));
    }

    /**
     * Sent with the options, so an app can avoid a ceremony this server would reject.
     *
     * @return array<int, string>
     */
    private function androidOrigins(): array
    {
        return array_map(fn (string $hash) => 'android:apk-key-hash:'.$hash, self::androidKeyHashes((string) config('ninja.app_links.android_fingerprints')));
    }

    private function storeChallenge(ByteBuffer|string $challenge, array $meta): string
    {
        $token = Str::random(40);
        Cache::put(self::CACHE_PREFIX . $token, array_merge($meta, [
            'challenge' => $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : $challenge,
        ]), self::CACHE_TTL_SECONDS);

        return $token;
    }

    private function getChallenge(string $token, string $action, ?int $userId): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::pull(self::CACHE_PREFIX . $token);

        if (!$payload || ($payload['action'] ?? null) !== $action) {
            throw new \RuntimeException('Invalid passkey challenge.');
        }

        if (!is_null($userId) && (int) ($payload['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Passkey challenge does not match user.');
        }

        return $payload;
    }

    private function decodeBase64Input(?string $value): string
    {
        if (!$value) {
            throw new \RuntimeException('Invalid passkey payload.');
        }

        $normalized = str_replace(['-', '_'], ['+', '/'], $value);
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 value.');
        }

        return $decoded;
    }
}
