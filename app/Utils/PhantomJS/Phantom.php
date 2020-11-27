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

use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\CurlUtils;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;

class Phantom
{
    use MakesHash;

    /**
     * Generate a PDF from the
     * Phantom JS API.
     *
     * @param $invitation
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
        info($url);

        $key = config('ninja.phantomjs_key');
        $secret = config('ninja.phantomjs_key');

        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/?request=%7Burl:%22{$url}%22,renderType:%22pdf%22%7D";
        $pdf = CurlUtils::get($phantom_url);

        //info($pdf);

        Storage::makeDirectory($path, 0775);

        $instance = Storage::disk(config('filesystems.default'))->put($file_path, $pdf);

        return $file_path;
    }

    public function convertHtmlToPdf($html)
    {
        
        $key = config('ninja.phantomjs_key');
        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/?request=%7Bcontent:%22{$html}%22,renderType:%22pdf%22%7D";
        $pdf = CurlUtils::get($phantom_url);
info($pdf);
        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    public function displayInvitation(string $entity, string $invitation_key)
    {
        $key = $entity.'_id';

        $invitation_instance = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation';
        $invitation = $invitation_instance::whereRaw('BINARY `key`= ?', [$invitation_key])->first();

        $entity_obj = $invitation->{$entity};

        $entity_obj->load('client');

        App::setLocale($invitation->contact->preferredLocale());

        $entity_design_id = $entity . '_design_id';
        $design_id = $entity_obj->design_id ? $entity_obj->design_id : $this->decodePrimaryKey($entity_obj->client->getSetting($entity_design_id));

        $design = Design::find($design_id);
        $html = new HtmlEngine($invitation);

        if ($design->is_custom) {
            $options = [
            'custom_partials' => json_decode(json_encode($design->design), true)
          ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
            ],
        ];

        $maker = new PdfMakerService($state);

        $data['html'] = $maker->design($template)
                              ->build()
                              ->getCompiledHTML(true);


        return view('pdf.html', $data);
    }
}
