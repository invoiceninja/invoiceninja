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

namespace App\Http\Requests\Import;

use App\Utils\Ninja;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Validator;
use League\Csv\Bom;
use League\Csv\Info;
use League\Csv\Reader;

trait ValidatesClientImportLimit
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || !in_array($this->input('import_type'), ['csv', 'freshbooks', 'waveaccounting', 'zoho', 'invoicely'], true)) {
                return;
            }

            $is_preimport = $this instanceof PreImportRequest;
            $mapping = $is_preimport ? [] : $this->input('column_map.client.mapping', []);

            if (!$is_preimport && $this->input('import_type') === 'csv' && (!is_array($mapping) || $mapping === [])) {
                return;
            }

            if ($this->input('import_type') !== 'csv') {
                $mapping = [];
            }

            $file = $this->file('files.client');
            $uses_hash = !$is_preimport && $this->filled('hash');

            if (!$file && !$uses_hash) {
                return;
            }

            $company = auth()->user()->company();

            if (!Ninja::isHosted() || !$company->account->isFreeHostedClient()) {
                return;
            }

            $contents = $uses_hash
                ? base64_decode(Cache::get($this->input('hash').'-client', ''))
                : $file->getContent();

            if ($contents === '' || $contents === false) {
                return;
            }

            $client_limit = $company->account->hosted_client_count;
            $remaining_clients = $client_limit - $company->clients()->count();
            // Preimport has no confirmed header setting yet, so allow for one header record.
            $skip_header = $is_preimport || $this->input('import_type') !== 'csv' || $this->boolean('skip_header');

            if ($this->exceedsClientImportLimit($contents, $mapping, $skip_header, $is_preimport, $remaining_clients)) {
                $validator->errors()->add(
                    $uses_hash ? 'column_map.client' : 'files.client',
                    ctrans('texts.limit_clients', ['count' => $client_limit])
                );
            }
        });
    }

    private function exceedsClientImportLimit(string $contents, array $mapping, bool $skip_header, bool $is_preimport, int $remaining_clients): bool
    {
        $bom = Bom::tryFromSequence($contents);

        if ($bom) {
            $contents = mb_convert_encoding(substr($contents, $bom->length()), 'UTF-8', $bom->encoding());
        }

        $csv = Reader::fromString($contents);
        $delimiter_stats = Info::getDelimiterStats($csv, [',', '.', ';', '|']);
        arsort($delimiter_stats);
        $csv->setDelimiter(array_key_first($delimiter_stats));

        $name_column = array_search('client.name', $mapping, true);
        $seen_names = [];
        $client_count = 0;
        $first_record = $csv->first();
        // Match the importer, which removes the three Invoice Ninja export records.
        $preamble_remaining = $this->input('import_type') === 'csv'
            && str_contains($first_record[0] ?? '', (string) config('ninja.app_name'))
            && $csv->count() > 4 ? 3 : 0;

        foreach ($csv as $record) {
            if ($preamble_remaining > 0) {
                $preamble_remaining--;
                continue;
            }

            if ($skip_header) {
                if ($is_preimport || $this->input('import_type') !== 'csv') {
                    $name_column = $this->guessClientNameColumn($record);
                }

                $skip_header = false;
                continue;
            }

            if (array_filter($record, static fn ($value): bool => trim($value ?? '') !== '') === []) {
                continue;
            }

            $client_name = $name_column === false ? null : ($record[$name_column] ?? null);

            if (!empty($client_name)) {
                if (isset($seen_names[$client_name])) {
                    continue;
                }

                $seen_names[$client_name] = true;
            }

            if (++$client_count > $remaining_clients) {
                return true;
            }
        }

        return false;
    }

    private function guessClientNameColumn(array $headers): int|string|false
    {
        if ($this->input('import_type') === 'freshbooks') {
            return array_search('Organization', $headers, true);
        }

        if ($this->input('import_type') !== 'csv') {
            return false;
        }

        $name_headers = array_map('mb_strtolower', ['client.name', 'name', ctrans('texts.name'), ctrans('texts.client_name')]);

        foreach ($headers as $column => $header) {
            if (in_array(mb_strtolower(trim($header ?? '')), $name_headers, true)) {
                return $column;
            }
        }

        return false;
    }
}
