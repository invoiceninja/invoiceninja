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

use App\Import\Pancake\CsvImporter;
use App\Import\Pancake\Entity;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportPancake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:import-pancake
                            {api_token : Invoice Ninja API token used to create records}
                            {directory : Directory containing Pancake client and invoice CSV exports}
                            {--entities= : Comma-separated list: clients, invoices, recurring_invoices, payments. Omit to import all}
                            {--abort-on-failure : Stop importing after the first failed record}
                            {--dry-run : Resolve existing IDs and print records without creating anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Pancake CSV exports into Invoice Ninja through the API';

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
            $entities = Entity::fromOption($this->optionString('entities'));
            $result = $this->importer->build((string) $this->argument('directory'), $entities);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Prepared %d record(s) across %d Pancake entity type(s) from %d CSV file(s).',
            array_sum(array_map('count', $result['records'])),
            count(array_filter($result['records'])),
            array_sum(array_map('count', $result['files'])),
        ));

        foreach ($result['unsupported_files'] as $file) {
            $this->components->warn("Skipping unrecognized CSV: {$file}");
        }

        if (($result['records'][Entity::RecurringInvoices->value] ?? []) !== []) {
            $this->components->warn('Recurring invoices will be imported as drafts and will not be started automatically.');
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($result['records'], $entities, $api_token);
        }

        return $this->createRecords($result['records'], $entities, $api_token);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     * @param array<int, Entity> $entities
     */
    private function dryRun(array $records, array $entities, string $api_token): int
    {
        $client_map = $this->buildClientMap($api_token);
        $invoice_map = $this->needsInvoiceMap($entities) ? $this->buildDocumentMap(Entity::Invoices, $api_token) : [];
        $recurring_invoice_map = in_array(Entity::RecurringInvoices, $entities, true)
            ? $this->buildDocumentMap(Entity::RecurringInvoices, $api_token)
            : [];
        $created_ids = [];
        $unmatched_clients = [];
        $unmatched_invoices = [];

        foreach ($records as &$entity_records) {
            foreach ($entity_records as &$record) {
                $record['payload'] = $this->resolveReferences(
                    $record,
                    $created_ids,
                    $client_map,
                    $invoice_map,
                    $unmatched_clients,
                    $unmatched_invoices,
                );
            }
            unset($record);
        }
        unset($entity_records);

        $this->line(json_encode(
            $records,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $this->components->info('Dry run complete; no records were created.');
        $this->reportUnmatched('client', $unmatched_clients);
        $this->reportUnmatched('invoice', $unmatched_invoices);

        if ($recurring_invoice_map !== []) {
            $this->components->info(sprintf(
                'Found %d existing recurring invoice number(s).',
                count($recurring_invoice_map),
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     * @param array<int, Entity> $entities
     */
    private function createRecords(array $records, array $entities, string $api_token): int
    {
        $counters = [
            'created' => 0,
            'reused' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        /** @var array<int, array{entity: string, record: string, http_status: string, fields: string, reason: string}> $failures */
        $failures = [];
        $client_map = $this->buildClientMap($api_token);
        $invoice_map = $this->needsInvoiceMap($entities) ? $this->buildDocumentMap(Entity::Invoices, $api_token) : [];
        $recurring_invoice_map = in_array(Entity::RecurringInvoices, $entities, true)
            ? $this->buildDocumentMap(Entity::RecurringInvoices, $api_token)
            : [];
        $created_ids = [];
        $unmatched_clients = [];
        $unmatched_invoices = [];
        $aborted = false;

        foreach ($entities as $entity) {
            foreach ($records[$entity->value] ?? [] as $record_index => $record) {
                $existing_id = $this->existingId(
                    $entity,
                    $record,
                    $client_map,
                    $invoice_map,
                    $recurring_invoice_map,
                );

                if ($existing_id !== null) {
                    $this->rememberExisting(
                        $entity,
                        $record,
                        $existing_id,
                        $created_ids,
                        $client_map,
                        $invoice_map,
                        $recurring_invoice_map,
                    );
                    $this->components->info(sprintf(
                        'Using existing %s: %s',
                        $entity->destinationLabel(),
                        (string) $record['label'],
                    ));
                    $counters['reused']++;

                    continue;
                }

                /** @var array<string, mixed> $payload */
                $payload = $record['payload'];

                try {
                    $payload = $this->resolveReferences(
                        $record,
                        $created_ids,
                        $client_map,
                        $invoice_map,
                        $unmatched_clients,
                        $unmatched_invoices,
                    );

                    if (! $this->hasRequiredReferences($entity, $payload)) {
                        $failure = $this->localFailure($entity, (string) $record['key'], $record_index);
                        $failures[] = $failure;
                        $this->reportFailureInline($failure);
                        $counters['failed']++;

                        if ($this->option('abort-on-failure')) {
                            $aborted = true;
                            break;
                        }

                        continue;
                    }

                    if ($entity === Entity::Payments) {
                        $payload = $this->limitPayment($record, $payload, $invoice_map);

                        if (($payload['amount'] ?? 0) <= 0) {
                            $this->components->warn('Skipping a Pancake payment with no remaining invoice balance.');
                            $counters['skipped']++;

                            continue;
                        }

                        $payload['idempotency_key'] = $this->paymentIdempotencyKey($record);
                    }

                    $response = Http::acceptJson()
                        ->withHeaders(['X-API-TOKEN' => $api_token])
                        ->post($this->apiCreateEndpoint($entity, $record), $payload);

                    if ($response->failed()) {
                        $failure = $this->responseFailure(
                            $entity,
                            (string) $record['key'],
                            $record_index,
                            $response,
                        );
                        $failures[] = $failure;
                        $this->reportFailureInline($failure);
                        $counters['failed']++;

                        if ($this->option('abort-on-failure')) {
                            $aborted = true;
                            break;
                        }

                        continue;
                    }

                    $id = $this->responseHashedId($response);

                    if ($id !== null) {
                        $this->rememberCreated(
                            $entity,
                            $record,
                            $response,
                            $id,
                            $created_ids,
                            $client_map,
                            $invoice_map,
                            $recurring_invoice_map,
                        );
                    }

                    if ($entity === Entity::Payments) {
                        $this->applyPaymentToMap($record, $payload, $invoice_map);
                    }

                    $this->components->info(sprintf(
                        'Created %s: %s',
                        $entity->destinationLabel(),
                        (string) $record['label'],
                    ));
                    $counters['created']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failure = $this->exceptionFailure(
                        $entity,
                        (string) $record['key'],
                        $record_index,
                        $exception,
                    );
                    $failures[] = $failure;
                    $this->reportFailureInline($failure);
                    $counters['failed']++;

                    if ($this->option('abort-on-failure')) {
                        $aborted = true;
                        break;
                    }
                }
            }

            if ($aborted) {
                break;
            }
        }

        $this->table(array_keys($counters), [array_values($counters)]);
        $this->reportUnmatched('client', $unmatched_clients);
        $this->reportUnmatched('invoice', $unmatched_invoices);

        if ($aborted) {
            $this->components->warn('Import aborted after the first failure because --abort-on-failure was specified.');
        }

        $this->reportFailures($failures);

        return $counters['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, string> */
    private function buildClientMap(string $api_token): array
    {
        $map = [];

        foreach ($this->apiRecords(Entity::Clients, $api_token) as $client) {
            $name = $client['name'] ?? null;
            $id = $client['hashed_id'] ?? $client['id'] ?? null;

            if (is_string($name) && $name !== '' && (is_string($id) || is_int($id))) {
                $map[$this->referenceKey($name)] = (string) $id;
            }
        }

        return $map;
    }

    /** @return array<string, array{id: string, available: ?float}> */
    private function buildDocumentMap(Entity $entity, string $api_token): array
    {
        $map = [];

        foreach ($this->apiRecords($entity, $api_token) as $document) {
            $number = $document['number'] ?? null;
            $id = $document['hashed_id'] ?? $document['id'] ?? null;

            if (is_string($number) && $number !== '' && (is_string($id) || is_int($id))) {
                $map[$this->referenceKey($number)] = [
                    'id' => (string) $id,
                    'available' => $entity === Entity::Invoices
                        ? $this->availableAmount($document)
                        : null,
                ];
            }
        }

        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    private function apiRecords(Entity $entity, string $api_token): array
    {
        $records = [];
        $page = 1;
        $total_pages = 1;

        try {
            do {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-TOKEN' => $api_token])
                    ->get($this->apiEndpoint($entity), [
                        'per_page' => 1000,
                        'page' => $page,
                    ]);

                if ($response->failed()) {
                    $this->components->warn(sprintf(
                        'Unable to build the %s map: API returned HTTP %d.',
                        $entity->destinationLabel(),
                        $response->status(),
                    ));

                    break;
                }

                $data = $response->json('data', []);
                $data = is_array($data) && ! array_is_list($data) ? [$data] : $data;

                if (! is_array($data)) {
                    break;
                }

                foreach ($data as $record) {
                    if (is_array($record)) {
                        $records[] = $record;
                    }
                }

                $total_pages = max(1, (int) $response->json('meta.pagination.total_pages', 1));
                $page++;
            } while ($page <= $total_pages);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->warn(sprintf(
                'Unable to build the %s map: %s.',
                $entity->destinationLabel(),
                $exception::class,
            ));
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, string>> $created_ids
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     * @param array<string, string> $unmatched_clients
     * @param array<string, string> $unmatched_invoices
     * @return array<string, mixed>
     */
    private function resolveReferences(
        array $record,
        array $created_ids,
        array $client_map,
        array $invoice_map,
        array &$unmatched_clients,
        array &$unmatched_invoices,
    ): array {
        /** @var array<string, mixed> $payload */
        $payload = $record['payload'];
        /** @var array<string, array{entity: string, key: string, name?: string}> $references */
        $references = $record['references'];

        foreach ($references as $path => $reference) {
            $id = match ($reference['entity']) {
                Entity::Clients->value => $created_ids[Entity::Clients->value][$reference['key']]
                    ?? $client_map[$reference['key']]
                    ?? null,
                Entity::Invoices->value => $created_ids[Entity::Invoices->value][$reference['key']]
                    ?? $invoice_map[$reference['key']]['id']
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

                continue;
            }

            data_set($payload, $path, $id);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     * @param array<string, array{id: string, available: ?float}> $recurring_invoice_map
     */
    private function existingId(
        Entity $entity,
        array $record,
        array $client_map,
        array $invoice_map,
        array $recurring_invoice_map,
    ): ?string {
        if ($entity === Entity::Clients) {
            foreach (array_unique(array_merge([(string) $record['key']], $record['aliases'] ?? [])) as $alias) {
                if (isset($client_map[$alias])) {
                    return $client_map[$alias];
                }
            }
        }

        if ($entity === Entity::Invoices) {
            return $invoice_map[(string) $record['key']]['id'] ?? null;
        }

        if ($entity === Entity::RecurringInvoices) {
            return $recurring_invoice_map[(string) $record['key']]['id'] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, string>> $created_ids
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     * @param array<string, array{id: string, available: ?float}> $recurring_invoice_map
     */
    private function rememberExisting(
        Entity $entity,
        array $record,
        string $id,
        array &$created_ids,
        array &$client_map,
        array &$invoice_map,
        array &$recurring_invoice_map,
    ): void {
        $created_ids[$entity->value][(string) $record['key']] = $id;

        if ($entity === Entity::Clients) {
            foreach (array_unique(array_merge([(string) $record['key']], $record['aliases'] ?? [])) as $alias) {
                $client_map[$alias] = $id;
                $created_ids[$entity->value][$alias] = $id;
            }
        }

        if ($entity === Entity::Invoices && ! isset($invoice_map[(string) $record['key']])) {
            $invoice_map[(string) $record['key']] = ['id' => $id, 'available' => null];
        }

        if ($entity === Entity::RecurringInvoices && ! isset($recurring_invoice_map[(string) $record['key']])) {
            $recurring_invoice_map[(string) $record['key']] = ['id' => $id, 'available' => null];
        }
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, string>> $created_ids
     * @param array<string, string> $client_map
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     * @param array<string, array{id: string, available: ?float}> $recurring_invoice_map
     */
    private function rememberCreated(
        Entity $entity,
        array $record,
        Response $response,
        string $id,
        array &$created_ids,
        array &$client_map,
        array &$invoice_map,
        array &$recurring_invoice_map,
    ): void {
        $this->rememberExisting(
            $entity,
            $record,
            $id,
            $created_ids,
            $client_map,
            $invoice_map,
            $recurring_invoice_map,
        );

        if ($entity === Entity::Invoices) {
            $data = $response->json('data', []);
            $invoice_map[(string) $record['key']]['available'] = is_array($data)
                ? $this->availableAmount($data)
                : null;
        }
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     * @return array<string, mixed>
     */
    private function limitPayment(array $record, array $payload, array $invoice_map): array
    {
        $reference = $record['references']['invoices.0.invoice_id'] ?? null;

        if (! is_array($reference)) {
            return $payload;
        }

        $available = $invoice_map[(string) $reference['key']]['available'] ?? null;

        if ($available === null) {
            return $payload;
        }

        $amount = min(max(0.0, (float) ($payload['amount'] ?? 0)), max(0.0, $available));
        $payload['amount'] = $amount;
        data_set($payload, 'invoices.0.amount', $amount);

        return $payload;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @param array<string, array{id: string, available: ?float}> $invoice_map
     */
    private function applyPaymentToMap(array $record, array $payload, array &$invoice_map): void
    {
        $reference = $record['references']['invoices.0.invoice_id'] ?? null;

        if (! is_array($reference)) {
            return;
        }

        $key = (string) $reference['key'];
        $available = $invoice_map[$key]['available'] ?? null;

        if ($available !== null) {
            $invoice_map[$key]['available'] = max(0.0, $available - (float) ($payload['amount'] ?? 0));
        }
    }

    /** @param array<string, mixed> $payload */
    private function hasRequiredReferences(Entity $entity, array $payload): bool
    {
        if (in_array($entity, [Entity::Invoices, Entity::RecurringInvoices, Entity::Payments], true)
            && ! isset($payload['client_id'])) {
            return false;
        }

        return $entity !== Entity::Payments || data_get($payload, 'invoices.0.invoice_id') !== null;
    }

    /** @param array<string, mixed> $record */
    private function apiCreateEndpoint(Entity $entity, array $record): string
    {
        $suffix = match ($entity) {
            Entity::Invoices => ($record['mark_sent'] ?? false) ? '?mark_sent=true' : '',
            Entity::Payments => '?email_receipt=false',
            default => '',
        };

        return $this->apiEndpoint($entity) . $suffix;
    }

    private function apiEndpoint(Entity $entity): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/v1/' . $entity->endpoint();
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $record */
    private function paymentIdempotencyKey(array $record): string
    {
        return 'pancake-' . substr(hash('sha256', (string) $record['key']), 0, 56);
    }

    private function responseHashedId(Response $response): ?string
    {
        $id = $response->json('data.hashed_id') ?? $response->json('data.id');

        return is_string($id) || is_int($id) ? (string) $id : null;
    }

    /** @param array<string, mixed> $invoice */
    private function availableAmount(array $invoice): ?float
    {
        if ((int) ($invoice['status_id'] ?? 0) === Invoice::STATUS_DRAFT
            && isset($invoice['amount'])
            && is_numeric($invoice['amount'])) {
            return max(0.0, (float) $invoice['amount']);
        }

        if (isset($invoice['balance']) && is_numeric($invoice['balance'])) {
            return max(0.0, (float) $invoice['balance']);
        }

        if (isset($invoice['amount']) && is_numeric($invoice['amount'])) {
            $paid_to_date = isset($invoice['paid_to_date']) && is_numeric($invoice['paid_to_date'])
                ? (float) $invoice['paid_to_date']
                : 0.0;

            return max(0.0, (float) $invoice['amount'] - $paid_to_date);
        }

        return null;
    }

    /** @param array<int, Entity> $entities */
    private function needsInvoiceMap(array $entities): bool
    {
        return in_array(Entity::Invoices, $entities, true)
            || in_array(Entity::Payments, $entities, true);
    }

    /**
     * @return array{entity: string, record: string, http_status: string, fields: string, reason: string}
     */
    private function localFailure(Entity $entity, string $record_key, int $record_index): array
    {
        return [
            'entity' => $entity->value,
            'record' => $this->recordReference($entity, $record_key, $record_index),
            'http_status' => '-',
            'fields' => 'client_id/invoice_id',
            'reason' => 'Required references could not be resolved.',
        ];
    }

    /**
     * @return array{entity: string, record: string, http_status: string, fields: string, reason: string}
     */
    private function responseFailure(
        Entity $entity,
        string $record_key,
        int $record_index,
        Response $response,
    ): array {
        $errors = $response->json('errors');
        $fields = is_array($errors)
            ? implode(', ', array_filter(array_keys($errors), 'is_string'))
            : '-';

        return [
            'entity' => $entity->value,
            'record' => $this->recordReference($entity, $record_key, $record_index),
            'http_status' => (string) $response->status(),
            'fields' => $fields !== '' ? $fields : '-',
            'reason' => 'The API rejected the record.',
        ];
    }

    /**
     * @return array{entity: string, record: string, http_status: string, fields: string, reason: string}
     */
    private function exceptionFailure(
        Entity $entity,
        string $record_key,
        int $record_index,
        Throwable $exception,
    ): array {
        return [
            'entity' => $entity->value,
            'record' => $this->recordReference($entity, $record_key, $record_index),
            'http_status' => '-',
            'fields' => '-',
            'reason' => class_basename($exception) . ': ' . mb_strimwidth($exception->getMessage(), 0, 160, '…'),
        ];
    }

    private function recordReference(Entity $entity, string $record_key, int $record_index): string
    {
        $fingerprint = substr(hash('sha256', "pancake|{$entity->value}|{$record_key}"), 0, 12);

        return sprintf('#%d / %s', $record_index + 1, $fingerprint);
    }

    /**
     * @param array{entity: string, record: string, http_status: string, fields: string, reason: string} $failure
     */
    private function reportFailureInline(array $failure): void
    {
        $http_status = $failure['http_status'] === '-' ? '' : "; HTTP {$failure['http_status']}";
        $fields = $failure['fields'] === '-' ? '' : "; fields: {$failure['fields']}";

        $this->components->error(sprintf(
            'Failed %s record [%s]%s%s: %s',
            $failure['entity'],
            $failure['record'],
            $http_status,
            $fields,
            $failure['reason'],
        ));
    }

    /**
     * @param array<int, array{entity: string, record: string, http_status: string, fields: string, reason: string}> $failures
     */
    private function reportFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        $this->newLine();
        $this->components->warn(sprintf('Failure report (%d)', count($failures)));
        $this->table(
            ['entity', 'record', 'http_status', 'fields', 'reason'],
            array_map('array_values', $failures),
        );
    }

    /** @param array<string, string> $unmatched */
    private function reportUnmatched(string $entity, array $unmatched): void
    {
        if ($unmatched === []) {
            return;
        }

        ksort($unmatched);
        $this->newLine();
        $this->components->warn(sprintf(
            '%d Pancake %s reference(s) could not be matched:',
            count($unmatched),
            $entity,
        ));

        foreach ($unmatched as $name) {
            $this->line(" - {$name}");
        }
    }

    private function referenceKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
