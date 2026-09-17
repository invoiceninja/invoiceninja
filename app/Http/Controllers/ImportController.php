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

namespace App\Http\Controllers;

use App\Http\Requests\Import\ImportRequest;
use App\Http\Requests\Import\PreImportRequest;
use App\Jobs\Import\CSVIngest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param PreImportRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/preimport",
     *      operationId="preimport",
     *      tags={"imports"},
     *      summary="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      description="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The CSV file",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a reference to the file",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function preimport(PreImportRequest $request)
    {
        App::setLocale(auth()->user()->company()->getLocale());
        // Create a reference
        $hash = Str::random(32);

        $data = [
            'hash'     => $hash,
            'mappings' => [],
        ];
        /** @var UploadedFile $file */
        foreach ($request->files->get('files') as $entityType => $file) {
            $contents = $this->readFileWithProperEncoding($file->getPathname());

            Cache::put($hash . '-' . $entityType, base64_encode($contents), 1200);

            // Parse CSV
            $csv_array = $this->getCsvData($contents);

            $class_map = $this->getEntityMap($entityType);

            $hints = $this->setImportHints($entityType, $class_map::importable(), $csv_array[0]);

            $data['mappings'][$entityType] = [
                'available' => $class_map::importable(),
                'headers'   => array_slice($csv_array, 0, 2),
                'hints' => $hints,
            ];
        }

        return response()->json($data);
    }

    private function readFileWithProperEncoding(string $filePath): string
    {
        // First, read the file and check if it's already clean UTF-8
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            return '';
        }

        // Check for different UTF BOMs and handle accordingly
        $bomResult = $this->detectAndHandleUTFEncoding($contents);
        if ($bomResult !== null) {
            return $bomResult;
        }

        // Remove BOM if present (for UTF-8 BOM)
        $contents = $this->removeBOM($contents);

        // Check if it's clean UTF-8 first (no conversion needed)
        if (mb_check_encoding($contents, 'UTF-8') && $this->isValidConversion($contents)) {
            return $contents;
        }

        // Method 1: Try reading with explicit Windows-1252 context
        $context = stream_context_create([
            'file' => [
                'encoding' => 'WINDOWS-1252',
            ],
        ]);

        $contextContents = @file_get_contents($filePath, false, $context);
        if ($contextContents !== false) {
            $contextContents = $this->removeBOM($contextContents);
            $converted = mb_convert_encoding($contextContents, 'UTF-8', 'WINDOWS-1252');
            if ($this->isValidConversion($converted)) {
                return $converted;
            }
        }

        // Method 2: Binary read with forced Windows-1252 conversion
        $handle = @fopen($filePath, 'rb');
        if ($handle) {
            $binaryContents = fread($handle, filesize($filePath));
            fclose($handle);

            $binaryContents = $this->removeBOM($binaryContents);

            // Check if this looks like Windows-1252 by looking for problem bytes
            if ($this->containsWindows1252Bytes($binaryContents)) {
                $converted = mb_convert_encoding($binaryContents, 'UTF-8', 'WINDOWS-1252');
                if ($this->isValidConversion($converted)) {
                    return $converted;
                }
            }
        }

        // Method 3: Fix corrupted UTF-8 replacement characters
        if ($contents !== false) {
            $fixed = $this->fixCorruptedWindows1252($contents);
            if ($this->isValidConversion($fixed)) {
                return $fixed;
            }
        }

        // Method 4: Try different encoding auto-detection with broader list
        if ($contents !== false) {
            $encodings = ['WINDOWS-1252', 'ISO-8859-1', 'ISO-8859-15', 'CP1252'];
            foreach ($encodings as $encoding) {
                $converted = mb_convert_encoding($contents, 'UTF-8', $encoding);
                if ($this->isValidConversion($converted)) {
                    return $converted;
                }
            }
        }

        // Fallback: return original contents
        return $contents ?: '';
    }

    /**
     * Detect and handle UTF-16 and UTF-32 encodings based on BOM
     */
    private function detectAndHandleUTFEncoding(string $data): ?string
    {
        // UTF-32 BE BOM
        if (substr($data, 0, 4) === "\x00\x00\xFE\xFF") {
            $withoutBOM = substr($data, 4);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-32BE');
        }

        // UTF-32 LE BOM
        if (substr($data, 0, 4) === "\xFF\xFE\x00\x00") {
            $withoutBOM = substr($data, 4);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-32LE');
        }

        // UTF-16 BE BOM
        if (substr($data, 0, 2) === "\xFE\xFF") {
            $withoutBOM = substr($data, 2);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-16BE');
        }

        // UTF-16 LE BOM
        if (substr($data, 0, 2) === "\xFF\xFE") {
            $withoutBOM = substr($data, 2);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-16LE');
        }

        // Try to detect UTF-16/32 without BOM (heuristic approach)
        $length = strlen($data);

        // UTF-32 detection (every 4th byte pattern)
        if ($length >= 8 && $length % 4 === 0) {
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 4) {
                if ($data[$i] === "\x00" && $data[$i + 1] === "\x00" && $data[$i + 2] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 5) { // Likely UTF-32LE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-32LE');
            }
        }

        // UTF-16 detection (every 2nd byte pattern)
        if ($length >= 4 && $length % 2 === 0) {
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 2) {
                if ($data[$i + 1] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 10) { // Likely UTF-16LE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-16LE');
            }

            // Check for UTF-16BE
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 2) {
                if ($data[$i] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 10) { // Likely UTF-16BE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-16BE');
            }
        }

        return null;
    }

    /**
     * Remove BOM (Byte Order Mark) from the beginning of a string
     */
    private function removeBOM(string $data): string
    {
        // UTF-8 BOM
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            return substr($data, 3);
        }

        // UTF-16 BE BOM
        if (substr($data, 0, 2) === "\xFE\xFF") {
            return substr($data, 2);
        }

        // UTF-16 LE BOM
        if (substr($data, 0, 2) === "\xFF\xFE") {
            return substr($data, 2);
        }

        // UTF-32 BE BOM
        if (substr($data, 0, 4) === "\x00\x00\xFE\xFF") {
            return substr($data, 4);
        }

        // UTF-32 LE BOM
        if (substr($data, 0, 4) === "\xFF\xFE\x00\x00") {
            return substr($data, 4);
        }

        return $data;
    }

    private function containsWindows1252Bytes(string $data): bool
    {
        // Check for Windows-1252 specific bytes in 0x80-0x9F range
        $windows1252Bytes = [0x80, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89, 0x8A, 0x8B, 0x8C, 0x8E, 0x91, 0x92, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9A, 0x9B, 0x9C, 0x9E, 0x9F];

        foreach ($windows1252Bytes as $byte) {
            if (strpos($data, chr($byte)) !== false) {
                return true;
            }
        }
        return false;
    }

    private function fixCorruptedWindows1252(string $data): string
    {
        // Map of UTF-8 replacement sequences back to proper characters
        $replacements = [
            "\xEF\xBF\xBD" => "\u{2019}", // Most common: right single quote (0x92) - use smart quote
            // Add more mappings as needed based on your data
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $data);
    }

    private function isValidConversion(string $data): bool
    {
        // Check if conversion was successful:
        // 1. Must be valid UTF-8
        // 2. Must NOT contain replacement characters (indicating corruption)
        // 3. Additional check for double-encoded replacement
        return mb_check_encoding($data, 'UTF-8')
              && !str_contains($data, "\xEF\xBF\xBD")  // UTF-8 replacement character bytes
              && !str_contains($data, 'ï¿½'); // Double-encoded replacement character
    }

    /**
     * @param array<int, string> $availableKeys
     * @param array<int, string> $headers
     * @return array<int, int|null>
     */
    private function setImportHints(string $entityType, array $availableKeys, array $headers): array
    {
        $hints = array_fill_keys(array_keys($headers), null);
        $primaryIndex = match ($entityType) {
            'bank_transaction' => 'transaction',
            'recurring_invoice' => 'invoice',
            default => $entityType,
        };
        $candidates = $this->buildImportHintCandidates($availableKeys, $primaryIndex);
        $proposals = [];

        foreach ($headers as $headerKey => $header) {
            $scores = [];

            foreach ($candidates as $candidate) {
                $score = $this->getImportHintScore($header, $candidate);

                if ($score > 0) {
                    $scores[$candidate['key']] = $score;
                }
            }

            if ($scores === []) {
                continue;
            }

            $highestScore = max($scores);
            $bestCandidates = array_keys(array_filter(
                $scores,
                fn (int $score): bool => $score === $highestScore
            ));

            if (count($bestCandidates) !== 1) {
                continue;
            }

            $proposals[$headerKey] = [
                'candidate' => $bestCandidates[0],
                'score' => $highestScore,
            ];
        }

        foreach (collect($proposals)->groupBy('candidate', true) as $candidateProposals) {
            $highestScore = $candidateProposals->max('score');
            $winners = $candidateProposals->filter(
                fn (array $proposal): bool => $proposal['score'] === $highestScore
            );

            if ($winners->count() !== 1) {
                continue;
            }

            $headerKey = $winners->keys()->first();
            $hints[$headerKey] = $winners->first()['candidate'];
        }

        return $hints;
    }

    /**
     * @param array<int, string> $availableKeys
     * @return array<int, array{key: int, canonical: string, full_label: string, label: string, field: string, is_primary: bool}>
     */
    private function buildImportHintCandidates(array $availableKeys, string $primaryIndex): array
    {
        $candidates = [];

        foreach ($availableKeys as $key => $value) {
            $parts = explode('.', $value, 2);
            $index = $parts[0];
            $field = $parts[1] ?? $parts[0];
            $translatedIndex = ctrans("texts.{$index}");
            $translatedLabel = ctrans("texts.{$field}");

            $candidates[] = [
                'key' => $key,
                'canonical' => $this->normalizeImportHint($value),
                'full_label' => $this->normalizeImportHint($translatedIndex.' '.$translatedLabel),
                'label' => $this->normalizeImportHint($translatedLabel),
                'field' => $this->normalizeImportHint($field),
                'is_primary' => $index === $primaryIndex,
            ];
        }

        return $candidates;
    }

    /**
     * @param array{key: int, canonical: string, full_label: string, label: string, field: string, is_primary: bool} $candidate
     */
    private function getImportHintScore(string $header, array $candidate): int
    {
        $normalizedHeader = $this->normalizeImportHint($header);

        if ($normalizedHeader === '' || $candidate['label'] === '') {
            return 0;
        }

        if ($normalizedHeader === $candidate['canonical']) {
            return 500;
        }

        if ($normalizedHeader === $candidate['full_label']) {
            return 450;
        }

        if ($normalizedHeader === $candidate['label'] || $normalizedHeader === $candidate['field']) {
            return 400 + ($candidate['is_primary'] ? 10 : 0);
        }

        return 0;
    }

    private function normalizeImportHint(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
    }

    public function import(ImportRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = $request->all();

        if (empty($data['hash'])) {
            // Create a reference
            $data['hash'] = $hash = Str::random(32);

            /** @var UploadedFile $file */
            foreach ($request->files->get('files') as $entityType => $file) {
                // $contents = file_get_contents($file->getPathname());
                $contents = $this->readFileWithProperEncoding($file->getPathname());

                // Store the csv in cache with an expiry of 10 minutes
                Cache::put($hash . '-' . $entityType, base64_encode($contents), 600);
                nlog($hash . '-' . $entityType);
            }
        }

        unset($data['files']);
        CSVIngest::dispatch($data, $user->company());

        return response()->json(['message' => ctrans('texts.import_started')], 200);
    }

    private function getEntityMap($entity_type)
    {
        return sprintf('App\\Import\\Definitions\%sMap', ucfirst(Str::camel($entity_type)));
    }

    private function getCsvData($csvfile)
    {

        $csv = Reader::fromString($csvfile);

        $csvdelimiter = self::detectDelimiter($csvfile);
        $csv->setDelimiter($csvdelimiter);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (is_array($headers) && count($headers) > 0 && count($data) > 4) {
                $firstCell = $headers[0];

                if (strstr($firstCell, (string) config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Entity Type Header
                }
            }
        }

        return $data; // Remove the convertData call since we fixed encoding upfront
    }

    /**
     * Returns the best delimiter
     *
     * @param string $csvfile
     * @return string
     */
    public function detectDelimiter($csvfile): string
    {

        $delimiters = [',', '.', ';', '|'];
        $bestDelimiter = ',';
        $count = 0;

        // 10-01-2024 - A better way to resolve the csv file delimiter.
        $csvfile = substr($csvfile, 0, strpos($csvfile, "\n"));

        foreach ($delimiters as $delimiter) {

            if (substr_count(strstr($csvfile, "\n", true), $delimiter) >= $count) {
                $count = substr_count($csvfile, $delimiter);
                $bestDelimiter = $delimiter;
            }

        }

        /** @phpstan-ignore-next-line **/
        return $bestDelimiter ?? ',';

    }
}
