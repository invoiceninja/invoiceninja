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
        $args = (array)$webAuthn->getCreateArgs(
            (string) $user->id,
            $user->email,
            $name ?: $user->email,
            240,
            true,
            'preferred',
            null
        );

        $token = $this->storeChallenge($webAuthn->getChallenge(), [
            'action' => 'registration',
            'user_id' => $user->id,
        ]);

        return ['data' => array_merge($args, ['challenge_token' => $token])];
    }

    public function registerCredential(User $user, string $challengeToken, array $payload, ?string $name = null): ?PasskeyCredential
    {
        if ($user->passkey_credentials()->count() >= self::MAX_CREDENTIALS_PER_USER) {
            throw new \RuntimeException('Maximum number of passkeys reached.');
        }

        $challengeData = $this->getChallenge($challengeToken, 'registration', $user->id);
        $webAuthn = $this->makeWebAuthn();

        $result = $webAuthn->processCreate(
            $this->decodeBase64Input($payload['clientDataJSON'] ?? null),
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


        if($credential) {
            throw new \RuntimeException('Passkey credential already exists.');
        }

        $credential = new PasskeyCredential();
        $credential->account_id = $user->account_id;
        $credential->user_id = $user->id;
        $credential->credential_id = $credentialId;
        $credential->name = $name ?: ctrans('texts.passkey'). " " . now()->format('Y-m-d H:i:s');
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
                ->map(fn (string $value) => base64_decode($value))
                ->filter()
                ->values()
                ->toArray();
        }

        $args = (array)$webAuthn->getGetArgs(
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

        return ['data' => array_merge($args, ['challenge_token' => $token])];
        
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

        $webAuthn = $this->makeWebAuthn();
        $webAuthn->processGet(
            $this->decodeBase64Input($payload['clientDataJSON'] ?? null),
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

    private function makeWebAuthn(): WebAuthn
    {
        $rpId = parse_url(config('ninja.react_url'), PHP_URL_HOST) ?: request()->getHost();

        return new WebAuthn(config('ninja.app_name'), $rpId, ['none', 'packed', 'fido-u2f', 'android-key', 'android-safetynet', 'apple', 'tpm']);
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
