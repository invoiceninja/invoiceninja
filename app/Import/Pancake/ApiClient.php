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

namespace App\Import\Pancake;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ApiClient
{
    /** @var array<string, mixed>|null */
    private ?array $current_company = null;

    /** @var array<string, array{amount: ?float, balance: ?float}> */
    private array $invoice_financials = [];

    public function __construct(
        private readonly string $api_token,
        private readonly string $base_url,
        private readonly int $timeout = 60,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, scalar> $query
     */
    public function create(DatabaseEntity $entity, array $payload, array $query = []): string
    {
        $response = $this->createResponse($entity, $payload, $query);

        return $this->destinationId($response, $entity->label());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, scalar> $query
     * @return array{id: string, reused: bool}
     */
    public function findOrCreate(DatabaseEntity $entity, array $payload, array $query = []): array
    {
        $destination_id = $this->findExisting($entity, $payload);

        if ($destination_id !== null) {
            return ['id' => $destination_id, 'reused' => true];
        }

        $response = $this->createResponse($entity, $payload, $query);

        if ($response->unprocessableEntity()) {
            $destination_id = $this->findExisting($entity, $payload);

            if ($destination_id !== null) {
                return ['id' => $destination_id, 'reused' => true];
            }
        }

        return [
            'id' => $this->destinationId($response, $entity->label()),
            'reused' => false,
        ];
    }

    /** @param array<string, mixed> $settings */
    public function updateCurrentCompany(array $settings): string
    {
        $company = $this->currentCompany();
        $company_id = $company['hashed_id'] ?? $company['id'] ?? null;

        if (! is_string($company_id) && ! is_int($company_id)) {
            throw new RuntimeException('Invoice Ninja did not return an ID for the current company.');
        }

        $current_settings = is_array($company['settings'] ?? null) ? $company['settings'] : [];
        $merged_settings = array_replace($current_settings, $settings);
        $merged_settings['company_logo'] ??= (string) ($current_settings['company_logo'] ?? '');
        $response = $this->request()->put(
            $this->endpoint('companies/' . $company_id),
            ['settings' => $merged_settings],
        );

        $this->data($response, 'company update');

        return (string) $company_id;
    }

    public function verify(): string
    {
        $company = $this->currentCompany();
        $company_id = $company['hashed_id'] ?? $company['id'] ?? null;

        if (! is_string($company_id) && ! is_int($company_id)) {
            throw new RuntimeException('Invoice Ninja did not return an ID for the current company.');
        }

        return (string) $company_id;
    }

    public function availableInvoiceAmount(string $invoice_id): ?float
    {
        return $this->invoiceFinancials($invoice_id)['balance'];
    }

    public function invoiceAmount(string $invoice_id): ?float
    {
        return $this->invoiceFinancials($invoice_id)['amount'];
    }

    /** @return array{amount: ?float, balance: ?float} */
    private function invoiceFinancials(string $invoice_id): array
    {
        if (isset($this->invoice_financials[$invoice_id])) {
            return $this->invoice_financials[$invoice_id];
        }

        $response = $this->request()->get($this->endpoint("invoices/{$invoice_id}"));
        $invoice = $this->data($response, 'invoice financial lookup');
        $amount = isset($invoice['amount']) && is_numeric($invoice['amount'])
            ? (float) $invoice['amount']
            : null;

        if (isset($invoice['balance']) && is_numeric($invoice['balance'])) {
            $balance = max(0.0, (float) $invoice['balance']);
        } elseif ($amount !== null) {
            $paid_to_date = isset($invoice['paid_to_date']) && is_numeric($invoice['paid_to_date'])
                ? (float) $invoice['paid_to_date']
                : 0.0;
            $balance = max(0.0, $amount - $paid_to_date);
        } else {
            $balance = null;
        }

        return $this->invoice_financials[$invoice_id] = compact('amount', 'balance');
    }

    public function upload(
        DatabaseEntity $entity,
        string $destination_id,
        string $path,
        string $filename,
        bool $is_public = false,
    ): void {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException("Unable to read Pancake attachment: {$path}");
        }

        try {
            $response = $this->request()
                ->attach('documents[]', $stream, $filename)
                ->put($this->endpoint("{$entity->endpoint()}/{$destination_id}/upload"), [
                    'is_public' => $is_public ? 'true' : 'false',
                ]);
        } finally {
            fclose($stream);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->failureMessage($response, "{$entity->label()} document upload"));
        }
    }

    public function updateCompanyLogo(string $company_id, string $path, string $filename): void
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException("Unable to read Pancake company logo: {$path}");
        }

        try {
            $response = $this->request()
                ->attach('company_logo', $stream, $filename)
                ->put($this->endpoint("companies/{$company_id}"));
        } finally {
            fclose($stream);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->failureMessage($response, 'company logo upload'));
        }
    }

    /** @return array<string, mixed> */
    public function first(DatabaseEntity $entity, array $query = []): array
    {
        $response = $this->request()->get($this->endpoint($entity->endpoint(), $query));
        $data = $this->data($response, $entity->label());

        if (array_is_list($data)) {
            $first = $data[0] ?? [];

            return is_array($first) ? $first : [];
        }

        return $data;
    }

    /** @param array<string, mixed> $payload */
    public function findExisting(DatabaseEntity $entity, array $payload): ?string
    {
        [$field, $value] = match ($entity) {
            DatabaseEntity::TaxRates,
            DatabaseEntity::ExpenseCategories,
            DatabaseEntity::TaskStatuses => ['name', $payload['name'] ?? null],
            DatabaseEntity::Projects,
            DatabaseEntity::Tasks,
            DatabaseEntity::Clients,
            DatabaseEntity::Vendors,
            DatabaseEntity::Invoices,
            DatabaseEntity::Quotes,
            DatabaseEntity::Credits,
            DatabaseEntity::RecurringInvoices,
            DatabaseEntity::Expenses => ['number', $payload['number'] ?? null],
            DatabaseEntity::Products => ['product_key', $payload['product_key'] ?? null],
            default => [null, null],
        };

        if (! is_string($field) || (! is_string($value) && ! is_int($value))) {
            return null;
        }

        $lookup_query = ['per_page' => 100];

        if ($entity !== DatabaseEntity::TaskStatuses) {
            $lookup_query['filter'] = (string) $value;
        }

        $response = $this->request()->get($this->endpoint($entity->endpoint(), $lookup_query));
        $data = $this->data($response, "existing {$entity->label()} lookup");
        $records = array_is_list($data) ? $data : [$data];

        foreach ($records as $record) {
            if (! is_array($record) || ! $this->sameIdentity($field, $record[$field] ?? null, $value)) {
                continue;
            }

            if ($entity === DatabaseEntity::Products
                && ! $this->samePancakeSource($record['notes'] ?? null, $payload['notes'] ?? null)) {
                continue;
            }

            $id = $record['hashed_id'] ?? $record['id'] ?? null;

            if (is_string($id) || is_int($id)) {
                return (string) $id;
            }
        }

        return null;
    }

    private function sameIdentity(string $field, mixed $existing_value, string|int $source_value): bool
    {
        if ($field !== 'name') {
            return (string) $existing_value === (string) $source_value;
        }

        return $this->normalizeName($existing_value) === $this->normalizeName($source_value);
    }

    private function normalizeName(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return mb_strtolower($normalized ?? trim((string) $value), 'UTF-8');
    }

    private function samePancakeSource(mixed $existing_notes, mixed $source_notes): bool
    {
        if (! is_string($existing_notes) || ! is_string($source_notes)) {
            return false;
        }

        if (! preg_match('/Pancake source: items:\d+/', $source_notes, $matches)) {
            return false;
        }

        return str_contains($existing_notes, $matches[0]);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['X-API-TOKEN' => $this->api_token])
            ->connectTimeout(10)
            ->timeout($this->timeout)
            ->retry(
                [250, 1000],
                when: function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError());
                },
                throw: false,
            );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, scalar> $query
     */
    private function createResponse(DatabaseEntity $entity, array $payload, array $query): Response
    {
        return $this->request()->post(
            $this->endpoint($entity->endpoint(), $query),
            $payload,
        );
    }

    /** @return array<string, mixed> */
    private function currentCompany(): array
    {
        if ($this->current_company !== null) {
            return $this->current_company;
        }

        $response = $this->request()->post($this->endpoint('companies/current'));
        $data = $this->data($response, 'current company');

        return $this->current_company = $data;
    }

    /** @param array<string, scalar> $query */
    private function endpoint(string $path, array $query = []): string
    {
        $url = rtrim($this->base_url, '/') . '/api/v1/' . ltrim($path, '/');

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    private function destinationId(Response $response, string $label): string
    {
        $data = $this->data($response, $label);
        $id = $data['hashed_id'] ?? $data['id'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            throw new RuntimeException("Invoice Ninja did not return an ID after creating {$label}.");
        }

        return (string) $id;
    }

    /** @return array<mixed> */
    private function data(Response $response, string $label): array
    {
        if ($response->failed()) {
            throw new RuntimeException($this->failureMessage($response, $label));
        }

        $data = $response->json('data', []);

        return is_array($data) ? $data : [];
    }

    private function failureMessage(Response $response, string $label): string
    {
        $message = $response->json('message')
            ?? $response->json('error.message')
            ?? $response->body();
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            $message .= ' ' . json_encode($errors, JSON_UNESCAPED_SLASHES);
        }

        return sprintf(
            'Invoice Ninja API rejected %s with HTTP %d: %s',
            $label,
            $response->status(),
            mb_substr(trim((string) $message), 0, 2000),
        );
    }
}
