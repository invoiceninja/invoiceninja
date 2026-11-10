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

namespace Tests;

/**
 * Builds the WebAuthn payloads a native authenticator would send, with real
 * cryptography, so a test exercises lbuchs/webauthn end to end instead of a
 * mock of it.
 *
 * Everything here is written independently of the production code it tests:
 * the Android origin hashes are hard-coded vectors computed outside PHP, and
 * the base64url / CBOR encoders are local, so a test cannot pass by agreeing
 * with a bug in the code under test.
 */
trait PasskeyTestHelpers
{
    /** The relying party the hosted service uses (the host of REACT_URL). */
    protected string $rpId = 'app.invoicing.co';

    /** Play app-signing certificate of com.invoiceninja.admin (Play Console → App signing). */
    protected string $playFingerprint = 'BB:BC:8B:FB:72:4E:F2:56:77:30:31:B1:C9:22:C0:51:D3:5D:EE:3F:CE:BD:59:3A:F7:11:2F:AB:46:7D:E8:88';

    /** F-Droid signing certificate of com.invoiceninja.admin. */
    protected string $fdroidFingerprint = 'D1:7F:AC:8D:58:95:99:23:1C:31:CE:52:A5:E6:FD:E7:2B:BA:9B:7D:DC:8B:18:03:07:E2:5C:C6:88:03:C1:7D';

    /**
     * What Android puts after `android:apk-key-hash:` for the Play build:
     * unpadded base64url of the certificate's SHA-256, computed with Python's
     * base64.urlsafe_b64encode and with openssl, not with PHP.
     */
    protected string $playOriginHash = 'u7yL-3JO8lZ3MDGxySLAUdNd7j_OvVk69xEvq0Z96Ig';

    /** The same for the F-Droid build. */
    protected string $fdroidOriginHash = '0X-sjViVmSMcMc5Speb95yu6m33cixgDB-JcxogDwX0';

    /**
     * A fixed P-256 key: openssl_pkey_new() fails for EC keys on hosts whose
     * openssl.cnf lacks default_bits, and a fixed key keeps every run identical.
     */
    protected string $privateKeyPem = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgU9RWlo+Ofp050qmQ
qswbrr12C4G3cS9jvhTF1bEThx2hRANCAARer2gZ9bcIVEsM+xWMXQD89msEaKJe
pipl+jE75mzsndZ8dgwnMj6NhQ934NY8BI2J1RShQdnTWlIFEb3w+gvu
-----END PRIVATE KEY-----
PEM;

    protected string $publicKeyPem = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEXq9oGfW3CFRLDPsVjF0A/PZrBGii
XqYqZfoxO+Zs7J3WfHYMJzI+jYUPd+DWPASNidUUoUHZ01pSBRG98PoL7g==
-----END PUBLIC KEY-----
PEM;

    protected function passkeyConfig(): void
    {
        config([
            'ninja.react_url' => 'https://'.$this->rpId,
            'ninja.app_links.android_fingerprints' => $this->playFingerprint.','.$this->fdroidFingerprint,
        ]);
    }

    protected function b64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    protected function b64urlDecode(string $value): string
    {
        $standard = strtr($value, '-_', '+/');
        $decoded = base64_decode($standard.str_repeat('=', (4 - strlen($standard) % 4) % 4), true);
        $this->assertIsString($decoded);

        return $decoded;
    }

    protected function androidOrigin(string $hash): string
    {
        return 'android:apk-key-hash:'.$hash;
    }

    /** clientDataJSON as Credential Manager or iOS would build it. */
    protected function clientDataJson(string $type, string $challengeB64Url, string $origin): string
    {
        $data = [
            'type' => $type,
            'challenge' => $challengeB64Url,
            'origin' => $origin,
        ];

        if (str_starts_with($origin, 'android:')) {
            $data['androidPackageName'] = 'com.invoiceninja.admin';
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Authenticator data for an assertion: rpIdHash ‖ flags ‖ counter.
     * 0x1D = user present, user verified, backup eligible, backed up — what
     * Google Password Manager and iCloud Keychain report.
     */
    protected function assertionAuthenticatorData(int $counter = 0, int $flags = 0x1D): string
    {
        return hash('sha256', $this->rpId, true).chr($flags).pack('N', $counter);
    }

    /** ECDSA (DER) over authenticatorData ‖ SHA-256(clientDataJSON), as WebAuthn §6.3.3 specifies. */
    protected function signAssertion(string $authenticatorData, string $clientDataJson): string
    {
        $key = openssl_pkey_get_private($this->privateKeyPem);
        $this->assertNotFalse($key);

        $signature = '';
        $this->assertTrue(openssl_sign(
            $authenticatorData.hash('sha256', $clientDataJson, true),
            $signature,
            $key,
            OPENSSL_ALGO_SHA256
        ));

        return $signature;
    }

    /** The assertion fields /api/v1/login expects under passkey_authentication. */
    protected function assertionPayload(string $credentialId, string $challengeB64Url, string $origin, int $counter = 0): array
    {
        $authenticatorData = $this->assertionAuthenticatorData($counter);
        $clientDataJson = $this->clientDataJson('webauthn.get', $challengeB64Url, $origin);

        return [
            'id' => $this->b64url($credentialId),
            'rawId' => $this->b64url($credentialId),
            'type' => 'public-key',
            'clientDataJSON' => $this->b64url($clientDataJson),
            'authenticatorData' => $this->b64url($authenticatorData),
            'signature' => $this->b64url($this->signAssertion($authenticatorData, $clientDataJson)),
            'userHandle' => null,
        ];
    }

    /**
     * A `none` attestation object for a new credential bound to the fixed key.
     * 0x5D = 0x1D plus attested credential data.
     */
    protected function noneAttestationObject(string $credentialId, int $flags = 0x5D): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKeyPem));
        $this->assertIsArray($details);

        // COSE_Key (RFC 9053): kty 1 → EC2 (2), alg 3 → ES256 (-7),
        // crv -1 → P-256 (1), x -2, y -3. lbuchs compares integer keys, and
        // requires each coordinate to be exactly 32 bytes, which
        // openssl_pkey_get_details does not guarantee (it drops leading zeros).
        $coseKey = $this->cborMap([
            [$this->cborInt(1), $this->cborInt(2)],
            [$this->cborInt(3), $this->cborInt(-7)],
            [$this->cborInt(-1), $this->cborInt(1)],
            [$this->cborInt(-2), $this->cborBytes(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT))],
            [$this->cborInt(-3), $this->cborBytes(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT))],
        ]);

        $authenticatorData = hash('sha256', $this->rpId, true)
            .chr($flags)
            .pack('N', 0)
            .str_repeat("\0", 16) // AAGUID: zero, as passkey providers send with `none`
            .pack('n', strlen($credentialId))
            .$credentialId
            .$coseKey;

        return $this->cborMap([
            [$this->cborText('fmt'), $this->cborText('none')],
            [$this->cborText('attStmt'), $this->cborMap([])],
            [$this->cborText('authData'), $this->cborBytes($authenticatorData)],
        ]);
    }

    /** The credential fields /api/v1/settings/passkeys expects. */
    protected function attestationPayload(string $credentialId, string $challengeB64Url, string $origin): array
    {
        return [
            'clientDataJSON' => $this->b64url($this->clientDataJson('webauthn.create', $challengeB64Url, $origin)),
            'attestationObject' => $this->b64url($this->noneAttestationObject($credentialId)),
            'transports' => ['internal', 'hybrid'],
        ];
    }

    // Minimal CBOR (RFC 8949) encoder for the shapes above.

    private function cborHead(int $major, int $length): string
    {
        $type = $major << 5;

        return match (true) {
            $length < 24 => chr($type | $length),
            $length < 0x100 => chr($type | 24).chr($length),
            $length < 0x10000 => chr($type | 25).pack('n', $length),
            default => chr($type | 26).pack('N', $length),
        };
    }

    private function cborInt(int $value): string
    {
        return $value >= 0 ? $this->cborHead(0, $value) : $this->cborHead(1, -1 - $value);
    }

    private function cborBytes(string $value): string
    {
        return $this->cborHead(2, strlen($value)).$value;
    }

    private function cborText(string $value): string
    {
        return $this->cborHead(3, strlen($value)).$value;
    }

    /** @param array<int, array{0: string, 1: string}> $pairs already-encoded key/value pairs */
    private function cborMap(array $pairs): string
    {
        $encoded = $this->cborHead(5, count($pairs));
        foreach ($pairs as [$key, $value]) {
            $encoded .= $key.$value;
        }

        return $encoded;
    }
}
