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
use App\Models\Company;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Payment;
use App\Transformers\PaymentTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PaymentExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $entity_transformer;

    protected $date_key = 'date';

    protected array $entity_keys = [
        'amount' => 'amount',
        'applied' => 'applied',
        'client' => 'client_id',
        'currency' => 'currency_id',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'date' => 'date',
        'exchange_currency' => 'exchange_currency_id',
        'gateway_type' => 'gateway_type_id',
        'number' => 'number',
        'private_notes' => 'private_notes',
        'project' => 'project_id',
        'refunded' => 'refunded',
        'status' => 'status_id',
        'transaction_reference' => 'transaction_reference',
        'type' => 'type_id',
        'vendor' => 'vendor_id',
    ];

    private array $decorate_keys = [
        'vendor',
        'status',
        'project',
        'client',
        'currency',
        'exchange_currency',
        'type',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new PaymentTransformer();
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

        $query = Payment::query()->where('company_id', $this->company->id)->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity){

            $this->csv->insertOne($this->buildRow($entity)); 

        });

        return $this->csv->toString(); 

    }

    private function buildRow(Payment $payment) :array
    {

        $transformed_entity = $this->entity_transformer->transform($payment);

        $entity = [];

        foreach(array_values($this->input['report_keys']) as $key){

            $entity[$key] = $transformed_entity[$key];
        
        }

        return $this->decorateAdvancedFields($payment, $entity);

    }

    private function decorateAdvancedFields(Payment $payment, array $entity) :array
    {

        if(array_key_exists('status_id', $entity))
            $entity['status_id'] = $payment->stringStatus($payment->status_id);

        if(array_key_exists('vendor_id', $entity))
            $entity['vendor_id'] = $payment->vendor()->exists() ? $payment->vendor->name : '';

        if(array_key_exists('project_id', $entity))
            $entity['project_id'] = $payment->project()->exists() ? $payment->project->name : '';

        if(array_key_exists('currency_id', $entity))
            $entity['currency_id'] = $payment->currency()->exists() ? $payment->currency->code : '';

        if(array_key_exists('exchange_currency_id', $entity))
            $entity['exchange_currency_id'] = $payment->exchange_currency()->exists() ? $payment->exchange_currency->code : '';

        if(array_key_exists('client_id', $entity))
            $entity['client_id'] = $payment->client->present()->name();

        if(array_key_exists('type_id', $entity))
            $entity['type_id'] = $payment->translatedType();

        return $entity;
    }

}
