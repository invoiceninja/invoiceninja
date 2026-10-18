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
use InvalidArgumentException;
use League\Csv\Reader;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class CsvImporter
{
    private const CLIENT_HEADERS = ['first name', 'last name', 'email', 'company'];

    private const INVOICE_HEADERS = ['client', 'invoice #', 'date of creation'];

    public function __construct(private readonly CsvTransformer $transformer) {}

    /**
     * @param array<int, Entity>|null $entities
     * @return array{
     *     records: array<string, array<int, array<string, mixed>>>,
     *     files: array<string, array<int, string>>,
     *     unsupported_files: array<int, string>
     * }
     */
    public function build(string $directory, ?array $entities = null): array
    {
        $directory = $this->resolveDirectory($directory);
        $entities ??= Entity::importOrder();
        $rows = [];
        $files = [];
        $unsupported_files = [];

        $csv_files = collect(File::allFiles($directory))
            ->filter(fn(SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['csv', 'tsv'], true))
            ->sortBy(fn(SplFileInfo $file): string => strtolower($file->getPathname()))
            ->values();

        if ($csv_files->isEmpty()) {
            throw new RuntimeException("No CSV files were found in the Pancake import directory: {$directory}");
        }

        foreach ($csv_files as $file) {
            $reader = $this->open($file->getPathname());
            $headers = array_map(
                fn(string $header): string => $this->normalizeHeader($header),
                $reader->getHeader(),
            );
            $kind = $this->classify($headers);

            if ($kind === null) {
                $unsupported_files[] = $file->getFilename();

                continue;
            }

            $rows[$kind] ??= [];
            $files[$kind] ??= [];
            array_push($rows[$kind], ...$this->read($reader, $file->getPathname()));
            $files[$kind][] = $file->getFilename();
        }

        $records = $this->transformer->transform($rows, $entities);

        if (array_sum(array_map('count', $records)) === 0) {
            throw new RuntimeException(sprintf(
                'No importable Pancake records were found for: %s.',
                implode(', ', array_map(fn(Entity $entity): string => $entity->value, $entities)),
            ));
        }

        return [
            'records' => $records,
            'files' => $files,
            'unsupported_files' => $unsupported_files,
        ];
    }

    /** @param array<int, string> $headers */
    private function classify(array $headers): ?string
    {
        if ($this->hasHeaders($headers, self::INVOICE_HEADERS)) {
            return 'invoices';
        }

        if ($this->hasHeaders($headers, self::CLIENT_HEADERS)) {
            return 'clients';
        }

        return null;
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

        throw new InvalidArgumentException("Pancake import directory does not exist: {$directory}");
    }

    private function open(string $path): Reader
    {
        try {
            $reader = Reader::from($path, 'r');
            $reader->setDelimiter($this->detectDelimiter($path));
            $reader->setHeaderOffset(0);
            $reader->skipEmptyRecords();
            $reader->getHeader();

            return $reader;
        } catch (Throwable $exception) {
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
                    $row[$this->normalizeHeader((string) $header)] = $this->trim((string) $value);
                }

                if (array_filter($row, fn(string $value): bool => $value !== '') !== []) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } catch (Throwable $exception) {
            throw $this->readException($path, $exception);
        }
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the CSV file.');
        }

        try {
            $header = fgets($handle) ?: '';
        } finally {
            fclose($handle);
        }

        $counts = [
            ',' => substr_count($header, ','),
            "\t" => substr_count($header, "\t"),
            ';' => substr_count($header, ';'),
        ];
        arsort($counts);
        $delimiter = array_key_first($counts);

        return $delimiter;
    }

    private function readException(string $path, Throwable $exception): RuntimeException
    {
        return new RuntimeException(
            'Unable to read Pancake CSV [' . basename($path) . ']: ' . $exception->getMessage(),
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

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = preg_replace('/\s+/', ' ', $this->trim($header)) ?? $this->trim($header);

        return mb_strtolower($header);
    }

    private function trim(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);
    }
}
