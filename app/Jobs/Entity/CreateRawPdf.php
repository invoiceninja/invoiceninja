<?php
/**
 * Entity Ninja (https://entityninja.com).
 *
 * @link https://github.com/entityninja/entityninja source repository
 *
 * @copyright Copyright (c) 2022. Entity Ninja LLC (https://entityninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Entity;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use App\Models\CreditInvitation;
use App\Models\RecurringInvoice;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Jobs\Invoice\CreateEInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Queue\SerializesModels;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Queue\InteractsWithQueue;
use App\Exceptions\FilePermissionsFailure;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;

class CreateRawPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash, PageNumbering;

    public Invoice | Credit | Quote | RecurringInvoice $entity;

    public $company;

    public $contact;

    public $invitation;

    public $entity_string = '';

    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation, $db)
    {
        MultiDB::setDb($db);

        $this->invitation = $invitation;

        if ($invitation instanceof InvoiceInvitation) {
            $this->entity = $invitation->invoice;
            $this->entity_string = 'invoice';
        } elseif ($invitation instanceof QuoteInvitation) {
            $this->entity = $invitation->quote;
            $this->entity_string = 'quote';
        } elseif ($invitation instanceof CreditInvitation) {
            $this->entity = $invitation->credit;
            $this->entity_string = 'credit';
        } elseif ($invitation instanceof RecurringInvoiceInvitation) {
            $this->entity = $invitation->recurring_invoice;
            $this->entity_string = 'recurring_invoice';
        }

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;
    }

    public function handle()
    {
        /* Forget the singleton*/
        App::forgetInstance('translator');

        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->contact->preferredLocale());

        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->entity->client->getMergedSettings()));

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->generate($this->invitation, true);
        }

        $entity_design_id = '';
        $path = '';

        if ($this->entity instanceof Invoice) {
            $path = $this->entity->client->invoice_filepath($this->invitation);
            $entity_design_id = 'invoice_design_id';
        } elseif ($this->entity instanceof Quote) {
            $path = $this->entity->client->quote_filepath($this->invitation);
            $entity_design_id = 'quote_design_id';
        } elseif ($this->entity instanceof Credit) {
            $path = $this->entity->client->credit_filepath($this->invitation);
            $entity_design_id = 'credit_design_id';
        } elseif ($this->entity instanceof RecurringInvoice) {
            $path = $this->entity->client->recurring_invoice_filepath($this->invitation);
            $entity_design_id = 'invoice_design_id';
        }

        $file_path = $path.$this->entity->numberFormatter().'.pdf';

        $entity_design_id = $this->entity->design_id ? $this->entity->design_id : $this->decodePrimaryKey($this->entity->client->getSetting($entity_design_id));

        $design = Design::query()->withTrashed()->find($entity_design_id);

        /* Catch all in case migration doesn't pass back a valid design */
        if (! $design) {
            $design = Design::query()->find(2);
        }

        $html = new HtmlEngine($this->invitation);

        if ($design->is_custom) {
            $options = [
                'custom_partials' => json_decode(json_encode($design->design), true),
            ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $this->entity->client,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $this->entity->client->getSetting('all_pages_header'),
                'all_pages_footer' => $this->entity->client->getSetting('all_pages_footer'),
            ],
            'process_markdown' => $this->entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        $pdf = null;

        try {
            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

                $finfo = new \finfo(FILEINFO_MIME);

                //fallback in case hosted PDF fails.
                if ($finfo->buffer($pdf) != 'application/pdf; charset=binary') {
                    $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));

                    $numbered_pdf = $this->pageNumbering($pdf, $this->company);

                    if ($numbered_pdf) {
                        $pdf = $numbered_pdf;
                    }
                }
            } else {
                $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, $this->company);

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }
            }
        } catch (\Exception $e) {
            nlog(print_r($e->getMessage(), 1));
        }

        if (config('ninja.log_pdf_html')) {
            info($maker->getCompiledHTML());
        }

        if ($pdf) {
            $maker =null;
            $state = null;
            
            return $this->checkEInvoice($pdf);
        }

        throw new FilePermissionsFailure('Unable to generate the raw PDF');
    }
    
    /**
     * Switch to determine if we need to embed the xml into the PDF itself
     *
     * @param  string $pdf
     * @return string
     */
    private function checkEInvoice(string $pdf): string
    {
        if(!$this->entity instanceof Invoice)
            return $pdf;

        $e_invoice_type = $this->entity->client->getSetting('e_invoice_type');

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

            $e_rechnung = (new CreateEInvoice($this->entity, true))->handle();
            $pdfBuilder = new ZugferdDocumentPdfBuilder($e_rechnung, $pdf);
            $pdfBuilder->generateDocument();
            return $pdfBuilder->downloadString(basename($this->entity->getFileName()));

        } catch (\Exception $e) {
            nlog("E_Invoice Merge failed - " . $e->getMessage());
        }

        return $pdf;
    }

    public function failed($e)
    {
    }
}
