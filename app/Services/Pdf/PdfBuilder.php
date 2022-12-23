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

namespace App\Services\Pdf;

use DOMDocument;
use DOMXPath;

class PdfBuilder
{

    public PdfService $service;

    public function __construct(PdfService $service)
    {
        $this->service = $service;
    }

    public function build()
    {

        $this->getTemplate()
             ->buildSections();

    }

    private function getTemplate() :self
    {

        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML(mb_convert_encoding($this->service->config->designer->template, 'HTML-ENTITIES', 'UTF-8'));

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    private function buildSections() :self
    {
        $this->sections = [
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->companyAddress(),
            ],
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->entityDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
            ],
            'statement-invoice-table' => [
                'id' => 'statement-invoice-table',
                'elements' => $this->statementInvoiceTable(),
            ],
            'statement-invoice-table-totals' => [
                'id' => 'statement-invoice-table-totals',
                'elements' => $this->statementInvoiceTableTotals(),
            ],
            'statement-payment-table' => [
                'id' => 'statement-payment-table',
                'elements' => $this->statementPaymentTable(),
            ],
            'statement-payment-table-totals' => [
                'id' => 'statement-payment-table-totals',
                'elements' => $this->statementPaymentTableTotals(),
            ],
            'statement-aging-table' => [
                'id' => 'statement-aging-table',
                'elements' => $this->statementAgingTable(),
            ],
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->tableTotals(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [
                    $this->sharedFooterElements(),
                ],
            ],
        ];        

        return $this;
        
    }



 //         if (isset($this->data['template']) && isset($this->data['variables'])) {
 //            $this->getEmptyElements($this->data['template'], $this->data['variables']);
 //        }

 //        if (isset($this->data['template'])) {
 //            $this->updateElementProperties($this->data['template']);
 //        }

 //        if (isset($this->data['variables'])) {
 //            $this->updateVariables($this->data['variables']);
 //        }

 //        return $this;



    
}