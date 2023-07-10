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
        ]);
    }

    public function getPdf()
    {
        
        $this->pdf =  $this->entity->fullscreenPdfViewer($this->invitation);
        $pdf_service = new PdfService($this->invitation);
        

        // $company_details = $pdf_service->config->settings->pdf_variables->company_details;


        $pdf_service->config = (new PdfConfiguration($pdf_service))->init();


        $pdf_service->html_variables = $pdf_service->config->client ?
                                    (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                                    (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        $pdf_service->designer = (new PdfDesigner($pdf_service));
        $pdf_service->designer->template = '<div id="company-details"></div>';

        $pdf_service->builder = (new PdfBuilder($pdf_service));

$section = [
    'company-details' => [
        'id' => 'company-details',
        'elements' => $pdf_service->builder->companyDetails(),
    ]
];



$document = new \DOMDocument();
$document->validateOnParse = true;
@$document->loadHTML(mb_convert_encoding($pdf_service->designer->template, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$pdf_service->builder->document = $document;

$pdf_service->builder->sections = $section;

$html = $pdf_service->builder
            ->getEmptyElements()
            ->updateElementProperties()
            ->updateVariables();

nlog($html->getCompiledHTML());




        // nlog($section);

        // $document = new \DOMDocument();
        // @$document->loadHTML(mb_convert_encoding('<div></div>', 'HTML-ENTITIES', 'UTF-8'));
        // $pdf_service->designer->template = '';
        // $pdf_service->builder->document = $document;

        // $pdf_service->builder
        //             ->sections = $section;        

        // nlog($pdf_service->builder->sections);

        //    $html =  $pdf_service->builder
        //             ->getEmptyElements()
        //             ->updateElementProperties()
        //             ->updateVariables();

        // nlog($html->getCompiledHTML());
    }

    public function getHtml()
    {


    }
}
