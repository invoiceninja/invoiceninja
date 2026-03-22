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

use App\Jobs\EDocument\CreateEDocument;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Utils\Gotenberg\GotenbergPdf;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\VendorHtmlEngine;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;

class PdfService
{
    use PdfMaker;
    use PageNumbering;

    public InvoiceInvitation|QuoteInvitation|CreditInvitation|RecurringInvoiceInvitation|PurchaseOrderInvitation $invitation;

    public Company $company;

    public PdfConfiguration $config;

    public PdfBuilder $builder;

    public PdfDesigner $designer;

    public array $html_variables;

    public string $document_type;

    public array $options;

    private float $start_time;

    public float $execution_time;

    private ?string $json_design_html = null;

    public const DELIVERY_NOTE = 'delivery_note';
    public const STATEMENT = 'statement';
    public const PURCHASE_ORDER = 'purchase_order';
    public const PRODUCT = 'product';

    public function __construct($invitation, $document_type = 'product', $options = [])
    {
        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->document_type = $document_type;

        $this->options = $options;

        $this->start_time = microtime(true);
    }

    public function boot(): self
    {

        $this->init();

        return $this;
    }

    /**
     * Resolves the PDF generation type and
     * attempts to generate a PDF from the HTML
     * string.
     *
     * @return mixed | Exception
     *
     */
    public function getPdf()
    {
        try {

            $html = $this->getHtml();
            // nlog($html);
            $pdf = $this->resolvePdfEngine($html);

            $numbered_pdf = $this->pageNumbering($pdf, $this->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            if ($this->config->entity_string == "invoice" && $this->config->settings->enable_e_invoice) {
                $pdf = $this->checkEInvoice($pdf);
            }

        } catch (\Exception $e) {
            nlog($e->getMessage());
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $this->execution_time = microtime(true) - $this->start_time;

        return $pdf;
    }

    /**
     * Renders the dom document to HTML
     *
     * @return string
     *
     */
    public function getHtml(): string
    {
        // If JSON design was used, return the pre-generated HTML
        if ($this->json_design_html !== null) {
            $html = \App\Services\Pdf\Purify::clean($this->json_design_html);

            if (config('ninja.log_pdf_html')) {
                nlog($html);
            }

            return $html;
        }

        $html = \App\Services\Pdf\Purify::clean($this->builder->document->saveHTML());

        if (config('ninja.log_pdf_html')) {
            nlog($html);
        }
        
        return $html;
    }

    /**
     * Initialize all the services to build the PDF
     *
     * @return self
     */
    public function init(): self
    {
        $this->start_time = microtime(true);

        $this->config = (new PdfConfiguration($this))->init();

        $this->html_variables = ($this->invitation instanceof \App\Models\PurchaseOrderInvitation)
                                    ? (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues()
                                    : (new HtmlEngine($this->invitation))->generateLabelsAndValues();

        // Check if this is a JSON-based design
        if ($this->isJsonDesign()) {
            nlog("Using JSON Design Service for PDF generation");
            $this->buildWithJsonDesign();
        } else {
            // Traditional flow
            $this->designer = (new PdfDesigner($this))->build();
            $this->builder = (new PdfBuilder($this))->build();
        }

        return $this;
    }

    /**
     * Check if the current design is a JSON-based design
     *
     * @return bool
     */
    private function isJsonDesign(): bool
    {
        if (!isset($this->config->design)) {
            return false;
        }

        $design = $this->config->design;

        if ($design->is_custom && is_object($design->design)) {
            $designData = json_decode(json_encode($design->design), true);
            return isset($designData['blocks']);
        }

        return false;
    }

    /**
     * Build PDF using JSON Design Service
     *
     * @return void
     */
    private function buildWithJsonDesign(): void
    {
        // Convert design object to array
        $designData = json_decode(json_encode($this->config->design->design), true);

        // Ensure pageSettings exists (use defaults if missing)
        if (!isset($designData['pageSettings'])) {
            $designData['pageSettings'] = [
                'pageSize' => 'a4',
                'orientation' => 'portrait',
                'marginTop' => '10mm',
                'marginRight' => '10mm',
                'marginBottom' => '10mm',
                'marginLeft' => '10mm',
                'fontFamily' => 'Inter, sans-serif',
                'fontSize' => '12px',
                'textColor' => '#374151',
                'lineHeight' => '1.5',
                'backgroundColor' => '#ffffff',
            ];
            nlog("No pageSettings found, using defaults");
        }

        nlog("Attempting to build PDF with JSON Design Service");
        nlog("Design data keys: " . json_encode(array_keys($designData)));
        nlog("Blocks count: " . count($designData['blocks'] ?? []));

        // Create JSON design service
        $jsonService = new JsonDesignService($this, $designData);

        // Validate the design
        if (!$jsonService->isValid()) {
            nlog("Invalid JSON design structure - cannot use JSON designer or traditional fallback");
            throw new \Exception("Invalid JSON design structure. Design must have 'blocks' and valid block structure.");
        }

        // Set document type to prevent default section generation
        $this->document_type = 'json_design';

        // Initialize designer with empty template (will be set by JsonDesignService)
        $this->designer = new PdfDesigner($this);
        $this->designer->template = '';

        // Build the HTML using JSON design service
        try {
            nlog("Building HTML with JSON Design Service");
            nlog("HTML variables count: " . count($this->html_variables['values'] ?? []));
            nlog("Sample variables: " . json_encode(array_slice($this->html_variables['values'] ?? [], 0, 5)));

            $this->json_design_html = $jsonService->build();

            nlog("JSON Design Service build completed successfully");
            nlog("HTML length: " . strlen($this->json_design_html));

            // Check if variables were replaced
            $hasUnreplacedVars = preg_match('/\$company\.|invoice\.|client\./', $this->json_design_html);
            nlog("Has unreplaced variables: " . ($hasUnreplacedVars ? 'YES ⚠️' : 'NO ✓'));

            if ($hasUnreplacedVars) {
                nlog("WARNING: Variables were not replaced! Checking first 500 chars:");
                nlog(substr($this->json_design_html, 0, 500));
            }
        } catch (\Exception $e) {
            nlog("JSON Design Service failed: " . $e->getMessage());
            nlog("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw instead of falling back since traditional flow won't work with JSON structure
        }

        // Create a minimal builder instance for compatibility
        // Initialize it with a minimal DOM document to prevent null errors
        $this->builder = new PdfBuilder($this);
        $this->builder->document = new \DOMDocument();
        $this->builder->document->loadHTML('<!DOCTYPE html><html><body></body></html>');

        nlog("JSON Design build complete");
    }

    /**
     * resolvePdfEngine
     *
     * @return mixed
     */
    public function resolvePdfEngine(string $html): mixed
    {
        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom())->convertHtmlToPdf($html);
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html);
        } elseif (config('ninja.pdf_generator') == 'gotenberg') {
            $pdf = (new GotenbergPdf())->convertHtmlToPdf($html);
        } else {
            $pdf = $this->makePdf(null, null, $html);
        }

        return $pdf;
    }

    /**
     * Switch to determine if we need to embed the xml into the PDF itself
     *
     * @param  string $pdf
     * @return string
     */
    private function checkEInvoice(string $pdf): string
    {
        if (!$this->config->entity instanceof Invoice) {
            return $pdf;
        }

        $e_invoice_type = $this->config->settings->e_invoice_type;

        switch ($e_invoice_type) {
            case "EN16931":
            case "XInvoice_2_2":
            case "XInvoice_2_1":
            case "XInvoice_2_0":
            case "XInvoice_1_0":
            case "XInvoice-Extended":
            case "XInvoice-BasicWL":
            case "XInvoice-Basic":
                return $this->embedEInvoiceZuGFerD($pdf) ?? $pdf;
                //case "Facturae_3.2":
                //case "Facturae_3.2.1":
                //case "Facturae_3.2.2":
                //
            default:
                return $pdf;
        }

    }

    /**
     * Embed the .xml file into the PDF
     *
     * @param  string $pdf
     * @return string
     */
    private function embedEInvoiceZuGFerD(string $pdf): string
    {
        try {

            $e_rechnung = (new CreateEDocument($this->config->entity, true))->handle();
            $pdfBuilder = new ZugferdDocumentPdfBuilder($e_rechnung, $pdf);
            $pdfBuilder->generateDocument();

            return $pdfBuilder->downloadString();

        } catch (\Throwable $th) {
            nlog("E_Invoice Merge failed - " . $th->getMessage());
        }

        return $pdf;
    }

}
