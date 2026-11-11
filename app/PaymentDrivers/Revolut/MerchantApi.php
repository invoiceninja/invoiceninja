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

namespace App\PaymentDrivers\Revolut;

use App\Models\CompanyGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The Revolut Merchant API calls the driver makes. Every call goes through
 * send(), which never throws: a failed call returns null (transport error)
 * or the error response, for the caller to handle.
 *
 * @see https://developer.revolut.com/docs/api/merchant
 */
class MerchantApi
{
    /** Merchant API version sent on every request (Revolut-Api-Version header). */
    public const API_VERSION = '2024-09-01';

    public const LIVE_URL = 'https://merchant.revolut.com';

    public const SANDBOX_URL = 'https://sandbox-merchant.revolut.com';

    public function __construct(private CompanyGateway $company_gateway)
    {
    }

    public function baseUrl(): string
    {
        return $this->company_gateway->getConfigField('testMode') ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * @see https://developer.revolut.com/docs/api/merchant#create-an-order
     */
    public function createOrder(array $payload): ?Response
    {
        return $this->send('post', '/api/orders', $payload);
    }

    /**
     * The order, or null when it could not be read.
     *
     * @see https://developer.revolut.com/docs/api/merchant#retrieve-an-order
     */
    public function retrieveOrder(string $order_id): ?array
    {
        $response = $this->send('get', '/api/orders/' . rawurlencode($order_id));

        $order = $response?->json();

        if (! $response?->successful() || ! is_array($order) || empty($order['id'])) {
            nlog('Revolut: could not retrieve order ' . $order_id . ': ' . ($response ? $response->body() : 'no response'));

            return null;
        }

        return $order;
    }

    /**
     * @see https://developer.revolut.com/docs/api/merchant#refund-an-order
     */
    public function refundOrder(string $order_id, array $payload): ?Response
    {
        return $this->send('post', '/api/orders/' . rawurlencode($order_id) . '/refund', $payload);
    }

    /**
     * Whether the API key is accepted in the selected environment.
     */
    public function credentialsWork(): bool
    {
        return (bool) $this->send('get', '/api/orders', ['limit' => 1])?->successful();
    }

    /**
     * The JSON body of a response, or a message when there is none.
     */
    public static function body(?Response $response): array
    {
        $body = $response?->json();

        return is_array($body) ? $body : ['message' => $response ? $response->body() : 'Could not reach Revolut'];
    }

    /**
     * A readable message from a Revolut error body.
     */
    public static function errorMessage(?array $body, string $fallback): string
    {
        return (string) ($body['message'] ?? $body['error_message'] ?? $body['code'] ?? $fallback);
    }

    /**
     * Call the API. Returns null when Revolut could not be reached.
     */
    private function send(string $method, string $path, array $data = []): ?Response
    {
        try {
            $request = Http::withToken((string) $this->company_gateway->getConfigField('apiKey'))
                ->withHeaders(['Revolut-Api-Version' => self::API_VERSION])
                ->withOptions(['verify' => true, 'allow_redirects' => false])
                ->acceptJson()
                ->timeout(30)
                ->baseUrl($this->baseUrl());

            return match ($method) {
                'get' => $request->get($path, $data),
                'patch' => $request->patch($path, $data),
                'delete' => $request->delete($path, $data),
                default => $request->post($path, $data),
            };
        } catch (\Throwable $e) {
            nlog("Revolut {$method} {$path} failed: {$e->getMessage()}");

            return null;
        }
    }
}
