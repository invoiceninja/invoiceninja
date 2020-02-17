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

namespace App\Services\Invoice;

use App\Jobs\Invoice\CreateInvoicePdf;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetInvoicePdf extends AbstractService
{

  	public function run($invoice, $contact = null)
  	{

    	if(!$contact)
			$contact = $invoice->client->primary_contact()->first();

		$path      = $invoice->client->invoice_filepath();

		$file_path = $path . $invoice->number . '.pdf';

		$disk 	   = config('filesystems.default');

    	$file 	   = Storage::disk($disk)->exists($file_path);

		if(!$file)
		{

			$file_path = CreateInvoicePdf::dispatchNow($invoice, $invoice->company, $contact);

		}

		//return $file_path;

		return Storage::disk($disk)->path($file_path);

  	}

}

