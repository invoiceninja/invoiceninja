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
use App\Transformers\DocumentTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class DocumentExport
{
    private $company;

    private $report_keys;

    private $entity_transformer;

    private array $entity_keys = [
        'record_type' => 'record_type',
        'record_name' => 'record_name',
        'name' => 'name',
        'type' => 'type',
        'created_at' => 'created_at',
    ];

    private array $decorate_keys = [

    ];

    public function __construct(Company $company, array $report_keys)
    {
        $this->company = $company;
        $this->report_keys = $report_keys;
        $this->entity_transformer = new DocumentTransformer();
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

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        Document::where('company_id', $this->company->id)
                ->cursor()
                ->each(function ($entity){

                    $this->csv->insertOne($this->buildRow($entity)); 

                });


        return $this->csv->toString(); 

    }

    private function buildHeader() :array
    {

        $header = [];

        foreach(array_keys($this->report_keys) as $key)
            $header[] = ctrans("texts.{$key}");

        return $header;
    }

    private function buildRow(Document $document) :array
    {

        $transformed_entity = $this->entity_transformer->transform($document);

        $entity = [];

        foreach(array_values($this->report_keys) as $key){

            $entity[$key] = $transformed_entity[$key];
        
        }

        return $this->decorateAdvancedFields($document, $entity);

    }

    private function decorateAdvancedFields(Document $document, array $entity) :array
    {

        if(array_key_exists('record_type', $entity))
            $entity['record_type'] = class_basename($document->documentable);

        if(array_key_exists('record_name', $entity))
            $entity['record_name'] = $document->hashed_id;

        return $entity;
    }

}
