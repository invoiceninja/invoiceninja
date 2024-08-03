<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Utils\Ninja;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\ClientTransformer;
use App\Transformers\ClientContactTransformer;
use Illuminate\Database\Eloquent\Builder;

class ContactExport extends BaseExport
{
    private ClientTransformer $client_transformer;

    private ClientContactTransformer $contact_transformer;

    private Decorator $decorator;

    public Writer $csv;

    public string $date_key = 'created_at';

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->client_transformer = new ClientTransformer();
        $this->contact_transformer = new ClientContactTransformer();
        $this->decorator = new Decorator();
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
                        ->where('company_id', $this->company->id)
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        });

        $query = $this->addDateRange($query, 'client_contacts');

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()->each(function ($contact) {
            /** @var \App\Models\ClientContact $contact */
            $this->csv->insertOne($this->buildRow($contact));
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
                ->map(function ($contact) {
                    /** @var \App\Models\ClientContact $contact */
                    $row = $this->buildRow($contact);
                    return $this->processMetaData($row, $contact);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }


    private function buildRow(ClientContact $contact): array
    {
        $transformed_contact = false;

        $transformed_client = $this->client_transformer->transform($contact->client);
        $transformed_contact = $this->contact_transformer->transform($contact);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if ($parts[0] == 'client' && array_key_exists($parts[1], $transformed_client)) {
                $entity[$key] = $transformed_client[$parts[1]];
            } elseif ($parts[0] == 'contact' && array_key_exists($parts[1], $transformed_contact)) {
                $entity[$key] = $transformed_contact[$parts[1]];
            } else {
                // nlog($key);
                $entity[$key] = $this->decorator->transform($key, $contact);
                // $entity[$key] = '';

            }
        }
        // return $entity;
        return $this->decorateAdvancedFields($contact->client, $entity);
    }

    private function decorateAdvancedFields(Client $client, array $entity): array
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

        if (in_array('client.user_id', $this->input['report_keys'])) {
            $entity['client.user_id'] = $client->user ? $client->user->present()->name() : '';// @phpstan-ignore-line
        }

        if (in_array('client.assigned_user_id', $this->input['report_keys'])) {
            $entity['client.assigned_user_id'] = $client->assigned_user ? $client->assigned_user->present()->name() : '';
        }


        return $entity;
    }
}
