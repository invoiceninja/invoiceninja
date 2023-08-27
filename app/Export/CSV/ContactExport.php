<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Transformers\ClientContactTransformer;
use App\Transformers\ClientTransformer;
use App\Utils\Ninja;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ContactExport extends BaseExport
{

    private ClientTransformer $client_transformer;

    private ClientContactTransformer $contact_transformer;

    public Writer $csv;

    public string $date_key = 'created_at';

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->client_transformer = new ClientTransformer();
        $this->contact_transformer = new ClientContactTransformer();
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->client_report_keys);
        }

        $query = ClientContact::query()
                        ->where('company_id', $this->company->id);

        $query = $this->addDateRange($query);

        return $query;

    }

    public function run()
    {
        
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()->each(function ($contact) {
            $this->csv->insertOne($this->buildRow($contact));
        });

        return $this->csv->toString();
    }


    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use($headerdisplay){
                return ['identifier' => $value, 'display_value' => $headerdisplay[$value]];
            })->toArray();

        $report = $query->cursor()
                ->map(function ($contact) {
                    return $this->buildRow($contact);
                })->toArray();
        
        return array_merge(['columns' => $header], $report);
    }


    private function buildRow(ClientContact $contact) :array
    {
        $transformed_contact = false;

        $transformed_client = $this->client_transformer->transform($contact->client);
        $transformed_contact = $this->contact_transformer->transform($contact);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);
            $keyval = array_search($key, $this->entity_keys);

            if ($parts[0] == 'client' && array_key_exists($parts[1], $transformed_client)) {
                $entity[$keyval] = $transformed_client[$parts[1]];
            } elseif ($parts[0] == 'contact' && array_key_exists($parts[1], $transformed_contact)) {
                $entity[$keyval] = $transformed_contact[$parts[1]];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($contact->client, $entity);
    }

    private function decorateAdvancedFields(Client $client, array $entity) :array
    {
        if (in_array('client.country_id', $this->input['report_keys'])) {
            $entity['country'] = $client->country ? ctrans("texts.country_{$client->country->name}") : '';
        }

        if (in_array('client.shipping_country_id', $this->input['report_keys'])) {
            $entity['shipping_country'] = $client->shipping_country ? ctrans("texts.country_{$client->shipping_country->name}") : '';
        }

        if (in_array('client.currency', $this->input['report_keys'])) {
            $entity['currency'] = $client->currency() ? $client->currency()->code : $client->company->currency()->code;
        }

        if (in_array('client.industry_id', $this->input['report_keys'])) {
            $entity['industry_id'] = $client->industry ? ctrans("texts.industry_{$client->industry->name}") : '';
        }

        return $entity;
    }
}
