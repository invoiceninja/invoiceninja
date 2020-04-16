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
use App\Utils\HtmlEngine;
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
        $this->quote = $invitation->quote;

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        MultiDB::setDB($this->company->db);

        $this->quote->load('client');

        $settings = $this->quote->client->getMergedSettings();

        App::setLocale($this->contact->preferredLocale());

        $path       = $this->quote->client->quote_filepath();

        $design     = Design::find($this->decodePrimaryKey($this->quote->client->getSetting('quote_design_id')));

        $designer   = new Designer($this->quote, $design, $this->quote->client->getSetting('pdf_variables'), 'quote');

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory($path, 0755);

        $quote_number = $this->quote->number;
        
        $design_body = $designer->build()->getHtml();

        $invitation = $this->quote->invitations->first();
        $invitation->quote = $this->quote;
        $invitation->setRelation('quote', $this->quote);

        $start = microtime(true);

        $html = (new HtmlEngine($designer, $invitation, 'quote'))->build();

        \Log::error("generate HTML time = ".(microtime(true) - $start));

        $pdf       = $this->makePdf(null, null, $html);

        $file_path = $path . $quote_number . '.pdf';

        $instance = Storage::disk($this->disk)->put($file_path, $pdf);

        return $file_path;
    }
}
