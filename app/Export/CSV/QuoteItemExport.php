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
use App\Models\Client;
use App\Models\Company;
use App\Models\Quote;
use App\Transformers\QuoteTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class QuoteItemExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $quote_transformer;

    protected string $date_key = 'date';

    protected array $entity_keys = [
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
        'qty' => 'item.quantity',
        'unit_cost' => 'item.cost',
        'product_key' => 'item.product_key',
        'cost' => 'item.product_cost',
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
        // 'gross_line_total' => 'item.gross_line_total',
        'custom_value1' => 'item.custom_value1',
        'custom_value2' => 'item.custom_value2',
        'custom_value3' => 'item.custom_value3',
        'custom_value4' => 'item.custom_value4',
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

        foreach ($quote->line_items as $item) {
            $item_array = [];

            foreach (array_values($this->input['report_keys']) as $key) {
                if (str_contains($key, 'item.')) {
                    $key = str_replace('item.', '', $key);
                    $item_array[$key] = $item->{$key};
                }
            }

            $entity = [];

            foreach (array_values($this->input['report_keys']) as $key) {
                $keyval = array_search($key, $this->entity_keys);

                if (array_key_exists($key, $transformed_items)) {
                    $entity[$keyval] = $transformed_items[$key];
                } else {
                    $entity[$keyval] = '';
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

            if (array_key_exists($key, $transformed_quote)) {
                $entity[$keyval] = $transformed_quote[$key];
            } else {
                $entity[$keyval] = '';
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
