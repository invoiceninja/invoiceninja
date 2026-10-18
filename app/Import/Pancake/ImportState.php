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

use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportState
{
    /** @var array{version: int, fingerprint: string, mappings: array<string, array<string, string>>, failures: array<int, array<string, mixed>>} */
    private array $state;

    public function __construct(
        private readonly string $path,
        private readonly string $fingerprint,
        private readonly bool $persistent = true,
        bool $restart = false,
    ) {
        if ($restart && $this->persistent && File::exists($this->path)) {
            File::delete($this->path);
        }

        $this->state = $this->load();
    }

    public function id(DatabaseEntity $entity, string|int $source_id): ?string
    {
        return $this->state['mappings'][$entity->value][(string) $source_id] ?? null;
    }

    public function has(DatabaseEntity $entity, string|int $source_id): bool
    {
        return $this->id($entity, $source_id) !== null;
    }

    public function remember(DatabaseEntity $entity, string|int $source_id, string|int $destination_id): void
    {
        $this->state['mappings'][$entity->value][(string) $source_id] = (string) $destination_id;
        $this->state['failures'] = array_values(array_filter(
            $this->state['failures'],
            fn(array $failure): bool => ($failure['entity'] ?? null) !== $entity->value
                || (string) ($failure['source_id'] ?? '') !== (string) $source_id,
        ));
        $this->persist();
    }

    /** @param array<string, mixed> $failure */
    public function recordFailure(array $failure): void
    {
        $this->state['failures'][] = $failure;
        $this->persist();
    }

    /** @return array<int, array<string, mixed>> */
    public function failures(): array
    {
        return $this->state['failures'];
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return array_map('count', $this->state['mappings']);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array{version: int, fingerprint: string, mappings: array<string, array<string, string>>, failures: array<int, array<string, mixed>>} */
    private function load(): array
    {
        $empty = [
            'version' => 1,
            'fingerprint' => $this->fingerprint,
            'mappings' => [],
            'failures' => [],
        ];

        if (! $this->persistent || ! File::exists($this->path)) {
            return $empty;
        }

        $decoded = json_decode(File::get($this->path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("The Pancake import state file is not valid JSON: {$this->path}");
        }

        if (($decoded['version'] ?? null) !== 1) {
            throw new RuntimeException("The Pancake import state file has an unsupported version: {$this->path}");
        }

        if (($decoded['fingerprint'] ?? null) !== $this->fingerprint) {
            throw new RuntimeException(
                'The Pancake import state belongs to a different source database, business identity, or API target. '
                . 'Use a different --state path or pass --restart.',
            );
        }

        $decoded['mappings'] = is_array($decoded['mappings'] ?? null) ? $decoded['mappings'] : [];
        $decoded['failures'] = is_array($decoded['failures'] ?? null) ? $decoded['failures'] : [];

        /** @var array{version: int, fingerprint: string, mappings: array<string, array<string, string>>, failures: array<int, array<string, mixed>>} $decoded */
        return $decoded;
    }

    private function persist(): void
    {
        if (! $this->persistent) {
            return;
        }

        $directory = dirname($this->path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0o755, true);
        }

        $temporary_path = $this->path . '.tmp';
        $json = json_encode(
            $this->state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (File::put($temporary_path, $json . "\n") === false || ! File::move($temporary_path, $this->path)) {
            throw new RuntimeException("Unable to persist Pancake import state: {$this->path}");
        }
    }
}
