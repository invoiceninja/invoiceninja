<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use DOMDocument;
use Tests\TestCase;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;

class PdfBuilderJsonVariableReplacementTest extends TestCase
{
    public function testJsonDesignVariablesAreReplacedInDomWithoutFullReparse(): void
    {
        $builder = new PdfBuilder($this->pdfService());

        $document = new DOMDocument();
        @$document->loadHTML(
            '<!DOCTYPE html><html><body>'
            . '<div id="plain">$invoice.number_label: $invoice.number</div>'
            . '<div id="html">$html_value</div>'
            . '<div id="encoded" data-state="encoded-html">$html_value</div>'
            . '<img id="image" alt="$invoice.number">'
            . '</body></html>'
        );

        $builder->setDocument($document);
        $builder->updateVariables();

        $this->assertSame('Invoice #: INV-001', $document->getElementById('plain')->textContent);
        $this->assertSame('Strong text', $document->getElementById('html')->textContent);
        $this->assertSame(1, $document->getElementById('html')->getElementsByTagName('strong')->length);
        $this->assertSame('<strong>Strong text</strong>', $document->getElementById('encoded')->textContent);
        $this->assertSame('INV-001', $document->getElementById('image')->getAttribute('alt'));
    }

    private function pdfService(): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $service->document_type = 'json_design';
        $service->html_variables = [
            'labels' => [
                '$invoice.number_label' => 'Invoice #',
            ],
            'values' => [
                '$invoice.number' => 'INV-001',
                '$html_value' => '<strong>Strong text</strong>',
            ],
        ];

        return $service;
    }
}
