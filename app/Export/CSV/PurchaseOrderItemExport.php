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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Transformers\PurchaseOrderTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PurchaseOrderItemExport extends BaseExport
{

    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private bool $force_keys = false;

    public array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'vendor' => 'vendor_id',
        'vendor_number' => 'vendor.number',
        'vendor_id_number' => 'vendor.id_number',
        // 'custom_surcharge1' => 'custom_surcharge1',
        // 'custom_surcharge2' => 'custom_surcharge2',
        // 'custom_surcharge3' => 'custom_surcharge3',
        // 'custom_surcharge4' => 'custom_surcharge4',
        // 'custom_value1' => 'custom_value1',
        // 'custom_value2' => 'custom_value2',
        // 'custom_value3' => 'custom_value3',
        // 'custom_value4' => 'custom_value4',
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
        'purchase_order1' => 'item.custom_value1',
        'purchase_order2' => 'item.custom_value2',
        'purchase_order3' => 'item.custom_value3',
        'purchase_order4' => 'item.custom_value4',
        'tax_category' => 'item.tax_id',
        'type' => 'item.type_id',
    ];

    private array $decorate_keys = [
        'client',
        'currency_id',
        'status'
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->purchase_order_transformer = new PurchaseOrderTransformer();
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
            $this->force_keys = true;
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->with('vendor')->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
            ->each(function ($purchase_order) {
                $this->iterateItems($purchase_order);
            });

        return $this->csv->toString();
    }

    private function iterateItems(PurchaseOrder $purchase_order)
    {
        $transformed_purchase_order = $this->buildRow($purchase_order);

        $transformed_items = [];

        foreach ($purchase_order->line_items as $item) {
            $item_array = [];

            foreach (array_values($this->input['report_keys']) as $key) { //items iterator produces item array
                
                if (str_contains($key, "item.")) {

                    $key = str_replace("item.", "", $key);
                    
                    $keyval = $key;

                    $keyval = str_replace("custom_value", "purchase_order", $key);

                    if($key == 'type_id') {
                        $keyval = 'type';
                    }

                    if($key == 'tax_id') {
                        $keyval = 'tax_category';
                    }

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

            $transformed_items = array_merge($transformed_purchase_order, $item_array);
            $entity = $this->decorateAdvancedFields($purchase_order, $transformed_items);

            $this->csv->insertOne($entity);
        }
    }

    private function buildRow(PurchaseOrder $purchase_order) :array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if(!$keyval) {
                $keyval = array_search(str_replace("purchase_order.", "", $key), $this->entity_keys) ?? $key;
            }

            if(!$keyval) {
                $keyval = $key;
            }

            if (array_key_exists($key, $transformed_purchase_order)) {
                $entity[$keyval] = $transformed_purchase_order[$key];
            } elseif (array_key_exists($keyval, $transformed_purchase_order)) {
                $entity[$keyval] = $transformed_purchase_order[$keyval];
            } else {
                $entity[$keyval] = $this->resolveKey($keyval, $purchase_order, $this->purchase_order_transformer);
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
