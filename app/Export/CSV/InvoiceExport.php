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
use App\Models\Invoice;
use App\Transformers\CreditTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class InvoiceExport
{
    private $company;

    private $report_keys;

    private $invoice_transformer;

    private array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'client' => 'client_id',
        'custom_surcharge1' => 'custom_surcharge1',
        'custom_surcharge2' => 'custom_surcharge2',
        'custom_surcharge3' => 'custom_surcharge3',
        'custom_surcharge4' => 'custom_surcharge4',
        'country' => 'country_id',
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
        'tax_rate2' => 'tax_rate2',
        'tax_rate3' => 'tax_rate3',
        'terms' => 'terms',
        'total_taxes' => 'total_taxes',
        'currency' => 'currency'
    ];

    private array $decorate_keys = [
        'country',
        'client',
        'invoice',
        'currency',
    ];

    public function __construct(Company $company, array $report_keys)
    {
        $this->company = $company;
        $this->report_keys = $report_keys;
        $this->invoice_transformer = new CreditTransformer();
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

        Invoice::with('client')->where('company_id', $this->company->id)
                            ->where('is_deleted',0)
                            ->cursor()
                            ->each(function ($invoice){

                                $this->csv->insertOne($this->buildRow($invoice)); 

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

    private function buildRow(Credit $invoice) :array
    {

        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];

        foreach(array_values($this->report_keys) as $key){

                $entity[$key] = $transformed_invoice[$key];
        }

        return $this->decorateAdvancedFields($invoice, $entity);

    }

    private function decorateAdvancedFields(Credit $invoice, array $entity) :array
    {

        if(array_key_exists('country_id', $entity))
            $entity['country_id'] = $invoice->client->country ? ctrans("texts.country_{$invoice->client->country->name}") : ""; 

        if(array_key_exists('currency', $entity))
            $entity['currency'] = $invoice->client->currency()->code;

        if(array_key_exists('invoice_id', $entity))
            $entity['invoice_id'] = $invoice->invoice ? $invoice->invoice->number : "";

        if(array_key_exists('client_id', $entity))
            $entity['client_id'] = $invoice->client->present()->name();

        return $entity;
    }

}
