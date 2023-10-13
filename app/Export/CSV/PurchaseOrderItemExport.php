<?php
/**
 * PurchaseOrder Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. PurchaseOrder Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Builder;
use App\Transformers\PurchaseOrderTransformer;

class PurchaseOrderItemExport extends BaseExport
{

    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private bool $force_keys = false;

    private array $storage_array = [];

    private array $storage_item_array = [];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->purchase_order_transformer = new PurchaseOrderTransformer();
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->mergeItemsKeys('purchase_order_report_keys'));
        }

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->with('vendor')->where('company_id', $this->company->id)
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
        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        $query = $this->init();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($purchase_order) {
                $this->iterateItems($purchase_order);
            });

        $this->csv->insertAll($this->storage_array);
        
        return $this->csv->toString();

    }

    private function iterateItems(PurchaseOrder $purchase_order)
    {
        $transformed_purchase_order = $this->buildRow($purchase_order);

        $transformed_items = [];

        foreach ($purchase_order->line_items as $item) {
            $item_array = [];

            foreach (array_values(array_intersect($this->input['report_keys'], $this->item_report_keys)) as $key) { //items iterator produces item array
                
                if (str_contains($key, "item.")) {

                    $key = str_replace("item.", "", $key);
                    
                    if($key == 'type_id') {
                        $keyval = 'type';
                    }

                    if($key == 'tax_id') {
                        $keyval = 'tax_category';
                    }

                    if (property_exists($item, $key)) {
                        $item_array[$key] = $item->{$key};
                    } else {
                        $item_array[$key] = '';
                    }
                }
            }

            $transformed_items = array_merge($transformed_purchase_order, $item_array);
            $entity = $this->decorateAdvancedFields($purchase_order, $transformed_items);

            $this->storage_array[] = $entity;
        }
    }

    private function buildRow(PurchaseOrder $purchase_order) :array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if(is_array($parts) && $parts[0] == 'item') {
                continue;
            }

            if (is_array($parts) && $parts[0] == 'purchase_order' && array_key_exists($parts[1], $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$parts[1]];
            } elseif (array_key_exists($key, $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$key];
            } else {
                $entity[$key] = $this->resolveKey($key, $purchase_order, $this->purchase_order_transformer);
            }
        }

        return $this->decorateAdvancedFields($purchase_order, $entity);
    }

    private function decorateAdvancedFields(PurchaseOrder $purchase_order, array $entity) :array
    {
        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $purchase_order->vendor->currency() ? $purchase_order->vendor->currency()->code : $purchase_order->company->currency()->code;
        }

        if(array_key_exists('type', $entity)) {
            $entity['type'] = $purchase_order->typeIdString($entity['type']);
        }

        if(array_key_exists('tax_category', $entity)) {
            $entity['tax_category'] = $purchase_order->taxTypeString($entity['tax_category']);
        }

        if($this->force_keys) {
            $entity['vendor'] = $purchase_order->vendor->present()->name();
            $entity['vendor_id_number'] = $purchase_order->vendor->id_number;
            $entity['vendor_number'] = $purchase_order->vendor->number;
            $entity['status'] = $purchase_order->stringStatus($purchase_order->status_id);
        }

        return $entity;
    }

}
