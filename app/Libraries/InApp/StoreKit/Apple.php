<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries\InApp\StoreKit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class Apple.
 */
class Apple
{
    private string $bundle_id = '';

    private string $issuer_id = '';

    private string $key_id = '';

    private string $private_key = '';

    private string $alg = 'ES256';

    public function createJwt()
    {
        $this->bundle_id = config('ninja.ninja_apple_bundle_id');

        $this->issuer_id = config('ninja.ninja_apple_issuer_id');

        $this->key_id = config('ninja.ninja_apple_api_key');

        $this->private_key = config('ninja.ninja_apple_private_key');

        $issue_time = time();

        $expiration_time = $issue_time + 60 * 60;

        $header = [
            'alg' => $this->alg,
            'kid' => $this->key_id,
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->issuer_id,
            'iat' => $issue_time,
            'exp' => $expiration_time,
            'aud' => 'appstoreconnect-v1',
            'nonce' => $this->guidv4(),
            'bid' => $this->bundle_id,
        ];

        $jwt = JWT::encode($payload, $this->private_key, $this->alg, null, $header);

        $decoded = JWT::decode($jwt, new Key($this->private_key, $this->alg));

        return $decoded;
    }

    private function guidv4()
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }
}
