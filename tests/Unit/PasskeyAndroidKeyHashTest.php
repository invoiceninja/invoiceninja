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

namespace Tests\Unit;

use App\Services\Auth\Passkeys\PasskeyService;
use PHPUnit\Framework\TestCase;

/**
 * An Android app's WebAuthn origin is `android:apk-key-hash:` plus the unpadded
 * base64url SHA-256 of its signing certificate, and lbuchs/webauthn matches it
 * as an exact string — so the conversion from the colon-hex fingerprints that
 * app links already publish has to be exact too.
 *
 * The expected values were computed outside PHP (Python's
 * base64.urlsafe_b64encode and the openssl CLI); both contain `-` and `_`, so
 * they also pin the URL-safe alphabet.
 */
class PasskeyAndroidKeyHashTest extends TestCase
{
    private const PLAY = 'BB:BC:8B:FB:72:4E:F2:56:77:30:31:B1:C9:22:C0:51:D3:5D:EE:3F:CE:BD:59:3A:F7:11:2F:AB:46:7D:E8:88';

    private const FDROID = 'D1:7F:AC:8D:58:95:99:23:1C:31:CE:52:A5:E6:FD:E7:2B:BA:9B:7D:DC:8B:18:03:07:E2:5C:C6:88:03:C1:7D';

    private const PLAY_HASH = 'u7yL-3JO8lZ3MDGxySLAUdNd7j_OvVk69xEvq0Z96Ig';

    private const FDROID_HASH = '0X-sjViVmSMcMc5Speb95yu6m33cixgDB-JcxogDwX0';

    public function testConvertsEachFingerprintToTheOriginAndroidSends(): void
    {
        $this->assertSame(
            [self::PLAY_HASH, self::FDROID_HASH],
            PasskeyService::androidKeyHashes(self::PLAY.','.self::FDROID)
        );
    }

    public function testToleratesWhitespaceLowercaseAndBareHex(): void
    {
        $this->assertSame(
            [self::PLAY_HASH, self::FDROID_HASH],
            PasskeyService::androidKeyHashes(' '.strtolower(self::PLAY).' , '.str_replace(':', '', self::FDROID).' ')
        );
    }

    public function testSkipsAnythingThatIsNotASha256Fingerprint(): void
    {
        $sha1 = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD';
        $notHex = str_replace('BB', 'ZZ', self::PLAY);

        $this->assertSame(
            [self::PLAY_HASH],
            PasskeyService::androidKeyHashes(implode(',', ['', $sha1, $notHex, self::PLAY, ',']))
        );
    }

    public function testATrailingNewlineIsNotAFingerprint(): void
    {
        // `$` matches before a final newline; letting this through would hand
        // hex2bin() 65 characters. Its warning is an exception under Laravel's
        // error handler — on every passkey request — so raise it here too.
        set_error_handler(static function (int $severity, string $message): bool {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $hashes = PasskeyService::androidKeyHashes(str_replace(':', '', self::PLAY)."\n:");
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $hashes);
    }

    public function testListsADuplicatedFingerprintOnce(): void
    {
        $this->assertSame(
            [self::PLAY_HASH],
            PasskeyService::androidKeyHashes(self::PLAY.','.strtolower(self::PLAY))
        );
    }

    public function testAnEmptyConfigTrustsNoAndroidApp(): void
    {
        $this->assertSame([], PasskeyService::androidKeyHashes(''));
    }
}
