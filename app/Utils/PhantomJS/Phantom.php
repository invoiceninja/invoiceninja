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
     * Phantom JS API.
     *
     * @return pdf HTML to PDF conversion
     */
    public function generate($invitation)
    {
        $entity = false;

        if ($invitation instanceof InvoiceInvitation) {
            $entity = 'invoice';
            $entity_design_id = 'invoice_design_id';
        } elseif ($invitation instanceof CreditInvitation) {
            $entity = 'credit';
            $entity_design_id = 'credit_design_id';
        } elseif ($invitation instanceof QuoteInvitation) {
            $entity = 'quote';
            $entity_design_id = 'quote_design_id';
        }

        $entity_obj = $invitation->{$entity};

        if ($entity == 'invoice') {
            $path = $entity_obj->client->invoice_filepath();
        }

        if ($entity == 'quote') {
            $path = $entity_obj->client->quote_filepath();
        }

        if ($entity == 'credit') {
            $path = $entity_obj->client->credit_filepath();
        }

        $file_path = $path.$entity_obj->number.'.pdf';

        $url = config('ninja.app_url').'phantom/'.$entity.'/'.$invitation->key.'?phantomjs_secret='.config('ninja.phantomjs_secret');

        $key = config('ninja.phantomjs_key');
        $secret = config('ninja.phantomjs_key');

        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/?request=%7Burl:%22{$url}%22,renderType:%22pdf%22%7D";
        $pdf = \App\Utils\CurlUtils::get($phantom_url);

        Storage::makeDirectory($path, 0775);

        $instance = Storage::disk(config('filesystems.default'))->put($file_path, $pdf);

        return $file_path;
    }

    public function displayInvitation(string $entity, string $invitation_key)
    {
        $key = $entity.'_id';

        $invitation_instance = 'App\Models\\'.ucfirst($entity).'Invitation';

        $invitation = $invitation_instance::whereRaw('BINARY `key`= ?', [$invitation_key])->first();
        

        $entity_obj = $invitation->{$entity};

        $entity_obj->load('client');

        App::setLocale($invitation->contact->preferredLocale());

        // $design_id = $entity_obj->design_id ? $entity_obj->design_id : $this->decodePrimaryKey($entity_obj->client->getSetting($entity_design_id));
        // $design = Design::find($design_id);
        // $designer = new Designer($entity_obj, $design, $entity_obj->client->getSetting('pdf_variables'), $entity);
        // $data['html'] = (new HtmlEngine($designer, $invitation, $entity))->build();

        $entity_design_id = $entity . '_design_id';
        $entity_design_id = $entity_obj->design_id ? $entity_obj->design_id : $this->decodePrimaryKey($entity_obj->client->getSetting($entity_design_id));
        $design = Design::find($entity_design_id);
        $data['html'] = new HtmlEngine(null, $invitation, $entity);

        return view('pdf.html', $data);
    }
}
