<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Encrypt;

class Secure
{
    public static function encrypt(string $hash): ?string
    {
        $data = null;

        $public_key = openssl_pkey_get_public(config('ninja.encryption.public_key'));

        if (openssl_public_encrypt($hash, $encrypted, $public_key)) {
            $data = base64_encode($encrypted);
        }

        return $data;
    }

    public static function decrypt(string $hash): ?string
    {
        $data = null;

        $private_key = openssl_pkey_get_private(config('ninja.encryption.private_key'));

        if (openssl_private_decrypt(base64_decode($hash), $decrypted, $private_key)) {
            $data = $decrypted;
        }

        return $data;
    }
}
