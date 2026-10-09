<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 *
 * open-banking.io (OBI) provider.
 *
 * OBI exposes a simple REST API for EU/UK open-banking account data. Unlike
 * the GoCardless/Nordigen flow it authenticates with a single bearer API key
 * (no QWAC/eIDAS certificates required), which makes it an attractive option
 * for self-hosters and small SaaS tenants.
 *
 * Documentation (example shape):
 *
 *   GET  /v1/institutions?country=DE
 *   POST /v1/authorizations            { institution_id, redirect_uri }
 *   GET  /v1/accounts/{id}/status
 *   GET  /v1/accounts/{id}/transactions?from_date=2026-01-01
 *   GET  /v1/accounts/{id}
 *
 * A transaction object from OBI:
 *   {
 *     "transaction_id": "string",
 *     "amount": "-42.50",            // negative = debit, positive = credit
 *     "currency": "EUR",
 *     "date": "2026-07-15",
 *     "description": "ACME MARKET",
 *     "counterparty": "DE89...",     // iban / masked account, optional
 *     "counterparty_name": "Acme",   // optional
 *     "base_type": "DEBIT"           // CREDIT | DEBIT
 *   }
 */

namespace App\Helpers\Bank\OBI;

use App\Contracts\Bank\BankSyncProviderInterface;
use Illuminate\Support\Facades\Http;

class OBI implements BankSyncProviderInterface
{
    /**
     * Production REST base URL.
     */
    protected string $base_url = 'https://api.open-banking.io';

    /**
     * Per-request timeout (seconds). OBI pagination is fast; keep it tight.
     */
    protected int $timeout = 30;

    /**
     * Bearer API key used for every authenticated request.
     */
    protected string $api_key;

    public function __construct(?string $api_key = null)
    {
        // Preference: explicit key → integration-level config JSON → app config.
        $this->api_key = $api_key ?? (string) (config('ninja.obi.api_key') ?? '');

        if ($this->api_key === '') {
            throw new \Exception('missing open-banking.io credentials');
        }
    }

    /**
     * Build a configured HTTP pending request with the bearer token applied.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function request()
    {
        return Http::withToken($this->api_key)
            ->baseUrl($this->base_url)
            ->timeout($this->timeout)
            ->acceptJson()
            ->throw(); // surface non-2xx as RequestException up the stack
    }

    /**
     * {@inheritDoc}
     */
    public function getInstitutions(?string $country = null): array
    {
        $query = [];

        if ($country !== null && $country !== '') {
            $query['country'] = $country;
        }

        $response = $this->request()->get('/v1/institutions', $query);

        $institutions = $response->json('data') ?? $response->json('institutions') ?? $response->json();

        return array_map(fn ($i) => [
            'id' => (string) ($i['id'] ?? $i['institution_id'] ?? ''),
            'name' => (string) ($i['name'] ?? ''),
            'logo' => $i['logo'] ?? null,
            'countries' => $i['countries'] ?? [],
        ], $institutions ?: []);
    }

    /**
     * {@inheritDoc}
     */
    public function createAuthorization(string $institution_id, string $redirect_uri): array
    {
        $response = $this->request()->post('/v1/authorizations', [
            'institution_id' => $institution_id,
            'redirect_uri' => $redirect_uri,
        ]);

        $payload = $response->json();

        return [
            'reference' => (string) ($payload['reference'] ?? $payload['id'] ?? $payload['authorization_id'] ?? ''),
            'link' => (string) ($payload['link'] ?? $payload['redirect_url'] ?? $payload['authorization_url'] ?? ''),
            'expires_at' => $payload['expires_at'] ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountStatus(string $account_id): array
    {
        try {
            $payload = $this->request()->get("/v1/accounts/{$account_id}/status")->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response->status();

            // 401/403 on the status endpoint means the consent is gone.
            if (in_array($status, [401, 403], true)) {
                return ['status' => 'EXPIRED'];
            }

            nlog("OBI: getAccountStatus failed for {$account_id} => HTTP {$status}: " . $e->getMessage());

            return ['status' => 'PROCESSING'];
        }

        $raw_status = strtoupper((string) ($payload['status'] ?? $payload['account_status'] ?? ''));

        return [
            'status' => $this->normalizeStatus($raw_status),
            'balance' => isset($payload['balance']) ? (float) $payload['balance'] : null,
            'currency' => $payload['currency'] ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getTransactions(string $account_id, ?string $from_date = null): array
    {
        $query = [];

        if ($from_date !== null && $from_date !== '') {
            $query['from_date'] = $from_date;
        }

        $payload = $this->request()->get("/v1/accounts/{$account_id}/transactions", $query)->json();

        $transactions = $payload['transactions'] ?? $payload['data'] ?? [];

        if (!is_array($transactions)) {
            return [];
        }

        $out = [];

        foreach ($transactions as $tx) {
            $out[] = $this->normalizeTransaction($tx);
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountMetadata(string $account_id): array
    {
        $payload = $this->request()->get("/v1/accounts/{$account_id}")->json();

        return [
            'account_id' => $account_id,
            'iban' => $payload['iban'] ?? null,
            'bban' => $payload['bban'] ?? null,
            'masked_number' => $payload['masked_number'] ?? $payload['account_number'] ?? null,
            'holder' => $payload['holder_name'] ?? $payload['owner_name'] ?? null,
            'name' => $payload['name'] ?? $payload['product'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'status' => $payload['status'] ?? null,
            'balance' => isset($payload['balance']) ? (float) $payload['balance'] : null,
            'institution_id' => $payload['institution_id'] ?? null,
        ];
    }

    /**
     * Map a provider status string onto the canonical set used by the
     * processing jobs. Unknown values are treated as transient ("PROCESSING")
     * so we never disable a healthy account on an upstream quirk.
     */
    protected function normalizeStatus(string $raw): string
    {
        return match ($raw) {
            'READY', 'ACTIVE', 'OK', 'AVAILABLE' => 'READY',
            'EXPIRED', 'REVOKED', 'REJECTED' => 'EXPIRED',
            'SUSPENDED', 'BLOCKED', 'DISABLED' => 'SUSPENDED',
            'PROCESSING', 'PENDING', 'AUTHORIZATION_REQUIRED' => 'PROCESSING',
            default => 'PROCESSING',
        };
    }

    /**
     * Normalize a raw OBI transaction into the shape documented on
     * BankSyncProviderInterface::getTransactions().
     *
     * @param  array<string, mixed>  $tx
     * @return array<string, mixed>
     */
    protected function normalizeTransaction(array $tx): array
    {
        $amount = (float) ($tx['amount'] ?? 0);

        // base_type: prefer the explicit field, otherwise infer from the sign.
        if (!empty($tx['base_type']) && in_array(strtoupper($tx['base_type']), ['CREDIT', 'DEBIT'], true)) {
            $base_type = strtoupper($tx['base_type']);
        } else {
            $base_type = $amount < 0 ? 'DEBIT' : 'CREDIT';
        }

        $transaction_id = (string) ($tx['transaction_id'] ?? $tx['id'] ?? '');

        $date = trim((string) ($tx['date'] ?? $tx['booking_date'] ?? $tx['value_date'] ?? ''));

        // Collapse to a Y-m-d date; ISO-8601 datetimes become their date part.
        if ($date !== '' && strlen($date) >= 10) {
            $date = substr($date, 0, 10);
        }

        return [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'date' => $date,
            'description' => (string) ($tx['description'] ?? $tx['remittance_information'] ?? ''),
            'counterparty' => $tx['counterparty'] ?? $tx['counterparty_account'] ?? null,
            'counterparty_name' => $tx['counterparty_name'] ?? null,
            'currency' => (string) ($tx['currency'] ?? ''),
            'base_type' => $base_type,
        ];
    }
}
