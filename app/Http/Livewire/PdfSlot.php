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

namespace App\Http\Livewire;

use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Utils\VendorHtmlEngine;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfDesigner;
use App\Services\Pdf\PdfConfiguration;

class PdfSlot extends Component
{
    public $invitation;

    public $db;

    public $entity;

    public $pdf;

    public $url;
    
    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        return render('components.livewire.pdf-slot', [
            'invitation' => $this->invitation,
            'entity' => $this->entity,
            'data' => $this->invitation->company->settings
        ]);
    }

    public function getPdf()
    {
        
        // $this->pdf =  $this->entity->fullscreenPdfViewer($this->invitation);


    }

    public function getHtml()
    {
        $pdf_service = new PdfService($this->invitation);
                
        $pdf_service->config = (new PdfConfiguration($pdf_service))->init();

        $pdf_service->html_variables = $pdf_service->config->client ?
                                    (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                                    (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        
    }

    public function getHtmlX()
    {

        $pdf_service = new PdfService($this->invitation);
                
        $pdf_service->config = (new PdfConfiguration($pdf_service))->init();

        $pdf_service->html_variables = $pdf_service->config->client ?
                                    (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                                    (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        $pdf_service->designer = (new PdfDesigner($pdf_service));

        $pdf_service->builder = (new PdfBuilder($pdf_service));

        $data = [];

        foreach(['company-details', 'company-address','client-details','entity-details','product-table','table-totals'] as $item) {


        $pdf_service->designer->template = '<div id="'.$item.'"></div>';

        match($item){
            'company-details' => $block = $pdf_service->builder->companyDetails(), 
            'company-address' => $block = $pdf_service->builder->companyAddress(),
            'client-details' => $block = $pdf_service->builder->clientDetails(),
            'entity-details' => $block = $pdf_service->builder->invoiceDetails(),
            'product-table' => $block = $this->productTable(),
            'table-totals' => $block = $pdf_service->builder->getTableTotals(),
            default => $block = [],
        };

            $section = [
                $item => [
                    'id' => $item,
                    'elements' => $block,
                ]
            ];

            $document = new \DOMDocument();
            $document->validateOnParse = true;
            @$document->loadHTML(mb_convert_encoding($pdf_service->designer->template, 'HTML-ENTITIES', 'UTF-8'));

            $pdf_service->builder->document = $document;
            $pdf_service->builder->sections = $section;

            $html = $pdf_service->builder
                        ->getEmptyElements()
                        ->updateElementProperties()
                        ->updateVariables();

            // $pdf_service->builder->document->removeChild($pdf_service->builder->document->doctype);
            // $pdf_service->builder->document->replaceChild($pdf_service->builder->document->firstChild->firstChild->firstChild, $pdf_service->builder->document->firstChild);

            $data[$item] = $pdf_service->builder->document->saveHTML();

            $section = [];
        }
        // nlog($pdf_service->builder->document->saveHTML());

        nlog($data);

        return $data;
    }

    private function productTable()
    {

    }

    private function sectionBuilder($tag)
    {
        
    }
}
