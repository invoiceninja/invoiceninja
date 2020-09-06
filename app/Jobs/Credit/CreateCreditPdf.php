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

namespace App\Jobs\Credit;

use App\Designs\Custom;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Invoice;
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

class CreateCreditPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker, MakesHash;

    public $credit;

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

        $this->credit = $invitation->credit;

        $this->company = $invitation->company;

        $this->contact = $invitation->contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        if (config('ninja.phantomjs_key')) {
            return (new Phantom)->generate($this->invitation);
        }

        $this->credit->load('client');

        App::setLocale($this->contact->preferredLocale());

        $path = $this->credit->client->credit_filepath();

        $file_path = $path.$this->credit->number.'.pdf';

        $credit_design_id = $this->credit->design_id ? $this->credit->design_id : $this->decodePrimaryKey($this->credit->client->getSetting('credit_design_id'));

        $design = Design::find($credit_design_id);

        $designer = new Designer($this->credit, $design, $this->credit->client->getSetting('pdf_variables'), 'credit');

        $html = (new HtmlEngine($designer, $this->invitation, 'credit'))->build();

        Storage::makeDirectory($path, 0775);

        $pdf = $this->makePdf(null, null, $html);

        $instance = Storage::disk($this->disk)->put($file_path, $pdf);

        return $file_path;
    }
}
