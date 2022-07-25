<?php

/**
 * Invoice Ninja (https://entityninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Entity;

use App\Exceptions\FilePermissionsFailure;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\NumberFormatter;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PDF;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\PdfParser\StreamReader;

class CreateEntityPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash, PageNumbering;

    public $entity;

    public $company;

    public $contact;

    private $disk;

    public $invitation;

    public $entity_string = '';

    public $client;

    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation, $disk = null)
    {
        $this->invitation = $invitation;

        if ($invitation instanceof InvoiceInvitation) {
            // $invitation->load('contact.client.company','invoice.client','invoice.user.account');
            $this->entity = $invitation->invoice;
            $this->entity_string = 'invoice';
        } elseif ($invitation instanceof QuoteInvitation) {
            // $invitation->load('contact.client.company','quote.client','quote.user.account');
            $this->entity = $invitation->quote;
            $this->entity_string = 'quote';
        } elseif ($invitation instanceof CreditInvitation) {
            // $invitation->load('contact.client.company','credit.client','credit.user.account');
            $this->entity = $invitation->credit;
            $this->entity_string = 'credit';
        } elseif ($invitation instanceof RecurringInvoiceInvitation) {
            // $invitation->load('contact.client.company','recurring_invoice');
            $this->entity = $invitation->recurring_invoice;
            $this->entity_string = 'recurring_invoice';
        }

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;

        $this->client = $invitation->contact->client;
        $this->client->load('company');

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        /* Forget the singleton*/
        App::forgetInstance('translator');

        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->client->locale());

        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->generate($this->invitation);
        }

        $entity_design_id = '';

        if ($this->entity instanceof Invoice) {
            $path = $this->client->invoice_filepath($this->invitation);
            $entity_design_id = 'invoice_design_id';
        } elseif ($this->entity instanceof Quote) {
            $path = $this->client->quote_filepath($this->invitation);
            $entity_design_id = 'quote_design_id';
        } elseif ($this->entity instanceof Credit) {
            $path = $this->client->credit_filepath($this->invitation);
            $entity_design_id = 'credit_design_id';
        } elseif ($this->entity instanceof RecurringInvoice) {
            $path = $this->client->recurring_invoice_filepath($this->invitation);
            $entity_design_id = 'invoice_design_id';
        }

        $file_path = $path.$this->entity->numberFormatter().'.pdf';

        $entity_design_id = $this->entity->design_id ? $this->entity->design_id : $this->decodePrimaryKey($this->client->getSetting($entity_design_id));

        $design = Design::find($entity_design_id);

        /* Catch all in case migration doesn't pass back a valid design */
        if (! $design) {
            $design = Design::find(2);
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
                'client' => $this->client,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->company->settings->pdf_variables,
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

                $numbered_pdf = $this->pageNumbering($pdf, $this->company);

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
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
            try {
                if (! Storage::disk($this->disk)->exists($path)) {
                    Storage::disk($this->disk)->makeDirectory($path, 0775);
                }

                Storage::disk($this->disk)->put($file_path, $pdf, 'public');
            } catch (\Exception $e) {
                throw new FilePermissionsFailure($e->getMessage());
            }
        }

        return $file_path;
    }

    public function failed($e)
    {
    }
}
