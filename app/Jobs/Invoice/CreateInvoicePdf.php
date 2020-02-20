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

namespace App\Jobs\Invoice;

use App\Designs\Custom;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Invoice;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CreateInvoicePdf implements ShouldQueue {

	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker;

	public $invoice;

	public $company;

	public $contact;

	private $disk;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($invoice, Company $company, ClientContact $contact = null) 
	{

		$this->invoice = $invoice;

		$this->company = $company;

		$this->contact = $contact;

        $this->disk = $disk ?? config('filesystems.default');

	}

	public function handle() {

		MultiDB::setDB($this->company->db);

		$this->invoice->load('client');

		if(!$this->contact)
			$this->contact = $this->invoice->client->primary_contact()->first();

		App::setLocale($this->contact->preferredLocale());

		$path      = $this->invoice->client->invoice_filepath();

		$file_path = $path . $this->invoice->number . '.pdf';

		$design = Design::find($this->invoice->client->getSetting('invoice_design_id'));

		if($design->is_custom){
			$invoice_design = new Custom($design->design);
		}
		else{
			$class = 'App\Designs\\'.$design->name;
			$invoice_design = new $class();
		}

		$designer = new Designer($invoice_design, $this->invoice->client->getSetting('pdf_variables'), 'invoice');

		//get invoice design
		$html = $this->generateInvoiceHtml($designer->build($this->invoice)->getHtml(), $this->invoice, $this->contact);

		//todo - move this to the client creation stage so we don't keep hitting this unnecessarily
		Storage::makeDirectory($path, 0755);

		//\Log::error($html);
		$pdf = $this->makePdf(null, null, $html);

		$instance = Storage::disk($this->disk)->put($file_path, $pdf);

		//$instance= Storage::disk($this->disk)->path($file_path);
		//
		return $file_path;	
	}


}
