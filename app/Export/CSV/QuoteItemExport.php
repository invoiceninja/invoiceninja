<?php
/**
 * Quote Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Quote;
use App\Transformers\QuoteTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class QuoteItemExport extends BaseExport
{

    private $quote_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    public array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'client' => 'client_id',
        'custom_surcharge1' => 'custom_surcharge1',
        'custom_surcharge2' => 'custom_surcharge2',
        'custom_surcharge3' => 'custom_surcharge3',
        'custom_surcharge4' => 'custom_surcharge4',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'date' => 'date',
        'discount' => 'discount',
        'due_date' => 'due_date',
        'exchange_rate' => 'exchange_rate',
        'footer' => 'footer',
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
        'currency' => 'currency_id',
        'quantity' => 'item.quantity',
        'cost' => 'item.cost',
        'product_key' => 'item.product_key',
        'buy_price' => 'item.product_cost',
        'cost' => 'item.cost',
        'notes' => 'item.notes',
        'discount' => 'item.discount',
        'is_amount_discount' => 'item.is_amount_discount',
        'tax_rate1' => 'item.tax_rate1',
        'tax_rate2' => 'item.tax_rate2',
        'tax_rate3' => 'item.tax_rate3',
        'tax_name1' => 'item.tax_name1',
        'tax_name2' => 'item.tax_name2',
        'tax_name3' => 'item.tax_name3',
        'line_total' => 'item.line_total',
        'gross_line_total' => 'item.gross_line_total',
        'quote1' => 'item.custom_value1',
        'quote2' => 'item.custom_value2',
        'quote3' => 'item.custom_value3',
        'quote4' => 'item.custom_value4',
        'tax_category' => 'item.tax_id',
        'type' => 'item.type_id',
    ];

    private array $decorate_keys = [
        'client',
        'currency',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->quote_transformer = new QuoteTransformer();
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

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = Quote::query()
                        ->withTrashed()
                        ->with('client')->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
            ->each(function ($quote) {
                $this->iterateItems($quote);
            });

        return $this->csv->toString();
    }

    private function iterateItems(Quote $quote)
    {
        $transformed_quote = $this->buildRow($quote);

        $transformed_items = [];

        $transformed_items = [];

        foreach ($quote->line_items as $item) {
            $item_array = [];      

            foreach (array_values($this->input['report_keys']) as $key) { //items iterator produces item array
                
                if (str_contains($key, "item.")) {

                    $key = str_replace("item.", "", $key);
                    
                    $keyval = $key;

                    $keyval = str_replace("custom_value", "quote", $key);

                    if($key == 'type_id')
                        $keyval = 'type';

                    if($key == 'tax_id')
                        $keyval = 'tax_category';

                    if (property_exists($item, $key)) {
                        $item_array[$keyval] = $item->{$key};
                    } else {
                        $item_array[$keyval] = '';
                    }
                }
            }

            $entity = [];

            foreach (array_values($this->input['report_keys']) as $key) { //create an array of report keys only 
                $keyval = array_search($key, $this->entity_keys); 

                if (array_key_exists($key, $transformed_items)) {
                    $entity[$keyval] = $transformed_items[$key];
                } else {
                    $entity[$keyval] = "";
                }
            }

            $transformed_items = array_merge($transformed_quote, $item_array);
            $entity = $this->decorateAdvancedFields($quote, $transformed_items);

            $this->csv->insertOne($entity);
        }
    }

    private function buildRow(Quote $quote) :array
    {
        $transformed_quote = $this->quote_transformer->transform($quote);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if(!$keyval) {
                $keyval = array_search(str_replace("quote.", "", $key), $this->entity_keys) ?? $key;
            }

            if(!$keyval) {
                $keyval = $key;
            }

            if (array_key_exists($key, $transformed_quote)) {
                $entity[$keyval] = $transformed_quote[$key];
            } elseif (array_key_exists($keyval, $transformed_quote)) {
                $entity[$keyval] = $transformed_quote[$keyval];
            }
             else {
                $entity[$keyval] = $this->resolveKey($keyval, $quote, $this->quote_transformer);
            }
        }

        return $this->decorateAdvancedFields($quote, $entity);
    }
    private function decorateAdvancedFields(Quote $quote, array $entity) :array
    {
        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $quote->client->currency() ? $quote->client->currency()->code : $quote->company->currency()->code;
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $quote->client->present()->name();
        }

        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $quote->stringStatus($quote->status_id);
        }

        return $entity;
    }
}
