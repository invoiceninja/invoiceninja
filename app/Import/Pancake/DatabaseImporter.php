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

use Closure;
use RuntimeException;
use Throwable;

class DatabaseImporter
{
    /**
     * @param array<int, DatabaseEntity> $entities
     * @param Closure(string, string, array<string, mixed>): void|null $reporter
     * @return array{created: int, reused: int, skipped: int, failed: int, entities: array<string, array{created: int, reused: int, skipped: int, failed: int}>}
     */
    public function import(
        DatabaseSource $source,
        ApiClient $api,
        ImportState $state,
        array $entities,
        bool $dry_run = false,
        bool $abort_on_failure = false,
        ?Closure $reporter = null,
    ): array {
        $totals = ['created' => 0, 'reused' => 0, 'skipped' => 0, 'failed' => 0];
        $entity_totals = [];
        $aborted = false;
        /** @var array<string, float|null> $invoice_balances */
        $invoice_balances = [];

        foreach ($entities as $entity) {
            $counters = ['created' => 0, 'reused' => 0, 'skipped' => 0, 'failed' => 0];

            try {
                $records = $source->records($entity);
            } catch (Throwable $exception) {
                $this->failure($state, $entity, '*', $exception, $counters, $reporter);
                $this->mergeCounters($totals, $counters);
                $entity_totals[$entity->value] = $counters;

                if ($abort_on_failure) {
                    break;
                }

                continue;
            }

            foreach ($records as $record) {
                $source_id = (string) $record['source_id'];
                $label = (string) $record['label'];

                if ($state->has($entity, $source_id)) {
                    $counters['skipped']++;
                    $this->report($reporter, 'skip', "Already imported {$entity->label()}: {$label}", $record);

                    continue;
                }

                try {
                    $payload = $this->resolveReferences($record, $state);

                    if ($dry_run) {
                        $this->validateUpload($entity, $record);
                        $destination_id = 'dry-' . substr(hash('sha256', "{$entity->value}:{$source_id}"), 0, 16);
                        $state->remember($entity, $source_id, $destination_id);
                        $counters['created']++;
                        $this->report($reporter, 'dry-run', "Would create {$entity->label()}: {$label}", [
                            ...$record,
                            'payload' => $payload,
                        ]);

                        continue;
                    }

                    if ($entity === DatabaseEntity::Company) {
                        $destination_id = $api->updateCurrentCompany($payload);

                        if (is_array($record['upload'] ?? null) && ($record['upload']['path'] ?? '') !== '') {
                            $path = (string) $record['upload']['path'];
                            $this->validateUpload($entity, $record);

                            $api->updateCompanyLogo(
                                $destination_id,
                                $path,
                                (string) $record['upload']['filename'],
                            );
                        }
                    } elseif ($entity === DatabaseEntity::Documents) {
                        $upload = $record['upload'];
                        $path = (string) ($upload['path'] ?? '');
                        $this->validateUpload($entity, $record);

                        $parent_entity = $upload['parent_entity'] ?? null;

                        if (! $parent_entity instanceof DatabaseEntity) {
                            throw new RuntimeException('Pancake attachment has no valid destination entity.');
                        }

                        $destination_id = (string) ($payload['destination_id'] ?? '');
                        $api->upload(
                            $parent_entity,
                            $destination_id,
                            $path,
                            (string) $upload['filename'],
                            (bool) $upload['is_public'],
                        );
                    } else {
                        if ($entity === DatabaseEntity::Payments) {
                            $payload = $this->limitPaymentAllocation(
                                $payload,
                                $api,
                                $invoice_balances,
                                isset($record['payment_percentage'])
                                    ? (float) $record['payment_percentage']
                                    : null,
                            );
                        }

                        $result = $api->findOrCreate(
                            $entity,
                            $payload,
                            is_array($record['query'] ?? null) ? $record['query'] : [],
                        );
                        $destination_id = $result['id'];

                        if ($result['reused']) {
                            $counters['reused']++;
                            $state->remember($entity, $source_id, $destination_id);
                            $this->report($reporter, 'reuse', "Using existing {$entity->label()}: {$label}", $record);

                            continue;
                        }
                    }

                    $state->remember($entity, $source_id, $destination_id);
                    $counters['created']++;
                    $this->report($reporter, 'create', "Imported {$entity->label()}: {$label}", $record);
                } catch (Throwable $exception) {
                    $this->failure($state, $entity, $source_id, $exception, $counters, $reporter, $label);

                    if ($abort_on_failure) {
                        $aborted = true;
                        break;
                    }
                }
            }

            $this->mergeCounters($totals, $counters);
            $entity_totals[$entity->value] = $counters;

            if ($aborted) {
                break;
            }
        }

        return [...$totals, 'entities' => $entity_totals];
    }

    /** @param array<string, mixed> $record */
    private function validateUpload(DatabaseEntity $entity, array $record): void
    {
        $upload = $record['upload'] ?? null;

        if (! is_array($upload) || ($upload['path'] ?? '') === '') {
            if ($entity === DatabaseEntity::Documents) {
                throw new RuntimeException('Pancake attachment has no source path.');
            }

            return;
        }

        $path = (string) $upload['path'];

        if (! is_file($path) || ! is_readable($path)) {
            $label = $entity === DatabaseEntity::Company ? 'company logo' : 'attachment';

            throw new RuntimeException("Pancake {$label} is missing or unreadable: {$path}");
        }
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function resolveReferences(array $record, ImportState $state): array
    {
        $payload = $record['payload'];

        foreach ($record['references'] as $reference) {
            $entity = DatabaseEntity::from((string) $reference['entity']);
            $source_id = (string) $reference['source_id'];
            $destination_id = $state->id($entity, $source_id);

            if ($destination_id === null) {
                if ((bool) $reference['required']) {
                    throw new RuntimeException(sprintf(
                        'Missing required %s mapping for Pancake source ID [%s].',
                        $entity->label(),
                        $source_id,
                    ));
                }

                continue;
            }

            data_set($payload, (string) $reference['path'], $destination_id);
        }

        return $payload;
    }

    /**
     * Preserve the complete Pancake payment while limiting its invoice allocation to the
     * remaining balance. Any genuine overpayment becomes unapplied client credit.
     *
     * @param array<string, mixed> $payload
     * @param array<string, float|null> $invoice_balances
     * @return array<string, mixed>
     */
    private function limitPaymentAllocation(
        array $payload,
        ApiClient $api,
        array &$invoice_balances,
        ?float $payment_percentage = null,
    ): array {
        $invoice_id = data_get($payload, 'invoices.0.invoice_id');

        if (! is_string($invoice_id) || $invoice_id === '') {
            return $payload;
        }

        if ($payment_percentage !== null) {
            $invoice_amount = $api->invoiceAmount($invoice_id);

            if ($invoice_amount !== null) {
                $payment_amount = round(max(0.0, $invoice_amount * ($payment_percentage / 100)), 6);
                $payload['amount'] = $payment_amount;
                data_set($payload, 'invoices.0.amount', $payment_amount);
            }
        }

        if (! array_key_exists($invoice_id, $invoice_balances)) {
            $invoice_balances[$invoice_id] = $api->availableInvoiceAmount($invoice_id);
        }

        $available = $invoice_balances[$invoice_id];

        if ($available === null) {
            return $payload;
        }

        $payment_amount = max(0.0, (float) ($payload['amount'] ?? 0));
        $requested_allocation = max(0.0, (float) data_get($payload, 'invoices.0.amount', $payment_amount));
        $allocation = min($payment_amount, $requested_allocation, $available);

        if ($allocation <= 0) {
            unset($payload['invoices']);
        } else {
            data_set($payload, 'invoices.0.amount', $allocation);
        }

        $invoice_balances[$invoice_id] = max(0.0, $available - $allocation);

        return $payload;
    }

    /**
     * @param array{created: int, reused: int, skipped: int, failed: int} $counters
     * @param Closure(string, string, array<string, mixed>): void|null $reporter
     */
    private function failure(
        ImportState $state,
        DatabaseEntity $entity,
        string $source_id,
        Throwable $exception,
        array &$counters,
        ?Closure $reporter,
        string $label = '',
    ): void {
        $failure = [
            'entity' => $entity->value,
            'source_id' => $source_id,
            'label' => $label,
            'reason' => $exception->getMessage(),
            'exception' => $exception::class,
            'recorded_at' => now()->toIso8601String(),
        ];
        $state->recordFailure($failure);
        $counters['failed']++;
        $this->report(
            $reporter,
            'failure',
            "Failed {$entity->label()} [{$source_id}] {$label}: {$exception->getMessage()}",
            $failure,
        );
    }

    /**
     * @param array{created: int, reused: int, skipped: int, failed: int} $totals
     * @param array{created: int, reused: int, skipped: int, failed: int} $counters
     */
    private function mergeCounters(array &$totals, array $counters): void
    {
        foreach ($totals as $key => &$value) {
            $value += $counters[$key];
        }
        unset($value);
    }

    /**
     * @param Closure(string, string, array<string, mixed>): void|null $reporter
     * @param array<string, mixed> $context
     */
    private function report(?Closure $reporter, string $type, string $message, array $context): void
    {
        if ($reporter) {
            $reporter($type, $message, $context);
        }
    }
}
