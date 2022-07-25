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
use App\Models\Product;
use App\Transformers\ProductTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ProductExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $entity_transformer;

    protected $date_key = 'created_at';

    protected array $entity_keys = [
        'project' => 'project_id',
        'vendor' => 'vendor_id',
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
    ];

    private array $decorate_keys = [
        'vendor',
        'project',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new ProductTransformer();
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

        $query = Product::query()->where('company_id', $this->company->id)->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity) {
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Product $product) :array
    {
        $transformed_entity = $this->entity_transformer->transform($product);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($product, $entity);
    }

    private function decorateAdvancedFields(Product $product, array $entity) :array
    {
        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $product->vendor()->exists() ? $product->vendor->name : '';
        }

        if (array_key_exists('project_id', $this->input['report_keys'])) {
            $entity['project'] = $product->project()->exists() ? $product->project->name : '';
        }

        return $entity;
    }
}
