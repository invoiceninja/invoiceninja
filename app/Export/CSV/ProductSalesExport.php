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
use App\Models\Invoice;
use App\Models\Product;
use App\Transformers\ProductTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;
use Illuminate\Support\Carbon;

class ProductSalesExport extends BaseExport
{
    private Company $company;

    protected array $input;

    protected $date_key = 'created_at';

    protected array $entity_keys = [
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'product_key' => 'product_key',
        'notes' => 'notes',
        'cost' => 'cost',
        'price' => 'price',
        'quantity' => 'quantity',
        'tax_rate1' => 'tax_rate1',
        'tax_rate2' => 'tax_rate2',
        'tax_rate3' => 'tax_rate3',
        'tax_name1' => 'tax_name1',
        'tax_name2' => 'tax_name2',
        'tax_name3' => 'tax_name3',
        'is_amount_discount' => 'is_amount_discount',
        'discount' => 'discount',
        'line_total' => 'line_total',
        'gross_line_total' => 'gross_line_total',
        'status' => 'status',
        'date' => 'date',
        'currency' => 'currency',
        'client' => 'client',
    ];

    private array $decorate_keys = [
        'client',
        'currency',
        'date',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
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

        $query = Invoice::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0)
                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($invoice) {

                  foreach($invoice->line_items as $item)
                    $this->csv->insertOne($this->buildRow($invoice, $item));

              });

        return $this->csv->toString();
    }

    private function buildRow($invoice, $invoice_item) :array
    {
        $transformed_entity = (array)$invoice_item;

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($invoice, $entity);
    }

    private function decorateAdvancedFields(Invoice $invoice, $entity) :array
    {
        $entity['client'] = $invoice->client->present()->name();
        $entity['currency'] = $invoice->client->currency()->code;
        $entity['status'] = $invoice->stringStatus($invoice->status_id);
        $entity['date'] = Carbon::parse($invoice->date)->format($this->company->date_format());

        return $entity;
    }
}
