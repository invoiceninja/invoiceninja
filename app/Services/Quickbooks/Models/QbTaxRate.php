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

    public function syncToForeign(array $records): void {}
}
