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

namespace App\Import\Harvest;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use League\Csv\Reader;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class CsvImporter
{
    private const CLIENT_HEADERS = ['client name', 'address'];

    private const CONTACT_HEADERS = [
        'client',
        'first name',
        'last name',
        'title',
        'email',
        'office phone',
        'mobile phone',
        'fax',
    ];

    public function __construct(private readonly CsvTransformer $transformer) {}

    /**
     * @param array<int, Entity>|null $entities
     * @return array{
     *     records: array<string, array<int, array<string, mixed>>>,
     *     files: array<string, array<int, string>>,
     *     unsupported_files: array<int, string>,
     *     unmatched_contacts: array<int, array<string, string>>
     * }
     */
    public function build(string $directory, ?array $entities = null, bool $resolve_currency = false): array
    {
        $directory = $this->resolveDirectory($directory);
        $entities ??= Entity::importOrder();
        $rows = [];
        $files = [];
        $unsupported_files = [];

        $csv_files = collect(File::allFiles($directory))
            ->filter(fn(SplFileInfo $file): bool => strtolower($file->getExtension()) === 'csv')
            ->sortBy(fn(SplFileInfo $file): string => strtolower($file->getPathname()))
            ->values();

        if ($csv_files->isEmpty()) {
            throw new RuntimeException("No CSV files were found in the Harvest import directory: {$directory}");
        }

        foreach ($csv_files as $file) {
            $reader = $this->open($file->getPathname());
            $headers = array_map(
                fn(string $header): string => $this->normalizeHeader($header),
                $reader->getHeader(),
            );
            $kind = $this->classify($file, $headers);

            if ($kind === null) {
                $unsupported_files[] = $file->getFilename();

                continue;
            }

            $rows[$kind] ??= [];
            $files[$kind] ??= [];
            array_push($rows[$kind], ...$this->read($reader, $file->getPathname()));
            $files[$kind][] = $file->getFilename();
        }

        $result = $this->transformer->transform($rows, $entities, $resolve_currency);
        $record_count = array_sum(array_map('count', $result['records']));

        if ($record_count === 0) {
            throw new RuntimeException(sprintf(
                'No importable Harvest records were found for: %s.',
                implode(', ', array_map(fn(Entity $entity): string => $entity->value, $entities)),
            ));
        }

        return [
            'records' => $result['records'],
            'files' => $files,
            'unsupported_files' => $unsupported_files,
            'unmatched_contacts' => $result['unmatched_contacts'],
        ];
    }

    /** @param array<int, string> $headers */
    private function classify(SplFileInfo $file, array $headers): ?string
    {
        $name = strtolower($file->getFilename());

        if ($this->hasHeaders($headers, self::CLIENT_HEADERS)) {
            return 'clients';
        }

        if ($this->hasHeaders($headers, self::CONTACT_HEADERS)) {
            return 'contacts';
        }

        if ($this->hasAny($headers, ['accepted date', 'declined date'])
            && $this->hasAny($headers, ['client', 'client name'])
            && $this->hasAny($headers, ['id', 'number', 'estimate id', 'estimate number'])) {
            return 'estimates';
        }

        if (str_contains($name, 'invoice') && str_contains($name, 'line')) {
            return 'invoice_lines';
        }

        if (str_contains($name, 'estimate') && str_contains($name, 'line')) {
            return 'estimate_lines';
        }

        if (str_contains($name, 'payment')) {
            return $this->hasAny($headers, ['invoice id', 'invoice number', 'invoice']) ? 'invoice_payments' : null;
        }

        if (str_contains($name, 'expense') && str_contains($name, 'categor')) {
            return 'expense_categories';
        }

        if ((str_contains($name, 'invoice') || str_contains($name, 'estimate')) && str_contains($name, 'categor')) {
            return 'task_types';
        }

        if (str_contains($name, 'estimate')) {
            return $this->hasAny($headers, ['client', 'client name'])
                && $this->hasAny($headers, ['id', 'number', 'estimate id', 'estimate number']) ? 'estimates' : null;
        }

        if (str_contains($name, 'invoice')) {
            return $this->hasAny($headers, ['client', 'client name'])
                && $this->hasAny($headers, ['id', 'number', 'invoice id', 'invoice number']) ? 'invoices' : null;
        }

        if (str_contains($name, 'project')
            && $this->hasAny($headers, ['client', 'client name'])
            && $this->hasAny($headers, ['project', 'project name'])) {
            return 'projects';
        }

        if ((str_contains($name, 'time') || str_contains($name, 'timesheet')) && $this->isTimeExport($headers)) {
            return 'time_entries';
        }

        if (str_contains($name, 'expense') && $this->isExpenseExport($headers)) {
            return 'expenses';
        }

        if ((str_contains($name, 'people') || str_contains($name, 'team') || str_contains($name, 'user')) && $this->isUserExport($headers)) {
            return 'users';
        }

        if (str_contains($name, 'task') && $this->hasAny($headers, ['task', 'task name', 'name'])) {
            return 'task_types';
        }

        if ($this->isTimeExport($headers)) {
            return 'time_entries';
        }

        if ($this->isExpenseExport($headers)) {
            return 'expenses';
        }

        if ($this->hasHeaders($headers, ['client', 'project']) && ! $this->hasAny($headers, ['date', 'hours', 'cost', 'amount'])) {
            return 'projects';
        }

        if ($this->isUserExport($headers) && ! $this->hasAny($headers, ['client'])) {
            return 'users';
        }

        if ($this->hasHeaders($headers, ['invoice number', 'description', 'quantity']) && $this->hasAny($headers, ['unit price', 'rate', 'cost'])) {
            return 'invoice_lines';
        }

        return null;
    }

    /** @param array<int, string> $headers */
    private function isTimeExport(array $headers): bool
    {
        return $this->hasHeaders($headers, ['date', 'client', 'project', 'task', 'hours']);
    }

    /** @param array<int, string> $headers */
    private function isExpenseExport(array $headers): bool
    {
        return $this->hasHeaders($headers, ['date', 'client', 'project'])
            && $this->hasAny($headers, ['category', 'expense category'])
            && $this->hasAny($headers, ['cost', 'total cost', 'amount']);
    }

    /** @param array<int, string> $headers */
    private function isUserExport(array $headers): bool
    {
        return $this->hasHeaders($headers, ['first name', 'last name', 'email']);
    }

    private function resolveDirectory(string $directory): string
    {
        $directory = trim($directory);
        $candidates = array_unique([
            $directory,
            str_replace('\ ', ' ', $directory),
        ]);

        foreach ($candidates as $candidate) {
            if (File::isDirectory($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        throw new InvalidArgumentException("Harvest import directory does not exist: {$directory}");
    }

    private function open(string $path): Reader
    {
        try {
            $reader = Reader::from($path, 'r');
            $reader->setHeaderOffset(0);
            $reader->skipEmptyRecords();
            $reader->getHeader();

            return $reader;
        } catch (\Throwable $exception) {
            throw $this->readException($path, $exception);
        }
    }

    /** @return array<int, array<string, string>> */
    private function read(Reader $reader, string $path): array
    {
        try {
            $rows = [];

            foreach ($reader->getRecords() as $record) {
                $row = [];

                foreach ($record as $header => $value) {
                    $row[$this->normalizeHeader((string) $header)] = trim((string) $value);
                }

                if (array_filter($row, fn(string $value): bool => $value !== '') !== []) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } catch (\Throwable $exception) {
            throw $this->readException($path, $exception);
        }
    }

    private function readException(string $path, \Throwable $exception): RuntimeException
    {
        return new RuntimeException(
            'Unable to read Harvest CSV [' . basename($path) . ']: ' . $exception->getMessage(),
            previous: $exception,
        );
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $required_headers
     */
    private function hasHeaders(array $headers, array $required_headers): bool
    {
        return array_diff($required_headers, $headers) === [];
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $possible_headers
     */
    private function hasAny(array $headers, array $possible_headers): bool
    {
        return array_intersect($possible_headers, $headers) !== [];
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = preg_replace('/\s+/', ' ', trim($header)) ?? trim($header);

        return strtolower($header);
    }
}
