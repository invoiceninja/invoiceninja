<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Payware;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class PaywareApi
{
    private const SANDBOX_URL = 'https://sandbox.payware.eu';
    private const PRODUCTION_URL = 'https://api.payware.eu';

    private const SANDBOX_PARTNER_ID = 'SBPARIDA';
    private const SANDBOX_VPOS_ID = '111111';
    private const SANDBOX_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr969qg5NpsDNfvICxnXlDtIFgCPql3Dh58dAYhgI0iMYEQpT4EsmhN6+m9xuTjj0zCQPIX38MSIWBQy/sYASBrgGa0q+W9roO0FSEp0pKcXe8K6GhugoFnuqat41jQCfBoAVa/AYl9ZVdTAdAnOX/oIxq359G5p013ntoFoK5QYEgIAKIFnaiz3Z18bvZHmmK5xhtCMQcza+GOqn28iUdlCQOVhVshd6b1NCxuvXhvz42dIL5FDWldnQjNO0uVZkB0e6tZZYPbY4Mp/xukyaOiaAFdu8N6+IDWj8493FeLd2Oepn1mNq5nfNnQSuMNKGOVRmMAgpkfwDvjKJYCuasQIDAQAB\n-----END PUBLIC KEY-----";

    private const DEFAULT_TIME_TO_LIVE = 600;

    private string $baseUrl;
    private string $partnerId;
    private string $vposId;
    private string $publicKey;

    public function __construct(
        string $partnerId,
        string $vposId,
        string $publicKey,
        bool $testMode = false
    ) {
        if ($testMode) {
            $this->baseUrl = self::SANDBOX_URL;
            $this->partnerId = self::SANDBOX_PARTNER_ID;
            $this->vposId = self::SANDBOX_VPOS_ID;
            $this->publicKey = self::SANDBOX_PUBLIC_KEY;
        } else {
            $this->baseUrl = self::PRODUCTION_URL;
            $this->partnerId = $partnerId;
            $this->vposId = $vposId;
            $this->publicKey = $publicKey;
        }
    }

    /**
     * Login to vPOS and return a cookie jar with the session.
     *
     * @return CookieJar
     *
     * @throws \Exception
     */
    private function login(): CookieJar
    {
        $cookieJar = new CookieJar();

        $response = Http::withOptions([
            'cookies' => $cookieJar,
            'verify' => true,
            'allow_redirects' => false,
        ])
            ->asForm()
            ->post($this->baseUrl . '/vpos/login', [
                'username' => $this->vposId,
                'password' => '',
            ]);

        if ($response->failed()) {
            throw new \Exception('payware login failed (HTTP ' . $response->status() . ')');
        }

        return $cookieJar;
    }

    /**
     * Create a payment transaction via vPOS API.
     *
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @param string $callbackUrl
     * @param string $passbackParams
     * @param int $timeToLive
     * @return array{transactionId: string}
     *
     * @throws \Exception
     */
    public function createTransaction(
        float $amount,
        string $currency,
        string $reason,
        string $callbackUrl,
        string $passbackParams,
        int $timeToLive = self::DEFAULT_TIME_TO_LIVE
    ): array {
        $timeToLive = max(60, min(600, $timeToLive));

        $cookieJar = $this->login();

        $payload = [
            'passbackParams' => $passbackParams,
            'callbackUrl' => $callbackUrl,
            'trData' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'reasonL1' => mb_substr($reason, 0, 100),
            ],
            'trOptions' => [
                'type' => 'QR',
                'timeToLive' => (string) $timeToLive,
            ],
        ];

        $response = Http::withOptions([
            'cookies' => $cookieJar,
            'verify' => true,
            'allow_redirects' => false,
        ])
            ->timeout(30)
            ->acceptJson()
            ->post($this->baseUrl . '/vpos/api/transactions', $payload);

        if ($response->failed()) {
            throw new \Exception('payware API error: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['transactionId'])) {
            throw new \Exception('payware API returned no transactionId');
        }

        return [
            'transactionId' => $data['transactionId'],
        ];
    }

    /**
     * Validate an incoming webhook JWT and return the decoded payload.
     *
     * @param string $authorizationHeader  The full Authorization header value
     * @param string $rawBody              The raw POST body
     * @return object The decoded webhook payload
     *
     * @throws \Exception
     */
    public function validateWebhook(string $authorizationHeader, string $rawBody): object
    {
        $token = str_replace('Bearer ', '', $authorizationHeader);

        $decoded = $this->decodeJwt($token);

        if ($decoded === false) {
            throw new \Exception('Invalid JWT signature');
        }

        // Verify issuer matches partner ID
        if (($decoded['payload']->iss ?? '') !== $this->partnerId) {
            throw new \Exception('JWT issuer mismatch');
        }

        // Verify audience
        if (($decoded['payload']->aud ?? '') !== 'https://payware.eu') {
            throw new \Exception('JWT audience mismatch');
        }

        // Verify iat freshness (reject tokens older than 5 minutes to prevent replay attacks)
        $iat = $decoded['payload']->iat ?? null;
        if ($iat !== null && abs(time() - (int) $iat) > 300) {
            throw new \Exception('JWT token too old (iat beyond 5-minute window)');
        }

        // Verify content hash
        $headerContentMd5 = $decoded['header']->contentMd5 ?? null;
        if ($headerContentMd5) {
            $calculatedMd5 = base64_encode(md5($rawBody, true));
            if ($calculatedMd5 !== $headerContentMd5) {
                throw new \Exception('Content MD5 mismatch');
            }
        }

        return json_decode($rawBody);
    }

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    /**
     * Decode and verify a JWT token using RS256.
     *
     * @param string $token
     * @return array{header: object, payload: object}|false
     */
    private function decodeJwt(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64));
        $payload = json_decode($this->base64UrlDecode($payloadB64));
        $signature = $this->base64UrlDecode($signatureB64);

        if (!$header || !$payload || ($header->alg ?? '') !== 'RS256') {
            return false;
        }

        $data = $headerB64 . '.' . $payloadB64;
        $publicKeyResource = openssl_pkey_get_public($this->publicKey);

        if ($publicKeyResource === false) {
            return false;
        }

        $valid = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);

        if ($valid !== 1) {
            return false;
        }

        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}
