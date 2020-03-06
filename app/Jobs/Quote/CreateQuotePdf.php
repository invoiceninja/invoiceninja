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

		$settings = $this->quote->client->getMergedSettings();

		$this->quote->load('client');

		if(!$this->contact)
			$this->contact = $this->quote->client->primary_contact()->first();

		App::setLocale($this->contact->preferredLocale());

		$path      = $this->quote->client->quote_filepath();

		$design = Design::find($this->decodePrimaryKey($this->quote->client->getSetting('quote_design_id')));

		if($design->is_custom){
			$quote_design = new Custom($design->design);
		}
		else{
			$class = 'App\Designs\\'.$design->name;
			$quote_design = new $class();
		}

		$designer = new Designer($this->quote, $quote_design, $this->quote->client->getSetting('pdf_variables'), 'quote');

		//todo - move this to the client creation stage so we don't keep hitting this unnecessarily
		Storage::makeDirectory($path, 0755);

		//\Log::error($html);

		$all_pages_header = $settings->all_pages_header;
		$all_pages_footer = $settings->all_pages_footer;

		$quote_number = $this->quote->number;


		// if($all_pages_header && $all_pages_footer){
		// 	$all_pages_header = $designer->init()->getHeader()->getHtml();
		// 	$all_pages_footer = $designer->init()->getFooter()->getHtml();
		// 	$design_body = $designer->init()->getBody()->getHtml();
		// 	$quote_number = "header_and_footer";
		// }
		// elseif($all_pages_header){
		// 	$all_pages_header = $designer->init()->getHeader()->getHtml();
		// 	$design_body = $designer->init()->getBody()->getFooter()->getHtml();
		// 	$quote_number = "header_only";
		// }
		// elseif($all_pages_footer){
		// 	$all_pages_footer = $designer->init()->getFooter()->getHtml();
		// 	$design_body = $designer->init()->getHeader()->getBody()->getHtml();
		// 	$quote_number = "footer_only";
		// }
		// else{
			$design_body = $designer->build()->getHtml();
		

		

		//get invoice design
		$html = $this->generateInvoiceHtml($design_body, $this->quote, $this->contact);

		$pdf = $this->makePdf($all_pages_header, $all_pages_footer, $html);
		$file_path = $path . $quote_number . '.pdf';

		$instance = Storage::disk($this->disk)->put($file_path, $pdf);

		//$instance= Storage::disk($this->disk)->path($file_path);
		//
		return $file_path;	
	}

}
