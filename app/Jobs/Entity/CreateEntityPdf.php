<?php

/**
 * Entity Ninja (https://entityninja.com).
 *
 * @link https://github.com/entityninja/entityninja source repository
 *
 * @copyright Copyright (c) 2020. Entity Ninja LLC (https://entityninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Entity;

use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\NumberFormatter;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

class CreateEntityPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash;

    public $entity;

    public $company;

    public $contact;

    private $disk;

    public $invitation;

    public $entity_string = '';

    /**
     * Create a new job instance.
     *
     * @param $invitation
     */
    public function __construct($invitation)
    {
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

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {

        App::setLocale($this->contact->preferredLocale());
        App::forgetInstance('translator');
        Lang::replace(Ninja::transformTranslations($this->entity->client->getMergedSettings()));

        if (config('ninja.phantomjs_pdf_generation')) {
            return (new Phantom)->generate($this->invitation);
        }

        $entity_design_id = '';

        if ($this->entity instanceof Invoice) {
            $path = $this->entity->client->invoice_filepath();
            $entity_design_id = 'invoice_design_id';
        } elseif ($this->entity instanceof Quote) {
            $path = $this->entity->client->quote_filepath();
            $entity_design_id = 'quote_design_id';
        } elseif ($this->entity instanceof Credit) {
            $path = $this->entity->client->credit_filepath();
            $entity_design_id = 'credit_design_id';
        }


        $file_path = $path.$this->entity->number.'.pdf';

        $entity_design_id = $this->entity->design_id ? $this->entity->design_id : $this->decodePrimaryKey($this->entity->client->getSetting($entity_design_id));

        $design = Design::find($entity_design_id);
        $html = new HtmlEngine($this->invitation);

        if ($design->is_custom) {
            $options = [
            'custom_partials' => json_decode(json_encode($design->design), true)
          ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'client' => $this->entity->client,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $this->entity->client->getSetting('all_pages_header'),
                'all_pages_footer' => $this->entity->client->getSetting('all_pages_footer'),
            ],
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        //info("make dir => {$path}");
        //Storage::makeDirectory($path, 0775);

        $pdf = null;

        try {
            $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));
        } catch (\Exception $e) {
            info(print_r($e->getMessage(), 1));
        }

        if ($pdf) {
            $instance = Storage::disk($this->disk)->put($file_path, $pdf);
        }

        return $file_path;
    }

    public function failed(\Exception $exception)
    {
        info("help!");
    }
}
