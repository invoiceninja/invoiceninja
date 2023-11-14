<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use Twig\Error\SyntaxError;
use App\Jobs\Util\PreviewPdf;
use App\Models\ClientContact;
use App\Services\Pdf\PdfMock;
use App\Utils\Traits\MakesHash;
use App\Services\Pdf\PdfService;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Utils\Traits\MakesInvoiceHtml;
use Turbo124\Beacon\Facades\LightLogs;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Response;
use App\DataMapper\Analytics\LivePreview;
use App\Services\Template\TemplateService;
use App\Http\Requests\Preview\DesignPreviewRequest;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Http\Requests\Preview\PreviewInvoiceRequest;

class PreviewController extends BaseController
{
    use MakesHash;
    use MakesInvoiceHtml;
    use PageNumbering;

    public function __construct()
    {
        parent::__construct();
    }
    
    public function live(PreviewInvoiceRequest $request): mixed
    {

        if (Ninja::isHosted() && !in_array($request->getHost(), ['preview.invoicing.co','staging.invoicing.co'])) {
            return response()->json(['message' => 'This server cannot handle this request.'], 400);
        }

        $start = microtime(true);

        /** Build models */
        $invitation = $request->resolveInvitation();
        $client = $request->getClient();
        $settings = $client->getMergedSettings();
        $entity_prop = str_replace("recurring_", "", $request->entity);
        $entity_obj = $invitation->{$request->entity};
        $entity_obj->fill($request->all());

        if(!$entity_obj->id) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{$entity_prop."_design_id"}));
            $entity_obj->footer = empty($entity_obj->footer) ? $settings->{$entity_prop."_footer"} : $entity_obj->footer;
            $entity_obj->terms = empty($entity_obj->terms) ? $settings->{$entity_prop."_terms"} : $entity_obj->terms;
            $entity_obj->public_notes = empty($entity_obj->public_notes) ? $request->getClient()->public_notes : $entity_obj->public_notes;
            $invitation->setRelation($request->entity, $entity_obj);
        }

        $ps = new PdfService($invitation, 'product', [
            'client' => $client ?? false,
            // 'vendor' => $vendor ?? false,
            "{$entity_prop}s" => [$entity_obj],
        ]);

        $pdf = $ps->boot()->getPdf();


        if (Ninja::isHosted()) {
            LightLogs::create(new LivePreview())
                        ->increment()
                        ->batch();
        }

        /** Return PDF */
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'preview.pdf', [
            'Content-Disposition' => 'inline',
            'Content-Type' => 'application/pdf',
            'Cache-Control:' => 'no-cache',
            'Server-Timing' => microtime(true)-$start
        ]);

    }

    /**
     * Refactor - 2023-10-19
     *
     * New method does not require Transactions.
     *
     * @param  PreviewInvoiceRequest $request
     * @return mixed
     */
    public function livexx(PreviewInvoiceRequest $request): mixed
    {

        if (Ninja::isHosted() && !in_array($request->getHost(), ['preview.invoicing.co','staging.invoicing.co'])) {
            return response()->json(['message' => 'This server cannot handle this request.'], 400);
        }

        $start = microtime(true);

        /** Build models */
        $invitation = $request->resolveInvitation();
        $client = $request->getClient();
        $settings = $client->getMergedSettings();

        /** Set translations */
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($invitation->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($settings));

        $entity_prop = str_replace("recurring_", "", $request->entity);
        $entity_obj = $invitation->{$request->entity};
        $entity_obj->fill($request->all());

        /** Update necessary objecty props */
        if(!$entity_obj->id) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{$entity_prop."_design_id"}));
            $entity_obj->footer = empty($entity_obj->footer) ? $settings->{$entity_prop."_footer"} : $entity_obj->footer;
            $entity_obj->terms = empty($entity_obj->terms) ? $settings->{$entity_prop."_terms"} : $entity_obj->terms;
            $entity_obj->public_notes = empty($entity_obj->public_notes) ? $request->getClient()->public_notes : $entity_obj->public_notes;
            $invitation->{$request->entity} = $entity_obj;
        }

        if(empty($entity_obj->design_id)) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{$entity_prop."_design_id"}));
        }

        /** Generate variables */
        $html = new HtmlEngine($invitation);
        $html->settings = $settings;
        $variables = $html->generateLabelsAndValues();

        $design = \App\Models\Design::query()->withTrashed()->find($entity_obj->design_id ?? 2);

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
                'client' => $client,
                'entity' => $entity_obj,
                'pdf_variables' => (array) $settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $client->getSetting('all_pages_header'),
                'all_pages_footer' => $client->getSetting('all_pages_footer'),
                'client' => $entity_obj->client ?? [],
                'vendor' => $entity_obj->vendor ?? [],
                $request->input('entity')."s" => [$entity_obj],
            ],
            'process_markdown' => $client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($template)
            ->build();

        /** Generate HTML */
        $html = $maker->getCompiledHTML(true);

        if (request()->query('html') == 'true') {
            return $html;
        }

        //if phantom js...... inject here..
        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($html);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {

            $pdf = (new NinjaPdf())->build($html);
            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $pdf = (new PreviewPdf($html, $company))->handle();

        if (Ninja::isHosted()) {
            LightLogs::create(new LivePreview())
                        ->increment()
                        ->batch();
        }

        /** Return PDF */
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'preview.pdf', [
            'Content-Disposition' => 'inline',
            'Content-Type' => 'application/pdf',
            'Cache-Control:' => 'no-cache',
            'Server-Timing' => microtime(true)-$start
        ]);

    }

    
    /**
     * Returns the mocked PDF for the invoice design preview.
     *
     * Only used in Settings > Invoice Design as a general overview
     *
     * @param  DesignPreviewRequest $request
     * @return mixed
     */
    public function design(DesignPreviewRequest $request): mixed
    {
        $start = microtime(true);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $pdf = (new PdfMock($request->all(), $company))->build()->getPdf();

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');
        $response->header('Server-Timing', microtime(true)-$start);
        
        return $response;
    }

    /**
     * Returns a template filled with entity variables.
     *
     * Used in the Custom Designer to preview design changes
     * @return mixed
     */
    public function show()
    {
        if(request()->has('template')) {
            return $this->template();
        }

        if (request()->has('entity') &&
            request()->has('entity_id') &&
            ! empty(request()->input('entity')) &&
            ! empty(request()->input('entity_id')) &&
            request()->has('body')) {

            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => ctrans('texts.invalid_design_object')], 400);
            }

            $entity = ucfirst(request()->input('entity'));

            $class = "App\Models\\$entity";

            $entity_obj = $class::whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            $entity_obj->load('client');

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->client->primary_contact()->preferredLocale());
            $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

            $html = new HtmlEngine($entity_obj->invitations()->first());

            $design_namespace = 'App\Services\PdfMaker\Designs\\'.request()->design['name'];

            $design_class = new $design_namespace();

            $state = [
                'template' => $design_class->elements([
                    'client' => $entity_obj->client,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'products' => request()->design['design']['product'],
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $entity_obj->client->company->markdown_enabled,
                'options' => [
                    'client' => $entity_obj->client ?? [],
                    'vendor' => $entity_obj->vendor ?? [],
                    request()->input('entity_type', 'invoice')."s" => [$entity_obj],
                ]
            ];

            $design = new Design(request()->design['name']);
            $maker = new PdfMaker($state);

            $maker
                ->design($design)
                ->build();
            
            if (request()->query('html') == 'true') {
                return $maker->getCompiledHTML();
            }

            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();
            $company = $user->company();

            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {

                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
                $numbered_pdf = $this->pageNumbering($pdf, $company);
                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }

                return $pdf;

            }

            $pdf = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, 'preview.pdf', [
                'Content-Disposition' => 'inline',
                'Content-Type' => 'application/pdf',
                'Cache-Control:' => 'no-cache',
            ]);


        }

        return $this->blankEntity();
    }

    private function template()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $design_object = json_decode(json_encode(request()->input('design')), 1);

        $ts = (new TemplateService());

        try {
            $ts->setCompany($company)
                ->setTemplate($design_object)
                ->mock();
        } catch(SyntaxError $e) {

            // return response()->json(['message' => 'Twig syntax is invalid.', 'errors' => new \stdClass], 422);
        }

        $html = $ts->getHtml();

        if (request()->query('html') == 'true') {
            return $html;
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($html);
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html);

            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($html, $company))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;

    }

    private function stubTemplateData()
    {
        
    }

    private function blankEntity()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($company->settings));

        /** @var \App\Models\InvoiceInvitation $invitation */
        $invitation = InvoiceInvitation::where('company_id', $company->id)->orderBy('id', 'desc')->first();

        /* If we don't have a valid invitation in the system - create a mock using transactions */
        if (! $invitation) {
            return $this->mockEntity();
        }

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new HtmlEngine($invitation);

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => $invitation->invoice->client,
                'entity' => $invitation->invoice,
                'pdf_variables' => (array) $invitation->invoice->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $invitation->invoice->client->company->markdown_enabled,
            'options' => [
                'client' => $invitation->invoice->client,
                'invoices' => [$invitation->invoice],
            ]
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    private function mockEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        try {
            DB::connection($company->db)->beginTransaction();

            /** @var \App\Models\Client $client */
            $client = Client::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => $company->id,
            ]);

            /** @var \App\Models\ClientContact $contact */
            $contact = ClientContact::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => $company->id,
                'client_id' => $client->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

            /** @var \App\Models\Invoice $invoice */

            $invoice = Invoice::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => $company->id,
                'client_id' => $client->id,
                'terms' => $company->settings->invoice_terms,
                'footer' => $company->settings->invoice_footer,
                'public_notes' => 'Sample Public Notes',
            ]);

            $invitation = InvoiceInvitation::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'client_contact_id' => $contact->id,
            ]);

            $invoice->setRelation('invitations', $invitation);
            $invoice->setRelation('client', $client);
            $invoice->setRelation('company', $company);
            $invoice->load('client.company');

            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => 'Invalid custom design object'], 400);
            }

            $html = new HtmlEngine($invoice->invitations()->first());

            $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

            $state = [
                'template' => $design->elements([
                    'client' => $invoice->client,
                    'entity' => $invoice,
                    'pdf_variables' => (array) $invoice->company->settings->pdf_variables,
                    'products' => request()->design['design']['product'],
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $invoice->client->company->markdown_enabled,
                'options' => [
                    'client' => $invoice->client,
                    'invoices' => [$invoice],
                ]
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($design)
                ->build();

            DB::connection($company->db)->rollBack();
        } catch(\Exception $e) {
            DB::connection($company->db)->rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');
        return $response;
    }
}
