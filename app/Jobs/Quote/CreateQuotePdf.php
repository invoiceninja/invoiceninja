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

class CreateQuotePdf implements ShouldQueue {

	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml, PdfMaker;

	public $quote;

	public $company;

	public $contact;

	private $disk;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($quote, Company $company, ClientContact $contact = null) 
	{

		$this->quote = $quote;

		$this->company = $company;

		$this->contact = $contact;

        $this->disk = $disk ?? config('filesystems.default');

	}

	public function handle() {

		MultiDB::setDB($this->company->db);

		$this->quote->load('client');

		if(!$this->contact)
			$this->contact = $this->quote->client->primary_contact()->first();

		App::setLocale($this->contact->preferredLocale());

		$path      = $this->quote->client->quote_filepath();

		$file_path = $path . $this->quote->number . '.pdf';

		$design = Design::find($this->quote->client->getSetting('quote_design_id'));

		if($design->is_custom){
			$quote_design = new Custom($design->design);
		}
		else{
			$class = 'App\Designs\\'.$design->name;
			$quote_design = new $class();
		}

		$designer = new Designer($quote_design, $this->quote->client->getSetting('pdf_variables'), 'quote');

		//get invoice design
		$html = $this->generateInvoiceHtml($designer->build($this->quote)->getHtml(), $this->quote, $this->contact);

		//todo - move this to the client creation stage so we don't keep hitting this unnecessarily
		Storage::makeDirectory($path, 0755);

		//\Log::error($html);
		$pdf = $this->makePdf(null, null, $html);

		$instance = Storage::disk($this->disk)->put($file_path, $pdf);

		//$instance= Storage::disk($this->disk)->path($file_path);

		return $file_path;	
	}

	/**
	 * Returns a PDF stream
	 *
	 * @param  string $header Header to be included in PDF
	 * @param  string $footer Footer to be included in PDF
	 * @param  string $html   The HTML object to be converted into PDF
	 *
	 * @return string        The PDF string
	 */
	private function makePdf($header, $footer, $html) {
		return Browsershot::html($html)
		//->showBrowserHeaderAndFooter()
		//->headerHtml($header)
		//->footerHtml($footer)
			->deviceScaleFactor(1)
			->showBackground()
			->waitUntilNetworkIdle(true)	->pdf();
		//->margins(10,10,10,10)
		//->savePdf('test.pdf');
	}
}
