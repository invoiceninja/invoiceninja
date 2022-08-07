<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\PhantomJS;

use App\Exceptions\PhantomPDFFailure;
use App\Jobs\Util\SystemLogger;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\SystemLog;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\CurlUtils;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Phantom
{
    use MakesHash, PageNumbering;

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
        } elseif ($invitation instanceof RecurringInvoiceInvitation) {
            $entity = 'recurring_invoice';
            $entity_design_id = 'invoice_design_id';
        }

        $entity_obj = $invitation->{$entity};

        if ($entity == 'invoice') {
            $path = $entity_obj->client->invoice_filepath($invitation);
        }

        if ($entity == 'quote') {
            $path = $entity_obj->client->quote_filepath($invitation);
        }

        if ($entity == 'credit') {
            $path = $entity_obj->client->credit_filepath($invitation);
        }

        if ($entity == 'recurring_invoice') {
            $path = $entity_obj->client->recurring_invoice_filepath($invitation);
        }

        $file_path = $path.$entity_obj->numberFormatter().'.pdf';

        $url = config('ninja.app_url').'/phantom/'.$entity.'/'.$invitation->key.'?phantomjs_secret='.config('ninja.phantomjs_secret');
        info($url);

        $key = config('ninja.phantomjs_key');
        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/";
        $pdf = CurlUtils::post($phantom_url, json_encode([
            'url'            => $url,
            'renderType'     => 'pdf',
            'outputAsJson'   => false,
            'renderSettings' => [
                'emulateMedia' => 'print',
                'pdfOptions'   => [
                    'preferCSSPageSize' => true,
                    'printBackground'   => true,
                ],
            ],
        ]));

        $this->checkMime($pdf, $invitation, $entity);

        $numbered_pdf = $this->pageNumbering($pdf, $invitation->company);

        if ($numbered_pdf) {
            $pdf = $numbered_pdf;
        }

        if (! Storage::disk(config('filesystems.default'))->exists($path)) {
            Storage::disk(config('filesystems.default'))->makeDirectory($path, 0775);
        }

        $instance = Storage::disk(config('filesystems.default'))->put($file_path, $pdf);

        return $file_path;
    }

    public function convertHtmlToPdf($html)
    {
        $key = config('ninja.phantomjs_key');
        $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/";
        $pdf = CurlUtils::post($phantom_url, json_encode([
            'content'            => $html,
            'renderType'     => 'pdf',
            'outputAsJson'   => false,
            'renderSettings' => [
                'emulateMedia' => 'print',
                'pdfOptions'   => [
                    'preferCSSPageSize' => true,
                    'printBackground'   => true,
                ],
            ],
        ]));

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    /* Check if the returning PDF is valid. */
    private function checkMime($pdf, $invitation, $entity)
    {
        $finfo = new \finfo(FILEINFO_MIME);

        if ($finfo->buffer($pdf) != 'application/pdf; charset=binary') {
            SystemLogger::dispatch(
                $pdf,
                SystemLog::CATEGORY_PDF,
                SystemLog::EVENT_PDF_RESPONSE,
                SystemLog::TYPE_PDF_FAILURE,
                $invitation->contact->client,
                $invitation->company,
            );

            throw new PhantomPDFFailure('There was an error generating the PDF with Phantom JS');
        } else {
            SystemLogger::dispatch(
                'Entity PDF generated sucessfully => '.$invitation->{$entity}->number,
                SystemLog::CATEGORY_PDF,
                SystemLog::EVENT_PDF_RESPONSE,
                SystemLog::TYPE_PDF_SUCCESS,
                $invitation->contact->client,
                $invitation->company,
            );
        }
    }

    public function displayInvitation(string $entity, string $invitation_key)
    {
        $key = $entity.'_id';

        $invitation_instance = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation';
        $invitation = $invitation_instance::where('key', $invitation_key)->first();

        $entity_obj = $invitation->{$entity};

        $entity_obj->load('client');

        App::setLocale($invitation->contact->preferredLocale());

        $entity_design_id = $entity.'_design_id';

        if ($entity == 'recurring_invoice') {
            $entity_design_id = 'invoice_design_id';
        }

        $design_id = $entity_obj->design_id ? $entity_obj->design_id : $this->decodePrimaryKey($entity_obj->client->getSetting($entity_design_id));

        $design = Design::find($design_id);
        $html = new HtmlEngine($invitation);

        if ($design->is_custom) {
            $options = [
                'custom_partials' => json_decode(json_encode($design->design), true),
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
            'process_markdown' => $entity_obj->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $data['html'] = $maker->design($template)
                              ->build()
                              ->getCompiledHTML(true);

        if (config('ninja.log_pdf_html')) {
            info($data['html']);
        }

        return view('pdf.html', $data);
    }
}
