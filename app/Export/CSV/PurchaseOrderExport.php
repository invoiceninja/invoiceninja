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
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PurchaseOrderExport extends BaseExport
{
    private Company $company;

    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    public array $entity_keys = [
        'amount' => 'amount',
        'balance' => 'balance',
        'vendor' => 'vendor_id',
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
        'currency_id' => 'currency_id',
    ];

    private array $decorate_keys = [
        'country',
        'currency_id',
        'status',
        'vendor',
        'project',
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
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->with('vendor')
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        // if(isset($this->input['status'])) {
        //     $query = $this->addPurchaseOrderStatusFilter($query, $this->input['status']);
        // }

        $query->cursor()
            ->each(function ($purchase_order) {
                $this->csv->insertOne($this->buildRow($purchase_order));
            });

        return $this->csv->toString();
    }

    private function buildRow(PurchaseOrder $purchase_order) :array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if(!$keyval) {
                $keyval = array_search(str_replace("invoice.", "", $key), $this->entity_keys) ?? $key;
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
        if (in_array('country_id', $this->input['report_keys'])) {
            $entity['country'] = $purchase_order->vendor->country ? ctrans("texts.country_{$purchase_order->vendor->country->name}") : '';
        }

        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency_id'] = $purchase_order->vendor->currency() ? $purchase_order->vendor->currency()->code : $purchase_order->company->currency()->code;
        }

        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $purchase_order->vendor->present()->name();
        }

        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $purchase_order->stringStatus($purchase_order->status_id);
        }

        return $entity;
    }
}
