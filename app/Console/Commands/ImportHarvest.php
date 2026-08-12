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

namespace App\Console\Commands;

use App\Import\Harvest\CsvImporter;
use App\Import\Harvest\Entity;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportHarvest extends Command
{
    private const API_ENDPOINT = 'https://grok.romulus.com.au/api/v1';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:import-harvest
                            {api_token : Invoice Ninja API token used to create records}
                            {directory : Directory containing Harvest CSV exports}
                            {--entities= : Comma-separated list: clients (includes contacts), users, projects, tasks, time_entries, expense_categories, expenses, invoices, invoice_payments, estimates. Omit to import all}
                            {--resolve-currency : Resolve each client currency from its parsed country}
                            {--dry-run : Resolve existing client IDs and print records without creating or updating anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Harvest CSV exports into Invoice Ninja through the API';

    public function __construct(private readonly CsvImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $api_token = trim((string) $this->argument('api_token'));

        if ($api_token === '') {
            $this->error('An Invoice Ninja API token is required.');

            return self::FAILURE;
        }

        try {
            $entities = $this->entitiesWithTimeEntryProjects(
                Entity::fromOption($this->optionString('entities')),
            );
            $result = $this->importer->build(
                (string) $this->argument('directory'),
                $entities,
                (bool) $this->option('resolve-currency'),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Prepared %d record(s) across %d Harvest entity type(s) from %d CSV file(s).',
            array_sum(array_map('count', $result['records'])),
            count(array_filter($result['records'])),
            array_sum(array_map('count', $result['files'])),
        ));

        foreach ($result['unsupported_files'] as $file) {
            $this->components->warn("Skipping unrecognized CSV: {$file}");
        }

        if ($result['unmatched_contacts'] !== []) {
            $this->components->warn(sprintf(
                '%d contact row(s) did not match a client and will be skipped.',
                count($result['unmatched_contacts']),
            ));
        }

        if ($this->option('dry-run')) {
            $unmatched_clients = [];
            $unmatched_invoices = [];
            $unmatched_projects = [];
            $client_map = $this->buildClientMap($api_token);
            $records = $this->resolveDryRunRecords(
                $result['records'],
                $client_map,
                $this->shouldBuildInvoiceMap($result['records']) ? $this->buildInvoiceMap($api_token) : [],
                $this->shouldBuildProjectMap($result['records']) ? $this->buildProjectMap($api_token, $client_map) : [],
                $unmatched_clients,
                $unmatched_invoices,
                $unmatched_projects,
            );
            $this->line(json_encode(
                $records,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
            $this->components->info('Dry run complete; no records were created.');
            $this->reportUnmatchedClients($unmatched_clients);
            $this->reportUnmatchedInvoices($unmatched_invoices);
            $this->reportUnmatchedProjects($unmatched_projects);

            return self::SUCCESS;
        }

        return $this->createRecords($result['records'], $entities, $api_token);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     * @param array<int, Entity> $entities
     */
    private function createRecords(array $records, array $entities, string $api_token): int
    {
        $counters = [
            'created' => 0,
            'tax_rates_created' => 0,
            'failed' => 0,
        ];
        $created_ids = [];
        $client_map = $this->buildClientMap($api_token);
        $invoice_map = $this->shouldBuildInvoiceMap($records) ? $this->buildInvoiceMap($api_token) : [];
        $project_map = [];
        $unmatched_clients = [];
        $unmatched_invoices = [];
        $unmatched_projects = [];
        $imports_time_entries = $this->shouldBuildProjectMap($records);

        foreach ($entities as $entity) {
            if ($entity === Entity::Projects && $imports_time_entries) {
                $client_map = array_replace($client_map, $this->buildClientMap($api_token));
                $project_map = $this->buildProjectMap($api_token, $client_map);
            }

            foreach ($records[$entity->value] ?? [] as $record) {
                $label = (string) $record['label'];

                if ($entity === Entity::Projects && isset($project_map[(string) $record['key']])) {
                    $created_ids[$entity->value][(string) $record['key']] = $project_map[(string) $record['key']];
                    $this->components->info("Using existing project: {$label}");

                    continue;
                }

                try {
                    $payload = $this->resolveReferences(
                        $record,
                        $created_ids,
                        $client_map,
                        $invoice_map,
                        $project_map,
                        $unmatched_clients,
                        $unmatched_invoices,
                        $unmatched_projects,
                    );
                    $payment_applications = [];

                    if ($entity === Entity::InvoicePayments) {
                        [$payload, $payment_applications] = $this->limitPaymentApplications(
                            $record,
                            $payload,
                            $invoice_map,
                        );
                        $payload['idempotency_key'] = $this->paymentIdempotencyKey($record);
                    }

                    $response = Http::acceptJson()
                        ->withHeaders(['X-API-TOKEN' => $api_token])
                        ->post($this->apiEndpoint($entity), $payload);

                    if ($response->failed()) {
                        $this->components->error($this->failureMessage($label, $response));
                        $counters['failed']++;

                        continue;
                    }

                    $id = $this->responseHashedId($response);

                    if (is_string($id) || is_int($id)) {
                        $created_ids[$entity->value][(string) $record['key']] = (string) $id;

                        if ($entity === Entity::Clients) {
                            $this->addClientToMap($client_map, $record, $response, (string) $id);
                        }

                        if ($entity === Entity::Invoices) {
                            $this->addInvoiceToMap($invoice_map, $record, $response, (string) $id);
                        }

                        if ($entity === Entity::Projects) {
                            $this->addProjectToMap($project_map, $record, (string) $id);
                        }
                    }

                    if ($entity === Entity::InvoicePayments) {
                        $this->applyInvoicePaymentsToMap($invoice_map, $payment_applications);
                    }

                    $this->components->info(sprintf(
                        'Created %s: %s',
                        $entity->destinationLabel(),
                        $label,
                    ));
                    $counters['created']++;
                    $this->performActions($entity, $record, $id, $api_token);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->error("Unable to import {$label}: {$exception->getMessage()}");
                    $counters['failed']++;
                }
            }

            if ($entity === Entity::Invoices) {
                $tax_rate_counters = $this->createTaxRates($records[$entity->value] ?? [], $api_token);
                $counters['tax_rates_created'] += $tax_rate_counters['created'];
                $counters['failed'] += $tax_rate_counters['failed'];
            }

            if ($entity === Entity::Projects && $imports_time_entries) {
                $project_map = array_replace(
                    $project_map,
                    $this->buildProjectMap($api_token, $client_map),
                );
            }
        }

        $this->table(array_keys($counters), [array_values($counters)]);
        $this->reportUnmatchedClients($unmatched_clients);
        $this->reportUnmatchedInvoices($unmatched_invoices);
        $this->reportUnmatchedProjects($unmatched_projects);

        return $counters['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, string> */
    private function buildClientMap(string $api_token): array
    {
        $client_map = [];
        $page = 1;
        $total_pages = 1;

        try {
            do {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-TOKEN' => $api_token])
                    ->get(rtrim(self::API_ENDPOINT, '/') . '/clients', [
                        'per_page' => 1000,
                        'page' => $page,
                    ]);

                if ($response->failed()) {
                    $this->components->warn($this->failureMessage('client map', $response));

                    break;
                }

                $data = $response->json('data', []);
                $clients = is_array($data) && ! array_is_list($data) ? [$data] : $data;

                if (! is_array($clients)) {
                    break;
                }

                foreach ($clients as $client) {
                    if (! is_array($client)) {
                        continue;
                    }

                    $name = $client['name'] ?? null;
                    $hashed_id = $client['hashed_id'] ?? $client['id'] ?? null;

                    if (is_string($name) && $name !== '' && (is_string($hashed_id) || is_int($hashed_id))) {
                        $client_map[$this->clientNameKey($name)] = (string) $hashed_id;
                    }
                }

                $total_pages = max(1, (int) $response->json('meta.pagination.total_pages', 1));
                $page++;
            } while ($page <= $total_pages);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->warn("Unable to build client map: {$exception->getMessage()}");
        }

        return $client_map;
    }

    /** @return array<string, array{id: string, available: float}> */
    private function buildInvoiceMap(string $api_token): array
    {
        $invoice_map = [];
        $page = 1;
        $total_pages = 1;

        try {
            do {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-TOKEN' => $api_token])
                    ->get(rtrim(self::API_ENDPOINT, '/') . '/invoices', [
                        'per_page' => 1000,
                        'page' => $page,
                    ]);

                if ($response->failed()) {
                    $this->components->warn($this->failureMessage('invoice map', $response));

                    break;
                }

                $data = $response->json('data', []);
                $invoices = is_array($data) && ! array_is_list($data) ? [$data] : $data;

                if (! is_array($invoices)) {
                    break;
                }

                foreach ($invoices as $invoice) {
                    if (! is_array($invoice)) {
                        continue;
                    }

                    $number = $invoice['number'] ?? null;
                    $hashed_id = $invoice['hashed_id'] ?? $invoice['id'] ?? null;

                    if (is_string($number) && $number !== '' && (is_string($hashed_id) || is_int($hashed_id))) {
                        $invoice_map[$this->referenceKey($number)] = [
                            'id' => (string) $hashed_id,
                            'available' => $this->invoiceAvailableAmount($invoice),
                        ];
                    }
                }

                $total_pages = max(1, (int) $response->json('meta.pagination.total_pages', 1));
                $page++;
            } while ($page <= $total_pages);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->warn("Unable to build invoice map: {$exception->getMessage()}");
        }

        return $invoice_map;
    }

    /**
     * @param array<string, string> $client_map
     * @return array<string, string>
     */
    private function buildProjectMap(string $api_token, array $client_map): array
    {
        $client_keys_by_id = [];

        foreach ($client_map as $client_key => $client_id) {
            $client_keys_by_id[$this->referenceKey($client_id)] = $client_key;
        }

        $project_map = [];
        $page = 1;
        $total_pages = 1;

        try {
            do {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-TOKEN' => $api_token])
                    ->get(rtrim(self::API_ENDPOINT, '/') . '/projects', [
                        'per_page' => 1000,
                        'page' => $page,
                    ]);

                if ($response->failed()) {
                    $this->components->warn($this->failureMessage('project map', $response));

                    break;
                }

                $data = $response->json('data', []);
                $projects = is_array($data) && ! array_is_list($data) ? [$data] : $data;

                if (! is_array($projects)) {
                    break;
                }

                foreach ($projects as $project) {
                    if (! is_array($project)) {
                        continue;
                    }

                    $name = $project['name'] ?? null;
                    $client_id = $project['client_id'] ?? null;
                    $hashed_id = $project['hashed_id'] ?? $project['id'] ?? null;

                    if (! is_string($name)
                        || $name === ''
                        || (! is_string($client_id) && ! is_int($client_id))
                        || (! is_string($hashed_id) && ! is_int($hashed_id))) {
                        continue;
                    }

                    $client_key = $client_keys_by_id[$this->referenceKey((string) $client_id)] ?? null;

                    if ($client_key !== null) {
                        $project_map[$this->projectReferenceKey($client_key, $name)] = (string) $hashed_id;
                    }
                }

                $total_pages = max(1, (int) $response->json('meta.pagination.total_pages', 1));
                $page++;
            } while ($page <= $total_pages);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->warn("Unable to build project map: {$exception->getMessage()}");
        }

        return $project_map;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: float}> $invoice_map
     * @param array<string, string> $project_map
     * @param array<string, string> $unmatched_clients
     * @param array<string, string> $unmatched_invoices
     * @param array<string, string> $unmatched_projects
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function resolveDryRunRecords(
        array $records,
        array $client_map,
        array $invoice_map,
        array $project_map,
        array &$unmatched_clients,
        array &$unmatched_invoices,
        array &$unmatched_projects,
    ): array {
        foreach ($records as &$entity_records) {
            foreach ($entity_records as &$record) {
                $record['payload'] = $this->resolveReferences(
                    $record,
                    [],
                    $client_map,
                    $invoice_map,
                    $project_map,
                    $unmatched_clients,
                    $unmatched_invoices,
                    $unmatched_projects,
                );
            }
            unset($record);
        }
        unset($entity_records);

        return $records;
    }

    /**
     * @param array<string, string> $client_map
     * @param array<string, mixed> $record
     */
    private function addClientToMap(array &$client_map, array $record, Response $response, string $hashed_id): void
    {
        $payload_name = data_get($record, 'payload.name');
        $response_name = $response->json('data.name');

        foreach ([$payload_name, $response_name] as $name) {
            if (is_string($name) && $name !== '') {
                $client_map[$this->clientNameKey($name)] = $hashed_id;
            }
        }
    }

    /**
     * @param array<string, array{id: string, available: float}> $invoice_map
     * @param array<string, mixed> $record
     */
    private function addInvoiceToMap(array &$invoice_map, array $record, Response $response, string $hashed_id): void
    {
        $payload_number = data_get($record, 'payload.number');
        $response_number = $response->json('data.number');

        foreach ([$payload_number, $response_number] as $number) {
            if (is_string($number) && $number !== '') {
                $invoice_map[$this->referenceKey($number)] = [
                    'id' => $hashed_id,
                    'available' => $this->invoiceAvailableAmount((array) $response->json('data', [])),
                ];
            }
        }
    }

    /** @param array<string, mixed> $invoice */
    private function invoiceAvailableAmount(array $invoice): float
    {
        $status_id = (int) ($invoice['status_id'] ?? 0);
        $amount = is_numeric($invoice['amount'] ?? null) ? (float) $invoice['amount'] : 0.0;
        $balance = is_numeric($invoice['balance'] ?? null) ? (float) $invoice['balance'] : 0.0;

        return max(0.0, $status_id === 1 ? $amount : $balance);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @param array<string, array{id: string, available: float}> $invoice_map
     * @return array{array<string, mixed>, array<string, float>}
     */
    private function limitPaymentApplications(array $record, array $payload, array $invoice_map): array
    {
        $applications = [];

        foreach ($record['references'] as $path => $reference) {
            if ($reference['entity'] !== Entity::Invoices->value || ! str_ends_with($path, '.invoice_id')) {
                continue;
            }

            $invoice = $invoice_map[$reference['key']] ?? null;

            if ($invoice === null) {
                continue;
            }

            $amount_path = substr($path, 0, -strlen('invoice_id')) . 'amount';
            $requested = data_get($payload, $amount_path);
            $applied = is_numeric($requested)
                ? round(min(max(0.0, (float) $requested), $invoice['available']), 6)
                : 0.0;

            if ($applied <= 0) {
                $index = (int) explode('.', $path)[1];
                unset($payload['invoices'][$index]);

                continue;
            }

            data_set($payload, $amount_path, $applied);
            $applications[$reference['key']] = round(
                ($applications[$reference['key']] ?? 0.0) + $applied,
                6,
            );
        }

        if (isset($payload['invoices']) && is_array($payload['invoices'])) {
            $payload['invoices'] = array_values($payload['invoices']);
        }

        return [$payload, $applications];
    }

    /**
     * @param array<string, array{id: string, available: float}> $invoice_map
     * @param array<string, float> $applications
     */
    private function applyInvoicePaymentsToMap(array &$invoice_map, array $applications): void
    {
        foreach ($applications as $invoice_key => $amount) {
            if (isset($invoice_map[$invoice_key])) {
                $invoice_map[$invoice_key]['available'] = round(
                    max(0.0, $invoice_map[$invoice_key]['available'] - $amount),
                    6,
                );
            }
        }
    }

    /** @param array<string, mixed> $record */
    private function paymentIdempotencyKey(array $record): string
    {
        return 'harvest-' . substr(hash('sha256', (string) $record['key']), 0, 56);
    }

    /**
     * @param array<string, string> $project_map
     * @param array<string, mixed> $record
     */
    private function addProjectToMap(array &$project_map, array $record, string $hashed_id): void
    {
        $key = $record['key'] ?? null;

        if (is_string($key) && $key !== '') {
            $project_map[$key] = $hashed_id;
        }
    }

    private function responseHashedId(Response $response): string|int|null
    {
        $hashed_id = $response->json('data.hashed_id') ?? $response->json('data.id');

        return is_string($hashed_id) || is_int($hashed_id) ? $hashed_id : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function performActions(Entity $entity, array $record, mixed $id, string $api_token): void
    {
        /** @var array<int, string> $actions */
        $actions = $record['actions'] ?? [];

        if ($actions === []) {
            return;
        }

        if (! is_string($id) && ! is_int($id)) {
            throw new RuntimeException('The API did not return an ID required to apply the record status.');
        }

        foreach ($actions as $action) {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-TOKEN' => $api_token])
                ->get($this->apiEndpoint($entity) . '/' . $id . '/' . $action);

            if ($response->failed()) {
                throw new RuntimeException($this->failureMessage("{$record['label']} action [{$action}]", $response));
            }

            $this->components->info("Applied {$action} status: {$record['label']}");
        }
    }

    /**
     * @param array<int, array<string, mixed>> $invoice_records
     * @return array{created: int, failed: int}
     */
    private function createTaxRates(array $invoice_records, string $api_token): array
    {
        $counters = ['created' => 0, 'failed' => 0];

        foreach ($this->invoiceTaxRates($invoice_records) as $tax_rate) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-TOKEN' => $api_token])
                    ->post(rtrim(self::API_ENDPOINT, '/') . '/tax_rates', $tax_rate);

                if ($response->status() === 422) {
                    $this->components->info("Tax rate already exists: {$tax_rate['name']}");

                    continue;
                }

                if ($response->failed()) {
                    $this->components->error($this->failureMessage("tax rate {$tax_rate['name']}", $response));
                    $counters['failed']++;

                    continue;
                }

                $this->components->info("Created tax rate: {$tax_rate['name']}");
                $counters['created']++;
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Unable to create tax rate {$tax_rate['name']}: {$exception->getMessage()}");
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /**
     * @param array<int, array<string, mixed>> $invoice_records
     * @return array<int, array{name: string, rate: float}>
     */
    private function invoiceTaxRates(array $invoice_records): array
    {
        $tax_rates = [];

        foreach ($invoice_records as $record) {
            /** @var array<string, mixed> $payload */
            $payload = $record['payload'];
            $tax_sources = [$payload];

            if (isset($payload['line_items']) && is_array($payload['line_items'])) {
                foreach ($payload['line_items'] as $line_item) {
                    if (is_array($line_item)) {
                        $tax_sources[] = $line_item;
                    }
                }
            }

            foreach ($tax_sources as $tax_source) {
                foreach ([1, 2, 3] as $index) {
                    $name = $tax_source["tax_name{$index}"] ?? null;
                    $rate = $tax_source["tax_rate{$index}"] ?? null;

                    if (! is_string($name) || $name === '' || ! is_numeric($rate) || (float) $rate <= 0) {
                        continue;
                    }

                    $tax_rates[mb_strtolower($name) . '|' . (float) $rate] = [
                        'name' => $name,
                        'rate' => (float) $rate,
                    ];
                }
            }
        }

        return array_values($tax_rates);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, string>> $created_ids
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: float}> $invoice_map
     * @param array<string, string> $project_map
     * @param array<string, string> $unmatched_clients
     * @param array<string, string> $unmatched_invoices
     * @param array<string, string> $unmatched_projects
     * @return array<string, mixed>
     */
    private function resolveReferences(
        array $record,
        array $created_ids,
        array $client_map,
        array $invoice_map,
        array $project_map,
        array &$unmatched_clients,
        array &$unmatched_invoices,
        array &$unmatched_projects,
    ): array {
        /** @var array<string, mixed> $payload */
        $payload = $record['payload'];

        /** @var array<string, array{entity: string, key: string, name?: string}> $references */
        $references = $record['references'];

        foreach ($references as $path => $reference) {
            $id = match ($reference['entity']) {
                Entity::Clients->value => $client_map[$reference['key']] ?? null,
                Entity::Invoices->value => ($invoice_map[$reference['key']]['id'] ?? null)
                    ?? $created_ids[$reference['entity']][$reference['key']]
                    ?? null,
                Entity::Projects->value => $project_map[$reference['key']]
                    ?? $created_ids[$reference['entity']][$reference['key']]
                    ?? null,
                default => $created_ids[$reference['entity']][$reference['key']] ?? null,
            };

            if ($id === null) {
                if ($reference['entity'] === Entity::Clients->value) {
                    $unmatched_clients[$reference['key']] = $reference['name'] ?? $reference['key'];
                }

                if ($reference['entity'] === Entity::Invoices->value) {
                    $unmatched_invoices[$reference['key']] = $reference['name'] ?? $reference['key'];
                }

                if ($reference['entity'] === Entity::Projects->value) {
                    $unmatched_projects[$reference['key']] = $reference['name'] ?? $reference['key'];
                }

                continue;
            }

            data_set($payload, $path, $id);
        }

        return $payload;
    }

    /** @param array<string, string> $unmatched_invoices */
    private function reportUnmatchedInvoices(array $unmatched_invoices): void
    {
        if ($unmatched_invoices === []) {
            return;
        }

        ksort($unmatched_invoices);
        $this->newLine();
        $this->components->warn(sprintf(
            '%d Harvest invoice ID(s) could not be matched to an Invoice Ninja invoice:',
            count($unmatched_invoices),
        ));

        foreach ($unmatched_invoices as $invoice_id) {
            $this->line(" - {$invoice_id}");
        }
    }

    /** @param array<string, string> $unmatched_projects */
    private function reportUnmatchedProjects(array $unmatched_projects): void
    {
        if ($unmatched_projects === []) {
            return;
        }

        ksort($unmatched_projects);
        $this->newLine();
        $this->components->warn(sprintf(
            '%d Harvest project(s) could not be matched to an Invoice Ninja project:',
            count($unmatched_projects),
        ));

        foreach ($unmatched_projects as $project) {
            $this->line(" - {$project}");
        }
    }

    /** @param array<string, array<int, array<string, mixed>>> $records */
    private function shouldBuildInvoiceMap(array $records): bool
    {
        return ($records[Entity::InvoicePayments->value] ?? []) !== [];
    }

    /** @param array<string, array<int, array<string, mixed>>> $records */
    private function shouldBuildProjectMap(array $records): bool
    {
        return ($records[Entity::TimeEntries->value] ?? []) !== [];
    }

    /** @param array<string, string> $unmatched_clients */
    private function reportUnmatchedClients(array $unmatched_clients): void
    {
        if ($unmatched_clients === []) {
            return;
        }

        ksort($unmatched_clients);
        $this->newLine();
        $this->components->warn(sprintf(
            '%d client name(s) could not be matched to an Invoice Ninja client ID:',
            count($unmatched_clients),
        ));

        foreach ($unmatched_clients as $name) {
            $this->line(" - {$name}");
        }
    }

    private function clientNameKey(string $name): string
    {
        return $this->referenceKey($name);
    }

    private function referenceKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function projectReferenceKey(string $client_key, string $project): string
    {
        return $this->referenceKey($client_key) . '|' . $this->referenceKey($project);
    }

    /**
     * @param array<int, Entity> $entities
     * @return array<int, Entity>
     */
    private function entitiesWithTimeEntryProjects(array $entities): array
    {
        if (! in_array(Entity::TimeEntries, $entities, true)
            || in_array(Entity::Projects, $entities, true)) {
            return $entities;
        }

        $entities[] = Entity::Projects;

        return array_values(array_filter(
            Entity::importOrder(),
            fn(Entity $entity): bool => in_array($entity, $entities, true),
        ));
    }

    private function apiEndpoint(Entity $entity): string
    {
        return rtrim(self::API_ENDPOINT, '/') . '/' . $entity->endpoint();
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    private function failureMessage(string $name, Response $response): string
    {
        $message = $response->json('message');
        $errors = $response->json('errors');
        $details = [];

        if (is_string($message) && $message !== '') {
            $details[] = $message;
        }

        if (is_array($errors) && $errors !== []) {
            $details[] = collect($errors)
                ->flatten()
                ->filter(fn(mixed $error): bool => is_string($error) && $error !== '')
                ->implode(' ');
        }

        $details = array_values(array_unique(array_filter($details)));
        $detail = $details === [] ? '' : ': ' . implode(' ', $details);

        return "Unable to import {$name}: API returned HTTP {$response->status()}{$detail}";
    }
}
