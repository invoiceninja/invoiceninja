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

namespace App\Jobs\Credit;

use App\Designs\Custom;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Invoice;
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($credit, Company $company, ClientContact $contact = null)
    {
        $this->credit = $credit;

        $this->company = $company;

        $this->contact = $contact;

        $this->disk = $disk ?? config('filesystems.default');
    }

    public function handle()
    {
        MultiDB::setDB($this->company->db);

        $this->credit->load('client');

        if (!$this->contact) {
            $this->contact = $this->credit->client->primary_contact()->first();
        }

        App::setLocale($this->contact->preferredLocale());

        $path      = $this->credit->client->credit_filepath();

        $file_path = $path . $this->credit->number . '.pdf';

        $design = Design::find($this->decodePrimaryKey($this->credit->client->getSetting('credit_design_id')));
        
        $designer = new Designer($this->credit, $design, $this->credit->client->getSetting('pdf_variables'), 'credit');

        //get invoice design
        //		$html = $this->generateInvoiceHtml($designer->build()->getHtml(), $this->credit, $this->contact);
        $html = $this->generateEntityHtml($designer, $this->credit, $this->contact);

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory($path, 0755);

        $pdf       = $this->makePdf(null, null, $html);

        $instance = Storage::disk($this->disk)->put($file_path, $pdf);

        return $file_path;
    }
}
