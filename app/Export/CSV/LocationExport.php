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
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Location;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\ClientTransformer;
use App\Transformers\LocationTransformer;
use App\Transformers\ClientContactTransformer;
use Illuminate\Database\Eloquent\Builder;

class LocationExport extends BaseExport
{
    private ClientTransformer $client_transformer;

    private ClientContactTransformer $contact_transformer;

    private LocationTransformer $location_transformer;

    private Decorator $decorator;

    public Writer $csv;

    public string $date_key = 'created_at';

    public array $entity_keys = [
        'location_name' => 'location.name',
        'location_address1' => 'location.address1',
        'location_address2' => 'location.address2',
        'location_city' => 'location.city',
        'location_state' => 'location.state',
        'location_postal_code' => 'location.postal_code',
        'location_country' => 'location.country_id',
        'location_custom_value1' => 'location.custom_value1',
        'location_custom_value2' => 'location.custom_value2',
        'location_custom_value3' => 'location.custom_value3',
        'location_custom_value4' => 'location.custom_value4',
        'location_is_shipping' => 'location.is_shipping_location',
        'name' => 'client.name',
        'number' => 'client.number',
        'id_number' => 'client.id_number',
        'vat_number' => 'client.vat_number',
        'city' => 'client.city',
        'address1' => 'client.address1',
        'address2' => 'client.address2',
        'state' => 'client.state',
        'postal_code' => 'client.postal_code',
        'country' => 'client.country_id',
        'first_name' => 'contact.first_name',
        'last_name' => 'contact.last_name',
        'contact_phone' => 'contact.phone',
        'email' => 'contact.email',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->client_transformer = new ClientTransformer();
        $this->contact_transformer = new ClientContactTransformer();
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
                        ->whereNotNull('client_id')
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        });

        $query = $this->addDateRange($query, 'locations');

        return $query;
    }

    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::fromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
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

    private function buildRow(Location $location): array
    {
        $transformed_location = $this->location_transformer->transform($location);

        $transformed_client = [];
        if ($location->client) {
            $transformed_client = $this->client_transformer->transform($location->client);
        }

        $transformed_contact = [];
        if ($location->client && $contact = $location->client->contacts()->first()) {
            $transformed_contact = $this->contact_transformer->transform($contact);
        }

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if ($parts[0] == 'location' && array_key_exists($parts[1], $transformed_location)) {
                $entity[$key] = $transformed_location[$parts[1]];
            } elseif ($parts[0] == 'client' && array_key_exists($parts[1], $transformed_client)) {
                $entity[$key] = $transformed_client[$parts[1]];
            } elseif ($parts[0] == 'contact' && array_key_exists($parts[1], $transformed_contact)) {
                $entity[$key] = $transformed_contact[$parts[1]];
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

        if ($location->client) {
            if (in_array('client.country_id', $this->input['report_keys'])) {
                $entity['client.country_id'] = $location->client->country ? ctrans("texts.country_{$location->client->country->name}") : '';
            }

            if (in_array('client.shipping_country_id', $this->input['report_keys'])) {
                $entity['client.shipping_country_id'] = $location->client->shipping_country ? ctrans("texts.country_{$location->client->shipping_country->name}") : '';
            }

            if (in_array('client.currency', $this->input['report_keys'])) {
                $entity['client.currency'] = $location->client->currency() ? $location->client->currency()->code : $location->client->company->currency()->code;
            }

            if (in_array('client.industry_id', $this->input['report_keys'])) {
                $entity['client.industry_id'] = $location->client->industry ? ctrans("texts.industry_{$location->client->industry->name}") : '';
            }
        }

        return $entity;
    }
}
