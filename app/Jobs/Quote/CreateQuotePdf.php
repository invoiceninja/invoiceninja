<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Quote;

use App\Designs\Custom;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Invoice;
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

class CreateQuotePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash;

    public $quote;

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

        $this->quote = $invitation->quote;

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        if(config('ninja.phantomjs_key'))
            return (new Phantom)->generate($this->invitation);

        $this->quote->load('client');

        App::setLocale($this->contact->preferredLocale());

        $path       = $this->quote->client->quote_filepath();

        $quote_design_id = $this->quote->design_id ? $this->quote->design_id : $this->decodePrimaryKey($this->quote->client->getSetting('quote_design_id'));

        $design     = Design::find($quote_design_id);

        $html = new HtmlEngine(null, $this->invitation, 'quote');

        $design_namespace = 'App\Services\PdfMaker\Designs\\' . $design->name;

        $design_class = new $design_namespace();

        $pdf_variables = json_decode(json_encode($this->quote->company->settings->pdf_variables), 1);

        $state = [
            'template' => $design_class->elements([
                'client' => $this->quote->client,
                'entity' => $this->quote,
                'product-table-columns' => $pdf_variables['product_columns'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $this->quote->client->getSetting('all_pages_header'),
                'all_pages_footer' => $this->quote->client->getSetting('all_pages_footer'),
            ],
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($design_namespace)
            ->build();

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory($path, 0775);
        
        $pdf       = $this->makePdf(null, null, $maker->getCompiledHTML());

        $file_path = $path . $this->quote->number . '.pdf';

        $instance = Storage::disk($this->disk)->put($file_path, $pdf);

        return $file_path;
    }
}
