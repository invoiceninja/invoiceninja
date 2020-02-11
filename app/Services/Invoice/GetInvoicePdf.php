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
use Illuminate\Support\Facades\Storage;

class GetInvoicePdf
{

    public function __construct()
    {
    }

  	public function __invoke($invoice, $contact = null)
  	{

		$path      = 'public/' . $invoice->client->client_hash . '/invoices/';

		$file_path = $path . $invoice->number . '.pdf';

		$disk 	   = config('filesystems.default');

    	$file 	   = Storage::disk($disk)->exists($file_path);

    	if(!$contact)
			$contact = $invoice->client->primary_contact()->first();

		if(!$file)
		{
		
			$file_path = CreateInvoicePdf::dispatchNow($this, $this->invoice->company, $contact);

		}


		return Storage::disk($disk)->url($file_path);

  	}

}

