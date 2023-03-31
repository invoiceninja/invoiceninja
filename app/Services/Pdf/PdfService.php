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

class PdfService
{
    use PdfMaker, PageNumbering;

    public InvoiceInvitation | QuoteInvitation | CreditInvitation | RecurringInvoiceInvitation | PurchaseOrderInvitation $invitation;

    public Company $company;

    public PdfConfiguration $config;

    public PdfBuilder $builder;

    public PdfDesigner $designer;

    public array $html_variables;

    public string $document_type;

    public array $options;

    const DELIVERY_NOTE = 'delivery_note';
    const STATEMENT = 'statement';
    const PURCHASE_ORDER = 'purchase_order';
    const PRODUCT = 'product';

    public function __construct($invitation, $document_type = 'product', $options = [])
    {
        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->document_type = $document_type;

        $this->options = $options;
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
            $pdf = $this->resolvePdfEngine($this->getHtml());

            $numbered_pdf = $this->pageNumbering($pdf, $this->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } catch (\Exception $e) {
            nlog(print_r($e->getMessage(), 1));
            throw new \Exception($e->getMessage(), $e->getCode());
        }

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
        $html = $this->builder->getCompiledHTML();

        if (config('ninja.log_pdf_html')) {
            info($html);
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
        $this->config = (new PdfConfiguration($this))->init();


        $this->html_variables = $this->config->client ?
                                    (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                                    (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        $this->designer = (new PdfDesigner($this))->build();

        $this->builder = (new PdfBuilder($this))->build();

        return $this;
    }

    /**
     * resolvePdfEngine
     *
     * @return mixed
     */
    public function resolvePdfEngine(string $html): mixed
    {
        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom)->convertHtmlToPdf($html);
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html);
        } else {
            $pdf = $this->makePdf(null, null, $html);
        }

        return $pdf;
    }
}
