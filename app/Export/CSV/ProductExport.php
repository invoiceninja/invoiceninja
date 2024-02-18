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
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\ProductTransformer;
use Illuminate\Database\Eloquent\Builder;

class ProductExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'created_at';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new ProductTransformer();
        $this->decorator = new Decorator();
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

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->product_report_keys);
        }

        $query = Product::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        if($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($entity) {
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Product $product): array
    {
        $transformed_entity = $this->entity_transformer->transform($product);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->product_report_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                // nlog($key);
                $entity[$key] = $this->decorator->transform($key, $product);
                // $entity[$key] = '';

            }
        }

        return $entity;
        // return $this->decorateAdvancedFields($product, $entity);
    }

    private function decorateAdvancedFields(Product $product, array $entity): array
    {
        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $product->vendor()->exists() ? $product->vendor->name : '';
        }

        // if (array_key_exists('project_id', $this->input['report_keys'])) {
        //     $entity['project'] = $product->project()->exists() ? $product->project->name : '';
        // }

        return $entity;
    }
}
