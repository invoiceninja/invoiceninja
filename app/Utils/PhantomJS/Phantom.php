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

namespace App\Utils\PhantomJS;

use App\Designs\Designer;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;


class Phantom
{
	use MakesHash;
	/**
	 * Generate a PDF from the 
	 * Phantom JS API
	 * 
	 * @return pdf HTML to PDF conversion
	 */
	public function generate($invitation)
	{
		$entity = false;

		if($invitation instanceof InvoiceInvitation)
			$entity = 'invoice';
		elseif($invitation instanceof CreditInvitation)
			$entity = 'credit';
		elseif($invitation instanceof QuoteInvitation)
			$entity = 'quote';

		$entity_obj = $invitation->{$entity};

		if($entity == 'invoice')
        	$path      = $entity_obj->client->invoice_filepath();

		if($entity == 'quote')
        	$path      = $entity_obj->client->quote_filepath();

		if($entity == 'credit')
        	$path      = $entity_obj->client->credit_filepath();

        $file_path = $path . $entity_obj->number . '.pdf';

		$url = rtrim(config('ninja.app_url'), "/") . 'phantom/' . $entity . '/' . $invitation . '?phantomjs_secret='. config('ninja.phantomjs_secret');

		$json_payload = new \stdClass;
		$json_payload->url = $url;
		$json_payload->renderType = "pdf";

		$url = "http://PhantomJScloud.com/api/browser/v2/" . config('ninja.phantomjs_key');
		$payload = json_encode($json_payload);
		$options = array(
		    'http' => array(
		        'header'  => "Content-type: application/json\r\n",
		        'method'  => 'POST',
		        'content' => $payload
		    )
		);
		$context  = stream_context_create($options);
		$pdf = file_get_contents($url, false, $context);
		if ($pdf === FALSE) { /* Handle error */ info("i did not make the PDF from phantom"); }

        Storage::makeDirectory($path, 0755);

        $instance = Storage::disk(config('filesystems.default'))->put($file_path, $pdf);

        return $file_path;


	}

	public function displayInvitation(string $entity, string $invitation_key)
	{
        $key = $entity.'_id';

        $invitation_instance = 'App\Models\\'.ucfirst($entity).'Invitation';

        $invitation = $invitation_instance::whereRaw("BINARY `key`= ?", [$invitation_key])->first();

        $entity_obj = $invitation->{$entity};

        $entity_obj->load('client');

        App::setLocale($invitation->contact->preferredLocale());

        $design_id = $entity_obj->design_id ? $entity_obj->design_id : $this->decodePrimaryKey($entity_obj->client->getSetting($entity . '_design_id'));

        $design = Design::find($design_id);
        
        $designer = new Designer($entity_obj, $design, $entity_obj->client->getSetting('pdf_variables'), $entity);

        $data['html'] = (new HtmlEngine($designer, $invitation, $entity))->build();

        return view('pdf.html', $data);
	}

}
