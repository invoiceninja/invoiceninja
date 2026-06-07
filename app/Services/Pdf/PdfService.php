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

use App\Models\Company;
use App\Models\CreditInvitation;
use App\Utils\Gotenberg\GotenbergPdf;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\EDocument\ZugferdPdfMerger;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\VendorHtmlEngine;

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
     * Initialize the PDF context for design preview rendering without building
     * the currently-saved design first. The caller will render the request
     * design after this method prepares config, settings, locale variables, and
     * the designer/builder shells.
     */
    public function bootForPreviewDesign(array $previewDesign): self
    {
        $this->start_time = microtime(true);

        $this->config = (new PdfConfiguration($this))->init();

        $this->applyJsonDesignSettingsOverrides($previewDesign);

        $htmlEngine = ($this->invitation instanceof \App\Models\PurchaseOrderInvitation)
                                    ? new VendorHtmlEngine($this->invitation)
                                    : new HtmlEngine($this->invitation);

        $htmlEngine->setSettings($this->config->settings);

        $this->html_variables = $htmlEngine->generateLabelsAndValues();

        $this->designer = new PdfDesigner($this);
        $this->designer->template = '';
        $this->builder = new PdfBuilder($this);
        $this->builder->document = new \DOMDocument();
        $this->builder->document->loadHTML('<!DOCTYPE html><html><body></body></html>');

        return $this;
    }

    /**
     * Resolves the PDF generation type and
     * attempts to generate a PDF from the HTML
     * string.
     *
     * @return mixed | \Exception
     *
     */
    public function getPdf()
    {
        try {

            $html = $this->getHtml();
            // nlog($html);
            $pdf = $this->resolvePdfEngine($html);

            $numbered_pdf = $this->pageNumbering($pdf, $this->company, $this->config->settings);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            if ($this->shouldMergeEInvoiceToPdf()) {

                try{
                    $pdf = $this->mergeEInvoiceToPdf($pdf);
                } catch (\Throwable $e) {
                    nlog("ERROR MERGING E-INVOICE TO PDF: " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            nlog($e->getMessage());
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $this->execution_time = microtime(true) - $this->start_time;

        return $pdf;
    }

    /**
     * Renders the dom document to HTML
     *
     * @return void
     *
     */
    public function setJsonDesignHtml(string $html): void
    {
        $this->json_design_html = $html;
    }

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

        // For JSON-designer documents, apply per-template documentSettings overrides
        // onto a cloned settings object before HtmlEngine reads them. This is how
        // show_paid_stamp / show_shipping_address / fonts / page size on the design
        // take precedence over the company defaults.
        $this->applyJsonDesignSettingsOverrides();

        $htmlEngine = ($this->invitation instanceof \App\Models\PurchaseOrderInvitation)
                                    ? new VendorHtmlEngine($this->invitation)
                                    : new HtmlEngine($this->invitation);

        // HtmlEngine pulls settings directly from the client/company on construct,
        // so propagate our resolved (possibly overridden) settings before it reads them.
        $htmlEngine->setSettings($this->config->settings);

        $this->html_variables = $htmlEngine->generateLabelsAndValues();

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
     * Build a DocumentSettingsResolver from the design and swap the merged
     * settings on PdfConfiguration with the resolved clone so all downstream
     * consumers (HtmlEngine, PdfBuilder, JsonToSectionsAdapter) see the
     * per-template overrides without any of them needing to know they exist.
     *
     * No-op for non-JSON designs and for JSON designs with no documentSettings.
     */
    private function applyJsonDesignSettingsOverrides(?array $designData = null): void
    {
        if ($designData === null) {
            if (!$this->isJsonDesign()) {
                return;
            }

            $designData = $this->config->decodedDesign();
        }

        if ($designData === null || !isset($designData['blocks'])) {
            return;
        }

        $resolver = new DocumentSettingsResolver($designData, $this->config->settings);

        if (!$resolver->hasOverrides()) {
            return;
        }

        $this->config->document_settings_resolver = $resolver;
        $this->config->settings = $resolver->resolve();
    }

    /**
     * Check if the current design is a JSON-based design
     *
     * @return bool
     */
    private function isJsonDesign(): bool
    {
        if (!isset($this->config->design) || !$this->config->design->is_custom) {
            return false;
        }

        $designData = $this->config->decodedDesign();

        return $designData !== null && isset($designData['blocks']);
    }

    /**
     * Build PDF using JSON Design Service
     *
     * @return void
     */
    private function buildWithJsonDesign(): void
    {
        $designData = $this->config->decodedDesign();

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
            $pdfa = $this->shouldCreatePdfA3VisualPdf() ? GotenbergPdf::PDF_A_3B : null;
            $pdf = (new GotenbergPdf())->convertHtmlToPdf($html, $pdfa);
        } else {
            $pdf = $this->makePdf(null, null, $html);
        }

        return $pdf;
    }

    private function shouldMergeEInvoiceToPdf(): bool
    {
        return empty($this->options['skip_e_invoice_pdf_merge'])
            && $this->shouldCreatePdfA3VisualPdf();

    }

    private function shouldCreatePdfA3VisualPdf(): bool
    {
        return $this->config->entity instanceof Invoice
            && ZugferdPdfMerger::shouldMerge($this->config->entity, $this->config->settings);

    }

    private function mergeEInvoiceToPdf(string $pdf): string
    {
        return (new ZugferdPdfMerger(
            $this->config->entity,
            $pdf,
            $this->config->settings->e_invoice_type ?? null
        ))->handle();
    }

}
