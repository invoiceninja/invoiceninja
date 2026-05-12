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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaywareApi
{
    private const SANDBOX_URL = 'https://sandbox.payware.eu';
    private const PRODUCTION_URL = 'https://api.payware.eu';

    private const SANDBOX_PARTNER_ID = 'SBPARIDA';
    private const SANDBOX_VPOS_ID = '111111';
    private const SANDBOX_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr969qg5NpsDNfvICxnXlDtIFgCPql3Dh58dAYhgI0iMYEQpT4EsmhN6+m9xuTjj0zCQPIX38MSIWBQy/sYASBrgGa0q+W9roO0FSEp0pKcXe8K6GhugoFnuqat41jQCfBoAVa/AYl9ZVdTAdAnOX/oIxq359G5p013ntoFoK5QYEgIAKIFnaiz3Z18bvZHmmK5xhtCMQcza+GOqn28iUdlCQOVhVshd6b1NCxuvXhvz42dIL5FDWldnQjNO0uVZkB0e6tZZYPbY4Mp/xukyaOiaAFdu8N6+IDWj8493FeLd2Oepn1mNq5nfNnQSuMNKGOVRmMAgpkfwDvjKJYCuasQIDAQAB\n-----END PUBLIC KEY-----";

    private const DEFAULT_TIME_TO_LIVE = 600;

    // Circuit breaker: cooldown after a failed login. Caps cascading attempts
    // against payware's 5-strike vPOS lockout (15 min) when credentials are misconfigured.
    // The marker is keyed on the full credential set (baseUrl + partnerId + vposId + publicKey),
    // so any admin save that changes a field rotates the cache key and immediately resumes traffic;
    // verifyConnection() (Health Check) also explicitly forgets the marker on success.
    private const LOGIN_FAILURE_COOLDOWN_SECONDS = 60;

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
    private function login(bool $bypassFailureCache = false): CookieJar
    {
        $cacheKey = $this->loginFailureCacheKey();

        if (!$bypassFailureCache && Cache::has($cacheKey)) {
            throw new \Exception('payware login skipped: previous attempt failed, awaiting cooldown to avoid vPOS lockout');
        }

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
            Cache::put($cacheKey, time(), self::LOGIN_FAILURE_COOLDOWN_SECONDS);
            throw new \Exception('payware login failed (HTTP ' . $response->status() . ')');
        }

        // Successful login clears any prior failure marker so the breaker doesn't
        // continue blocking customer traffic after an admin fixes the credentials.
        Cache::forget($cacheKey);

        return $cookieJar;
    }

    /**
     * Admin-initiated credential check. Bypasses the circuit breaker so that
     * the admin always gets a fresh result, and clears the failure marker on
     * success so customer traffic resumes immediately when creds are fixed.
     */
    public function verifyConnection(): bool
    {
        try {
            $this->login(true);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function loginFailureCacheKey(): string
    {
        return 'payware_login_failed:' . hash('sha256', $this->baseUrl . '|' . $this->partnerId . '|' . $this->vposId . '|' . $this->publicKey);
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
        int $timeToLive = self::DEFAULT_TIME_TO_LIVE,
        int $currencyPrecision = 2
    ): array {
        $timeToLive = max(60, min(600, $timeToLive));
        $currencyPrecision = max(0, min(4, $currencyPrecision));

        $cookieJar = $this->login();

        $payload = [
            'passbackParams' => $passbackParams,
            'callbackUrl' => $callbackUrl,
            'trData' => [
                'amount' => number_format($amount, $currencyPrecision, '.', ''),
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

        // Verify iat freshness: allow up to 60s clock skew forward, 300s backward to prevent replay
        $iat = $decoded['payload']->iat ?? null;
        if ($iat === null) {
            throw new \Exception('JWT missing iat claim');
        }
        $now = time();
        if ((int) $iat > $now + 60 || (int) $iat < $now - 300) {
            throw new \Exception('JWT iat outside acceptable window');
        }

        // Verify body integrity via SHA-256 hash carried in JWT header (server-side hardening)
        $headerContentSha256 = $decoded['header']->contentSha256 ?? null;
        if (!$headerContentSha256) {
            throw new \Exception('JWT missing contentSha256 header');
        }
        $calculatedSha256 = base64_encode(hash('sha256', $rawBody, true));
        if (!hash_equals($headerContentSha256, $calculatedSha256)) {
            throw new \Exception('Content SHA-256 mismatch');
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
