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

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Credit;
use League\Csv\Writer;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Transformers\CreditTransformer;
use Illuminate\Contracts\Database\Eloquent\Builder;

class CreditExport extends BaseExport
{

    private CreditTransformer $credit_transformer;

    public string $date_key = 'created_at';

    public Writer $csv;

    public array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'client' => 'client_id',
        'country' => 'country_id',
        'custom_surcharge1' => 'custom_surcharge1',
        'custom_surcharge2' => 'custom_surcharge2',
        'custom_surcharge3' => 'custom_surcharge3',
        'custom_surcharge4' => 'custom_surcharge4',
        'currency' => 'currency',
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
    ];

    private array $decorate_keys = [
        'country',
        'client',
        'invoice',
        'currency',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->credit_transformer = new CreditTransformer();
    }

    public function returnJson()
    {
        $query = $this->init();

        $header = $this->buildHeader();

        $report = $query->cursor()
                ->map(function ($credit) {
                    $row = $this->buildRow($credit);
                    return $this->processMetaData($row, $credit);
                })->toArray();

        return array_merge([$header], $report);
    }

    private function processMetaData(array $row, Credit $credit): array
    {
        $clean_row = [];

        foreach ($this->input['report_keys'] as $key => $value) {
            
            $report_keys = explode(".", $value);
            
            $column_key = str_replace("credit.", "", $value);
            $column_key = array_search($column_key, $this->entity_keys);

            $clean_row[$key]['entity'] = $report_keys[0];
            $clean_row[$key]['id'] = $report_keys[1] ?? $report_keys[0];
            $clean_row[$key]['hashed_id'] = $report_keys[0] == 'credit' ? null : $credit->{$report_keys[0]}->hashed_id ?? null;
            $clean_row[$key]['value'] = $row[$column_key];

            if(in_array($clean_row[$key]['id'], ['amount', 'balance', 'partial', 'refunded', 'applied','unit_cost','cost','price']))
                $clean_row[$key]['display_value'] = Number::formatMoney($row[$column_key], $credit->client);
            else
                $clean_row[$key]['display_value'] = $row[$column_key];

        }

        return $clean_row;
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
            // $this->input['report_keys'] = collect(array_values($this->entity_keys))->map(function ($value){

            //     // if(in_array($value,['client_id','country_id']))
            //     //     return $value;
            //     // else
            //         return 'credit.'.$value;
            // })->toArray();
            
        }

        $query = Credit::query()
                        ->withTrashed()
                        ->with('client')->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        return $query;
    }

    public function run(): string
    {
        $query = $this->init();
        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());
        // nlog($this->input['report_keys']);

        $query->cursor()
            ->each(function ($credit) {
                // nlog($this->buildRow($credit));
                $this->csv->insertOne($this->buildRow($credit));
            });

        return $this->csv->toString();
    }

    private function buildRow(Credit $credit) :array
    {
        $transformed_credit = $this->credit_transformer->transform($credit);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if(!$keyval)
                $keyval = array_search(str_replace("credit.", "", $key), $this->entity_keys) ?? $key;

            if(!$keyval)
                $keyval = $key;
                
            if (array_key_exists($key, $transformed_credit)) {
                $entity[$keyval] = $transformed_credit[$key];
            } elseif (array_key_exists($keyval, $transformed_credit)) {
                $entity[$keyval] = $transformed_credit[$keyval];
            }
            else {
                $entity[$keyval] = $this->resolveKey($keyval, $credit, $this->credit_transformer);
            }
        }

        return $this->decorateAdvancedFields($credit, $entity);
    }

    private function decorateAdvancedFields(Credit $credit, array $entity) :array
    {
        if (in_array('country_id', $this->input['report_keys'])) {
            $entity['country'] = $credit->client->country ? ctrans("texts.country_{$credit->client->country->name}") : '';
        }
        
        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency_id'] = $credit->client->currency() ? $credit->client->currency()->code : $credit->company->currency()->code;
        }

        if (in_array('invoice_id', $this->input['report_keys'])) {
            $entity['invoice'] = $credit->invoice ? $credit->invoice->number : '';
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $credit->client->present()->name();
        }

        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $credit->stringStatus($credit->status_id);
        }

        if(in_array('credit.status', $this->input['report_keys'])) {
            $entity['credit.status'] = $credit->stringStatus($credit->status_id);
        }

        return $entity;
    }
}
