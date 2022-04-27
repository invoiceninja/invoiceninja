<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ContactExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private ClientTransformer $client_transformer;

    private ClientContactTransformer $contact_transformer;

    protected string $date_key = 'created_at';

    protected array $entity_keys = [
        'address1' => 'client.address1',
        'address2' => 'client.address2',
        'balance' => 'client.balance',
        'city' => 'client.city',
        'country' => 'client.country_id',
        'credit_balance' => 'client.credit_balance',
        'custom_value1' => 'client.custom_value1',
        'custom_value2' => 'client.custom_value2',
        'custom_value3' => 'client.custom_value3',
        'custom_value4' => 'client.custom_value4',
        'id_number' => 'client.id_number',
        'industry' => 'client.industry_id',
        'last_login' => 'client.last_login',
        'name' => 'client.name',
        'number' => 'client.number',
        'paid_to_date' => 'client.paid_to_date',
        'phone' => 'client.phone',
        'postal_code' => 'client.postal_code',
        'private_notes' => 'client.private_notes',
        'public_notes' => 'client.public_notes',
        'shipping_address1' => 'client.shipping_address1',
        'shipping_address2' => 'client.shipping_address2',
        'shipping_city' => 'client.shipping_city',
        'shipping_country' => 'client.shipping_country_id',
        'shipping_postal_code' => 'client.shipping_postal_code',
        'shipping_state' => 'client.shipping_state',
        'state' => 'client.state',
        'vat_number' => 'client.vat_number',
        'website' => 'client.website',
        'currency' => 'client.currency',
        'first_name' => 'contact.first_name',
        'last_name' => 'contact.last_name',
        'phone' => 'contact.phone',
        'contact_custom_value1' => 'contact.custom_value1',
        'contact_custom_value2' => 'contact.custom_value2',
        'contact_custom_value3' => 'contact.custom_value3',
        'contact_custom_value4' => 'contact.custom_value4',
        'email' => 'contact.email',
    ];

    private array $decorate_keys = [
        'client.country_id',
        'client.shipping_country_id',
        'client.currency',
        'client.industry',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->client_transformer = new ClientTransformer();
        $this->contact_transformer = new ClientContactTransformer();
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

        $query = ClientContact::query()
                        ->where('company_id', $this->company->id);

        $query = $this->addDateRange($query);

        $query->cursor()->each(function ($contact){

            $this->csv->insertOne($this->buildRow($contact)); 

        });


        return $this->csv->toString(); 

    }

    private function buildRow(ClientContact $contact) :array
    {

        $transformed_contact = false;

        $transformed_client = $this->client_transformer->transform($contact->client);
        $transformed_contact = $this->contact_transformer->transform($contact);

        $entity = [];

        foreach(array_values($this->input['report_keys']) as $key){

            $parts = explode(".",$key);
            $entity[$parts[1]] = "";

            if($parts[0] == 'client') {
                $entity[$parts[1]] = $transformed_client[$parts[1]];
            }
            elseif($parts[0] == 'contact') {
                $entity[$parts[1]] = $transformed_contact[$parts[1]];
            }

        }

        return $this->decorateAdvancedFields($contact->client, $entity);

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
