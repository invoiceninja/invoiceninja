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
use App\Models\Company;
use App\Models\Invoice;
use App\Transformers\InvoiceTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class InvoiceItemExport extends BaseExport
{

    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private bool $force_keys = false;

    private array $storage_array = [];

    private array $storage_item_array = [];

    private array $decorate_keys = [
        'client',
        'currency_id',
        'status'
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->invoice_transformer = new InvoiceTransformer();
    }

    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->force_keys = true;
            $this->input['report_keys'] = array_values($this->mergeItemsKeys('invoice_report_keys'));
        }

        $query = Invoice::query()
                        ->withTrashed()
                        ->with('client')
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        return $query;

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use($headerdisplay){
                return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
            })->toArray();


        $query->cursor()
            ->each(function ($resource) {
                $this->iterateItems($resource);
                        
                foreach($this->storage_array as $row) {
                    $this->storage_item_array[] = $this->processItemMetaData($row, $resource);
                }

                $this->storage_array = [];
                        
            });
                
        return array_merge(['columns' => $header], $this->storage_item_array);
               
    }


    public function run()
    {
        $query = $this->init();
        
        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($invoice) {
                $this->iterateItems($invoice);
            });

        $this->csv->insertAll($this->storage_array);
        
        return $this->csv->toString();
    }

    private function iterateItems(Invoice $invoice)
    {
        $transformed_invoice = $this->buildRow($invoice);

        $transformed_items = [];

        foreach ($invoice->line_items as $item) {
            $item_array = [];      

            foreach (array_values(array_intersect($this->input['report_keys'], $this->item_report_keys)) as $key) { //items iterator produces item array
                
                if (str_contains($key, "item.")) {

                    $key = str_replace("item.", "", $key);
                    
                    if($key == 'type_id')
                        $key = 'type';

                    if($key == 'tax_id')
                        $key = 'tax_category';

                    if (property_exists($item, $key)) {
                        $item_array[$key] = $item->{$key};
                    } 
                    else {
                        $item_array[$key] = '';
                    }
                }
            }
            
            $transformed_items = array_merge($transformed_invoice, $item_array);
            $entity = $this->decorateAdvancedFields($invoice, $transformed_items);

            $this->storage_array[] = $entity;

        }
    }

    private function buildRow(Invoice $invoice) :array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
           
            $parts = explode('.', $key);

            if(is_array($parts) && $parts[0] == 'item')
                continue;

            if (is_array($parts) && $parts[0] == 'invoice' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            }else if (array_key_exists($key, $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$key];
            } 
            else {
                $entity[$key] = $this->resolveKey($key, $invoice, $this->invoice_transformer);
            }
        }

        return $this->decorateAdvancedFields($invoice, $entity);
    }

    private function decorateAdvancedFields(Invoice $invoice, array $entity) :array
    {
        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $invoice->client->currency() ? $invoice->client->currency()->code : $invoice->company->currency()->code;
        }

        if(array_key_exists('type', $entity)) {
            $entity['type'] = $invoice->typeIdString($entity['type']);
        }

        if(array_key_exists('tax_category', $entity)) {
            $entity['tax_category'] = $invoice->taxTypeString($entity['tax_category']);
        }

        return $entity;
    }

}
