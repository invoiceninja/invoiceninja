<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\Designs\Custom;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Invoice;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HtmlEngine;
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
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CreateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash;

    public $invoice;

    public $company;

    public $contact;

    private $disk;

    public $invitation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invitation)
    {
        $this->invitation = $invitation;

        $this->invoice = $invitation->invoice;

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {

        if (config('ninja.phantomjs_key')) {
            return (new Phantom)->generate($this->invitation);
        }

        App::setLocale($this->contact->preferredLocale());

        $path = $this->invoice->client->invoice_filepath();

        $file_path = $path.$this->invoice->number.'.pdf';

        $invoice_design_id = $this->invoice->design_id ? $this->invoice->design_id : $this->decodePrimaryKey($this->invoice->client->getSetting('invoice_design_id'));

        $design = Design::find($invoice_design_id);
        $html = new HtmlEngine(null, $this->invitation, 'invoice');

        $template = new PdfMakerDesign(strtolower($design->name));

        $state = [
            'template' => $template->elements([
                'client' => $this->invoice->client,
                'entity' => $this->invoice,
                'pdf_variables' => (array) $this->invoice->company->settings->pdf_variables,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $this->invoice->client->getSetting('all_pages_header'),
                'all_pages_footer' => $this->invoice->client->getSetting('all_pages_footer'),
            ],
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory($path, 0775);

        $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));

        $instance = Storage::disk($this->disk)->put($file_path, $pdf);

        return $file_path;
    }
}
