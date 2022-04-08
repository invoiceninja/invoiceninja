<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Transformers\CreditTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class CreditExport
{
    private $company;

    private $report_keys;

    private $credit_transformer;

    private array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'client' => 'client_id',
        'custom_surcharge1' => 'custom_surcharge1',
        'custom_surcharge2' => 'custom_surcharge2',
        'custom_surcharge3' => 'custom_surcharge3',
        'custom_surcharge4' => 'custom_surcharge4',
        'country' => 'client.country_id',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'date' => 'date',
        'discount' => 'discount',
        'due_date' => 'due_date',
        'exchange_rate' => 'exchange_rate',
        'footer' => 'footer',
        'invoice' => 'invoice_id',
        'number' => 'number',
        'paid_to_date' => 'paid_to_date',
        'partial' => 'partial',
        'partial_due_date' => 'partial_due_date',
        'po_number' => 'po_number',
        'private_notes' => 'private_notes',
        'public_notes' => 'public_notes',
        'status' => 'status_id',
        'tax_name1' => 'tax_name1',
        'tax_name2' => 'tax_name2',
        'tax_name3' => 'tax_name3',
        'tax_rate1' => 'tax_rate1',
        'tax_rate1' => 'tax_rate1',
        'tax_rate1' => 'tax_rate1',
    ];

    private array $decorate_keys = [
        'client.country_id',
        'client.shipping_country_id',
        'client.currency',
        'client.industry',
    ];

    public function __construct(Company $company, array $report_keys)
    {
        $this->company = $company;
        $this->report_keys = $report_keys;
        $this->credit_transformer = new CreditTransformer();
    }

    public function run()
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        Client::with('contacts')->where('company_id', $this->company->id)
                                ->where('is_deleted',0)
                                ->cursor()
                                ->each(function ($client){

                                    $this->csv->insertOne($this->buildRow($client)); 

                                });


        return $this->csv->toString(); 

    }

    private function buildHeader() :array
    {

        $header = [];

        foreach(array_keys($this->report_keys) as $key)
            $header[] = ctrans("texts.{$key}");

        return $header;
    }

    private function buildRow(Client $client) :array
    {

        $transformed_contact = false;

        $transformed_client = $this->client_transformer->transform($client);

        if($contact = $client->contacts()->first())
            $transformed_contact = $this->contact_transformer->transform($contact);


        $entity = [];

        foreach(array_values($this->report_keys) as $key){

            $parts = explode(".",$key);
            $entity[$parts[1]] = "";

            if($parts[0] == 'client') {
                $entity[$parts[1]] = $transformed_client[$parts[1]];
            }
            elseif($parts[0] == 'contact') {
                $entity[$parts[1]] = $transformed_contact[$parts[1]];
            }

        }

        return $this->decorateAdvancedFields($client, $entity);

    }

    private function decorateAdvancedFields(Client $client, array $entity) :array
    {

        if(array_key_exists('country_id', $entity))
            $entity['country_id'] = $client->country ? ctrans("texts.country_{$client->country->name}") : ""; 

        if(array_key_exists('shipping_country_id', $entity))
            $entity['shipping_country_id'] = $client->shipping_country ? ctrans("texts.country_{$client->shipping_country->name}") : ""; 

        if(array_key_exists('currency', $entity))
            $entity['currency'] = $client->currency()->code;

        if(array_key_exists('industry_id', $entity))
            $entity['industry_id'] = $client->industry ? ctrans("texts.industry_{$client->industry->name}") : ""; 

        return $entity;
    }

}
