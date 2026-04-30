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

namespace App\Export\CSV;

use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Location;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\LocationTransformer;
use Illuminate\Database\Eloquent\Builder;

class LocationExport extends BaseExport
{
    private LocationTransformer $location_transformer;

    private Decorator $decorator;

    public Writer $csv;

    public string $date_key = 'created_at';

    public array $entity_keys = [
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->location_transformer = new LocationTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->location_report_keys);
        }

        $query = Location::query()
                        ->where('company_id', $this->company->id)
                        ->where(function ($q) {
                            $q->whereHas('client', function ($c) {
                                $c->where('is_deleted', false);
                            })->orWhereHas('vendor', function ($v) {
                                $v->where('is_deleted', false);
                            });
                        });

        $query = $this->addDateRange($query, 'locations');

        return $query;
    }

    public function run()
    {
        $query = $this->init();

        $this->csv = Writer::fromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $this->csv->insertOne($this->buildHeader());

        $query->cursor()->each(function ($location) {
            /** @var \App\Models\Location $location */
            $this->csv->insertOne($this->buildRow($location));
        });

        return $this->csv->toString();
    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($location) {
                    /** @var \App\Models\Location $location */
                    $row = $this->buildRow($location);
                    return $this->processMetaData($row, $location);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    protected function buildRow(Location $location): array
    {
        $transformed_location = $this->location_transformer->transform($location);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if ($parts[0] == 'location' && array_key_exists($parts[1], $transformed_location)) {
                $entity[$key] = $transformed_location[$parts[1]];
            } elseif ($key == 'client.name') {
                $entity[$key] = $location->client ? $location->client->present()->name() : '';
            } elseif ($key == 'vendor.name') {
                $entity[$key] = $location->vendor ? $location->vendor->name : '';
            } else {
                $entity[$key] = $this->decorator->transform($key, $location);
            }
        }

        $entity = $this->decorateAdvancedFields($location, $entity);

        return $this->convertFloats($entity);
    }

    private function decorateAdvancedFields(Location $location, array $entity): array
    {
        if (in_array('location.country_id', $this->input['report_keys'])) {
            $entity['location.country_id'] = $location->country ? ctrans("texts.country_{$location->country->name}") : '';
        }

        return $entity;
    }
}
