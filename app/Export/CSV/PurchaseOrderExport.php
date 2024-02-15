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
use App\Export\Decorators\Decorator;
use Illuminate\Database\Eloquent\Builder;
use App\Transformers\PurchaseOrderTransformer;

class PurchaseOrderExport extends BaseExport
{
    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public array $entity_keys = [
        'amount' => 'purchase_order.amount',
        'balance' => 'purchase_order.balance',
        'vendor' => 'purchase_order.vendor_id',
        // 'custom_surcharge1' => 'purchase_order.custom_surcharge1',
        // 'custom_surcharge2' => 'purchase_order.custom_surcharge2',
        // 'custom_surcharge3' => 'purchase_order.custom_surcharge3',
        // 'custom_surcharge4' => 'purchase_order.custom_surcharge4',
        'custom_value1' => 'purchase_order.custom_value1',
        'custom_value2' => 'purchase_order.custom_value2',
        'custom_value3' => 'purchase_order.custom_value3',
        'custom_value4' => 'purchase_order.custom_value4',
        'date' => 'purchase_order.date',
        'discount' => 'purchase_order.discount',
        'due_date' => 'purchase_order.due_date',
        'exchange_rate' => 'purchase_order.exchange_rate',
        'footer' => 'purchase_order.footer',
        'number' => 'purchase_order.number',
        'paid_to_date' => 'purchase_order.paid_to_date',
        'partial' => 'purchase_order.partial',
        'partial_due_date' => 'purchase_order.partial_due_date',
        'po_number' => 'purchase_order.po_number',
        'private_notes' => 'purchase_order.private_notes',
        'public_notes' => 'purchase_order.public_notes',
        'status' => 'purchase_order.status',
        'tax_name1' => 'purchase_order.tax_name1',
        'tax_name2' => 'purchase_order.tax_name2',
        'tax_name3' => 'purchase_order.tax_name3',
        'tax_rate1' => 'purchase_order.tax_rate1',
        'tax_rate2' => 'purchase_order.tax_rate2',
        'tax_rate3' => 'purchase_order.tax_rate3',
        'terms' => 'purchase_order.terms',
        'total_taxes' => 'purchase_order.total_taxes',
        'currency_id' => 'purchase_order.currency_id',
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
        $this->decorator = new Decorator();
    }


    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->purchase_order_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_vendor_fields, $this->input['report_keys']));

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->with('vendor')
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        if($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($resource) {
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }


    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($purchase_order) {
                $this->csv->insertOne($this->buildRow($purchase_order));
            });

        return $this->csv->toString();
    }

    private function buildRow(PurchaseOrder $purchase_order): array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'purchase_order' && array_key_exists($parts[1], $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$parts[1]];
            } else {
                // nlog($key);
                $entity[$key] = $this->decorator->transform($key, $purchase_order);
                // $entity[$key] = '';

                // $entity[$key] = $this->resolveKey($key, $purchase_order, $this->purchase_order_transformer);
            }


        }
        // return $entity;
        return $this->decorateAdvancedFields($purchase_order, $entity);
    }

    private function decorateAdvancedFields(PurchaseOrder $purchase_order, array $entity): array
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

        if (in_array('purchase_order.status', $this->input['report_keys'])) {
            $entity['purchase_order.status'] = $purchase_order->stringStatus($purchase_order->status_id);
        }

        if (in_array('purchase_order.user_id', $this->input['report_keys'])) {
            $entity['purchase_order.user_id'] = $purchase_order->user ? $purchase_order->user->present()->name() : '';
        }

        if (in_array('purchase_order.assigned_user_id', $this->input['report_keys'])) {
            $entity['purchase_order.assigned_user_id'] = $purchase_order->assigned_user ? $purchase_order->assigned_user->present()->name() : '';
        }


        return $entity;
    }
}
