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

namespace App\Services\Quickbooks\Models;

use App\Factory\TaxRateFactory;
use App\Models\TaxRate;
use App\Services\Quickbooks\TaxCodeComponentKey;
use QuickBooksOnline\API\Facades\TaxAgency as QbTaxAgency;
use QuickBooksOnline\API\Facades\TaxRate as QbTaxRateFacade;
use QuickBooksOnline\API\Facades\TaxService as QbTaxService;
use App\Interfaces\SyncInterface;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\TaxRateTransformer;

class QbTaxRate implements SyncInterface
{
    protected TaxRateTransformer $tax_rate_transformer;

    public function __construct(public QuickbooksService $service)
    {
        $this->tax_rate_transformer = new TaxRateTransformer();
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('TaxRate', $id);
    }

    public function syncToNinja(array $records): void
    {
        // Merge tax_rate_map based on "id" key - update existing entries and add new ones
        $existing_map = $this->service->company->quickbooks->settings->tax_rate_map ?? [];

        // Transform new records and merge with existing, keyed by "id"
        $merged_map = collect($existing_map)
            ->keyBy('id')
            ->merge(
                collect($records)
                    ->map(fn($record) => $this->tax_rate_transformer->transform($record))
                    ->keyBy('id')
            )
            ->values()
            ->toArray();

        $this->service->company->quickbooks->settings->tax_rate_map = $merged_map;

        foreach ($records as $record) {
            $ninja_data = $this->tax_rate_transformer->transform($record);

            if (TaxRate::where('company_id', $this->service->company->id)
                ->where('name', $ninja_data['name'])
                ->where('rate', $ninja_data['rate'])
                ->doesntExist()) {

                $tr = TaxRateFactory::create($this->service->company->id, $this->service->company->owner()->id);
                $tr->name = $ninja_data['name'];
                $tr->rate = $ninja_data['rate'];
                $tr->save();

            }

        }
    }

    public function syncToForeign(array $records): void
    {
        foreach ($records as $record) {
            if ($record instanceof TaxRate) {
                $this->ensureTaxCodeForComponents([
                    ['name' => $record->name, 'rate' => (float) $record->rate],
                ]);

                continue;
            }

            if (is_array($record) && isset($record['components']) && is_array($record['components'])) {
                $this->ensureTaxCodeForComponents($record['components']);
            }
        }

        $this->service->companySync();
    }

    /**
     * @param  array<int, array{name?: string, rate?: float|int|string|null}>  $components
     */
    public function ensureTaxCodeForComponents(array $components): ?string
    {
        $components = $this->normalizeComponents($components);

        if (empty($components)) {
            return null;
        }

        $existing_tax_code_id = $this->findExistingTaxCodeId($components);

        if ($existing_tax_code_id) {
            return $existing_tax_code_id;
        }

        try {
            [$tax_code_name, $result] = $this->createTaxService($components, false);
        } catch (\Throwable $e) {
            if (!$this->isDuplicateNameException($e)) {
                nlog('QB: failed to create TaxService for Ninja tax components', [
                    'tax_code' => $this->taxCodeName($components),
                    'component_key' => TaxCodeComponentKey::fromComponents($components),
                    'components' => $components,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }

            nlog('QB: TaxService name already exists; searching for matching existing TaxCode', [
                'tax_code' => $this->taxCodeName($components),
                'component_key' => TaxCodeComponentKey::fromComponents($components),
                'components' => $components,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $existing_tax_code_id = $this->findExistingTaxCodeIdFromQuickBooks($components);

            if ($existing_tax_code_id) {
                nlog('QB: using existing TaxCode after duplicate-name response', [
                    'tax_code_id' => $existing_tax_code_id,
                    'component_key' => TaxCodeComponentKey::fromComponents($components),
                    'components' => $components,
                ]);

                return $existing_tax_code_id;
            }

            nlog('QB: TaxService duplicate name did not match an existing TaxCode; retrying with generated names', [
                'tax_code' => $this->taxCodeName($components),
                'component_key' => TaxCodeComponentKey::fromComponents($components),
                'components' => $components,
            ]);

            try {
                [$tax_code_name, $result] = $this->createTaxService($components, true);
            } catch (\Throwable $retry_exception) {
                nlog('QB: failed to create TaxService for Ninja tax components', [
                    'tax_code' => $this->taxCodeName($components, true),
                    'component_key' => TaxCodeComponentKey::fromComponents($components),
                    'components' => $components,
                    'exception' => $retry_exception::class,
                    'message' => $retry_exception->getMessage(),
                ]);

                throw $retry_exception;
            }
        }

        $tax_code_id = data_get($result, 'TaxService.TaxCodeId') ?? data_get($result, 'TaxCodeId');

        if ($tax_code_id) {
            $this->rememberTaxCodeAlias($components, (string) $tax_code_id, $tax_code_name, $result);
        }

        nlog('QB: created TaxService for Ninja tax components', [
            'tax_code' => $tax_code_name,
            'tax_code_id' => $tax_code_id,
            'component_key' => TaxCodeComponentKey::fromComponents($components),
            'components' => $components,
        ]);

        return $tax_code_id ? (string) $tax_code_id : null;
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     * @return array{0: string, 1: mixed}
     */
    private function createTaxService(array $components, bool $uniqueNames): array
    {
        $agencies = $this->fetchTaxAgencies();
        $rate_details = [];

        foreach ($components as $component) {
            $tax_agency_id = $this->ensureTaxAgencyId($component, $agencies);
            $existing_tax_rate_id = $this->findExistingTaxRateId($component);
            $detail = [
                'TaxRateName' => $this->taxRateName($component, $uniqueNames),
                'RateValue' => $component['rate'],
                'TaxAgencyId' => $tax_agency_id,
                'TaxApplicableOn' => 'Sales',
            ];

            if ($existing_tax_rate_id) {
                $detail['TaxRateId'] = $existing_tax_rate_id;
            }

            $rate_details[] = QbTaxRateFacade::create($detail);
        }

        $tax_code_name = $this->taxCodeName($components, $uniqueNames);
        $tax_service = QbTaxService::create([
            'TaxCode' => $tax_code_name,
            'TaxRateDetails' => $rate_details,
        ]);

        return [$tax_code_name, $this->service->sdk->Add($tax_service)];
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private function rememberTaxCodeAlias(array $components, string $taxCodeId, string $taxCodeName, mixed $result): void
    {
        $component_key = TaxCodeComponentKey::fromComponents($components);

        if ($component_key === '') {
            return;
        }

        if (count($components) === 1) {
            $tax_rate_map = $this->service->company->quickbooks->settings->tax_rate_map ?? [];
            $tax_rate_map[] = [
                'id' => $this->taxRateIdFromTaxServiceResult($result) ?: 'ninja-tax-code:' . $taxCodeId . ':' . $component_key,
                'name' => $components[0]['name'],
                'rate' => $components[0]['rate'],
                'tax_code_id' => $taxCodeId,
                'source' => 'ninja',
            ];
            $this->service->company->quickbooks->settings->tax_rate_map = $tax_rate_map;
        } else {
            $composite_tax_code_map = $this->service->company->quickbooks->settings->composite_tax_code_map ?? [];
            $composite_tax_code_map[$component_key] = [[
                'tax_code_id' => $taxCodeId,
                'name' => $taxCodeName,
                'source' => 'ninja',
            ]];
            $this->service->company->quickbooks->settings->composite_tax_code_map = $composite_tax_code_map;
        }

        if ($this->service->company->exists) {
            $this->service->company->save();
        }
    }

    private function taxRateIdFromTaxServiceResult(mixed $result): ?string
    {
        $rate_details = data_get($result, 'TaxService.TaxRateDetails') ?? data_get($result, 'TaxRateDetails') ?? [];

        if (!is_array($rate_details)) {
            $rate_details = [$rate_details];
        }

        $tax_rate_id = data_get($rate_details, '0.TaxRateId');

        return $tax_rate_id ? (string) $tax_rate_id : null;
    }

    private function isDuplicateNameException(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate Name Exists Error')
            || str_contains($e->getMessage(), '"code":"6240"')
            || str_contains($e->getMessage(), '<Error code="6240"');
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private function findExistingTaxCodeIdFromQuickBooks(array $components): ?string
    {
        $tax_rates = $this->service->fetchTaxRates();
        $tax_codes = $this->service->fetchTaxCodes();
        $tax_rates_by_id = [];

        foreach ($tax_rates as $tax_rate) {
            $tax_rate_id = (string) ($tax_rate['id'] ?? '');

            if ($tax_rate_id !== '') {
                $tax_rates_by_id[$tax_rate_id] = $tax_rate;
            }
        }

        $component_key = TaxCodeComponentKey::fromComponents($components);

        foreach ($tax_codes as $tax_code) {
            $tax_code_array = is_object($tax_code) ? json_decode(json_encode($tax_code), true) : $tax_code;
            $raw_id = data_get($tax_code_array, 'Id');
            $tax_code_id = is_array($raw_id) ? (string) ($raw_id['value'] ?? '') : (string) ($raw_id ?? '');

            if ($tax_code_id === '') {
                continue;
            }

            $tax_code_components = $this->componentsFromTaxCode($tax_code_array, $tax_rates_by_id);

            if ($component_key !== '' && TaxCodeComponentKey::fromComponents($tax_code_components) === $component_key) {
                $this->refreshTaxRateMapFromQuickBooks($tax_rates);
                $this->rememberTaxCodeAlias($components, $tax_code_id, (string) data_get($tax_code_array, 'Name', ''), null);

                return $tax_code_id;
            }
        }

        $this->refreshTaxRateMapFromQuickBooks($tax_rates);

        return null;
    }

    /**
     * @param  array<string, mixed>  $taxCode
     * @param  array<string, array{id?: string, name?: string, rate?: float|int|string|null}>  $taxRatesById
     * @return array<int, array{name: string, rate: float}>
     */
    private function componentsFromTaxCode(array $taxCode, array $taxRatesById): array
    {
        $sales_rate_list = data_get($taxCode, 'SalesTaxRateList.TaxRateDetail', []);

        if (!is_array($sales_rate_list) || (!isset($sales_rate_list[0]) && !empty($sales_rate_list))) {
            $sales_rate_list = [$sales_rate_list];
        }

        $components = [];

        foreach ($sales_rate_list as $rate_detail) {
            $rate_ref = data_get($rate_detail, 'TaxRateRef');
            $tax_rate_id = is_array($rate_ref) ? (string) ($rate_ref['value'] ?? '') : (string) ($rate_ref ?? '');

            if ($tax_rate_id === '' || !isset($taxRatesById[$tax_rate_id])) {
                continue;
            }

            $tax_rate = $taxRatesById[$tax_rate_id];
            $rate = (float) ($tax_rate['rate'] ?? 0);

            if ($rate <= 0) {
                continue;
            }

            $components[] = [
                'name' => (string) ($tax_rate['name'] ?? ''),
                'rate' => $rate,
            ];
        }

        return $components;
    }

    /**
     * @param  array<int, array{id?: string, name?: string, rate?: float|int|string|null}>|null  $taxRates
     */
    private function refreshTaxRateMapFromQuickBooks(?array $taxRates = null): void
    {
        $tax_rates = $taxRates ?? $this->service->fetchTaxRates();

        if (empty($tax_rates)) {
            return;
        }

        $existing_tax_rate_map = $this->service->company->quickbooks->settings->tax_rate_map ?? [];
        $ninja_entries = array_values(array_filter($existing_tax_rate_map, fn (mixed $tax_rate): bool => is_array($tax_rate) && ($tax_rate['source'] ?? null) === 'ninja'));

        $this->service->company->quickbooks->settings->tax_rate_map = array_values(array_merge($tax_rates, $ninja_entries));
    }

    /**
     * @param  array<int, array{name?: string, rate?: float|int|string|null}>  $components
     * @return array<int, array{name: string, rate: float}>
     */
    private function normalizeComponents(array $components): array
    {
        $normalized = [];

        foreach ($components as $component) {
            $rate = (float) ($component['rate'] ?? 0);

            if ($rate <= 0) {
                continue;
            }

            $normalized[] = [
                'name' => trim((string) ($component['name'] ?? '')),
                'rate' => $rate,
            ];
        }

        usort($normalized, fn (array $a, array $b): int => TaxCodeComponentKey::fromComponents([$a]) <=> TaxCodeComponentKey::fromComponents([$b]));

        return $normalized;
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private function findExistingTaxCodeId(array $components): ?string
    {
        if (count($components) === 1) {
            $tax_rate_id = $this->findExistingTaxRateId($components[0]);

            if (!$tax_rate_id) {
                return null;
            }

            foreach ($this->service->company->quickbooks->settings->tax_rate_map ?? [] as $tax_rate) {
                if ((string) ($tax_rate['id'] ?? '') === $tax_rate_id && !empty($tax_rate['tax_code_id'] ?? null)) {
                    return (string) $tax_rate['tax_code_id'];
                }
            }

            return null;
        }

        $component_key = TaxCodeComponentKey::fromComponents($components);
        $candidates = $this->service->company->quickbooks->settings->composite_tax_code_map[$component_key] ?? [];

        if (is_string($candidates)) {
            return $candidates;
        }

        if (isset($candidates['tax_code_id'])) {
            $candidates = [$candidates];
        }

        if (!is_array($candidates) || empty($candidates)) {
            return null;
        }

        $candidate_ids = [];

        foreach ($candidates as $candidate) {
            $candidate_id = is_array($candidate) ? (string) ($candidate['tax_code_id'] ?? '') : (string) $candidate;

            if ($candidate_id !== '') {
                $candidate_ids[] = $candidate_id;
            }
        }

        $candidate_ids = array_values(array_unique($candidate_ids));

        return count($candidate_ids) === 1 ? $candidate_ids[0] : null;
    }

    /**
     * @param  array{name: string, rate: float}  $component
     */
    private function findExistingTaxRateId(array $component): ?string
    {
        $wanted_rate = TaxCodeComponentKey::formatRate($component['rate']);
        $wanted_name = TaxCodeComponentKey::normalizeName($component['name']);

        foreach ($this->service->company->quickbooks->settings->tax_rate_map ?? [] as $tax_rate) {
            $rate = TaxCodeComponentKey::formatRate($tax_rate['rate'] ?? 0);
            $name = TaxCodeComponentKey::normalizeName((string) ($tax_rate['name'] ?? ''));

            if ($rate === $wanted_rate && ($wanted_name === '' || ($name !== '' && (str_contains($name, $wanted_name) || str_contains($wanted_name, $name))))) {
                return (string) ($tax_rate['id'] ?? '');
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function fetchTaxAgencies(): array
    {
        // @phpstan-ignore-next-line
        $records = $this->service->sdk->Query('SELECT * FROM TaxAgency') ?? [];

        // @phpstan-ignore-next-line
        if (!is_array($records)) {
            $records = [$records];
        }

        $agencies = [];

        foreach ($records as $record) {
            $id = (string) (data_get($record, 'Id.value') ?? data_get($record, 'Id') ?? '');
            $name = (string) (data_get($record, 'DisplayName') ?? data_get($record, 'Name') ?? '');

            if ($id !== '' && $name !== '') {
                $agencies[] = ['id' => $id, 'name' => $name];
            }
        }

        return $agencies;
    }

    /**
     * @param  array{name: string, rate: float}  $component
     * @param  array<int, array{id: string, name: string}>  $agencies
     */
    private function ensureTaxAgencyId(array $component, array &$agencies): string
    {
        $agency_id = $this->findTaxAgencyId($component, $agencies);

        if ($agency_id) {
            return $agency_id;
        }

        $agency_name = $this->taxAgencyName($component);
        $tax_agency = QbTaxAgency::create([
            'DisplayName' => $agency_name,
        ]);

        try {
            $result = $this->service->sdk->Add($tax_agency);
        } catch (\Throwable $e) {
            nlog('QB: failed to create TaxAgency for Ninja tax component', [
                'agency_name' => $agency_name,
                'component' => $component,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $agency_id = (string) (data_get($result, 'Id.value') ?? data_get($result, 'Id') ?? '');

        if ($agency_id === '') {
            throw new \RuntimeException("QuickBooks did not return an ID for TaxAgency {$agency_name}");
        }

        $agencies[] = ['id' => $agency_id, 'name' => $agency_name];

        nlog('QB: created TaxAgency for Ninja tax component', [
            'agency_id' => $agency_id,
            'agency_name' => $agency_name,
            'component' => $component,
        ]);

        return $agency_id;
    }

    /**
     * @param  array{name: string, rate: float}  $component
     * @param  array<int, array{id: string, name: string}>  $agencies
     */
    private function findTaxAgencyId(array $component, array $agencies): ?string
    {
        $component_name = TaxCodeComponentKey::normalizeName($component['name']);
        $keywords = $this->taxAgencyKeywords($component_name);

        foreach ($agencies as $agency) {
            $agency_name = TaxCodeComponentKey::normalizeName($agency['name']);

            if ($component_name !== '' && str_contains($agency_name, $component_name)) {
                return $agency['id'];
            }

            foreach ($keywords as $keyword) {
                if (str_contains($agency_name, $keyword)) {
                    return $agency['id'];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function taxAgencyKeywords(string $componentName): array
    {
        if (str_contains($componentName, 'qst')) {
            return ['quebec', 'québec', 'revenu'];
        }

        if (str_contains($componentName, 'gst') || str_contains($componentName, 'hst')) {
            return ['receiver', 'general', 'canada', 'cra'];
        }

        if (str_contains($componentName, 'pst')) {
            return ['pst', 'provincial', 'province', 'revenue', 'finance'];
        }

        return array_filter(explode(' ', $componentName));
    }

    /**
     * @param  array{name: string, rate: float}  $component
     */
    private function taxAgencyName(array $component): string
    {
        $component_name = TaxCodeComponentKey::normalizeName($component['name']);

        if (str_contains($component_name, 'qst')) {
            return 'Revenu Quebec';
        }

        if (str_contains($component_name, 'gst') || str_contains($component_name, 'hst')) {
            return 'Receiver General';
        }

        if (str_contains($component_name, 'pst')) {
            return 'Provincial Sales Tax';
        }

        $name = trim($component['name']);

        return $name !== '' ? $name . ' Tax Agency' : 'Ninja Tax Agency';
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private function taxCodeName(array $components, bool $unique = false): string
    {
        if ($unique) {
            return 'Ninja Tax ' . substr(sha1(TaxCodeComponentKey::fromComponents($components) . microtime(true)), 0, 10);
        }

        $name = implode(' + ', array_map(fn (array $component): string => $this->taxRateName($component), $components));

        return strlen($name) <= 31 ? $name : 'Ninja Tax ' . substr(sha1(TaxCodeComponentKey::fromComponents($components)), 0, 10);
    }

    /**
     * @param  array{name: string, rate: float}  $component
     */
    private function taxRateName(array $component, bool $unique = false): string
    {
        $rate = rtrim(rtrim(TaxCodeComponentKey::formatRate($component['rate']), '0'), '.');
        $name = trim($component['name']);
        $base = trim(($name !== '' ? $name . ' ' : '') . $rate . '%');

        if (!$unique) {
            return $base;
        }

        $suffix = substr(sha1(TaxCodeComponentKey::fromComponents([$component]) . microtime(true)), 0, 8);
        $unique_name = trim($base . ' ' . $suffix);

        return strlen($unique_name) <= 100 ? $unique_name : substr($unique_name, 0, 100);
    }
}
