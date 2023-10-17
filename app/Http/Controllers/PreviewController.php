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
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Factory\QuoteFactory;
use App\Jobs\Util\PreviewPdf;
use App\Models\ClientContact;
use App\Services\Pdf\PdfMock;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Repositories\QuoteRepository;
use Illuminate\Support\Facades\Cache;
use App\Repositories\CreditRepository;
use App\Utils\Traits\MakesInvoiceHtml;
use Turbo124\Beacon\Facades\LightLogs;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Factory\RecurringInvoiceFactory;
use Illuminate\Support\Facades\Response;
use App\DataMapper\Analytics\LivePreview;
use App\Repositories\RecurringInvoiceRepository;
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

    private function purgeCache()
    {   nlog(auth()->user()->id);
        Cache::pull("preview_".auth()->user()->id);
    }

    public function newLivePreview(PreviewInvoiceRequest $request)
    {   

        $time = time();
        nlog($time);

        $invitation = $request->resolveInvitation();

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($invitation->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($invitation->{$request->entity}->client->getMergedSettings()));

        $html = new HtmlEngine($invitation);
        $variables = $html->generateLabelsAndValues();

        $entity_obj = $invitation->{$request->entity};

        // if (! $invitation->{$request->entity}->id ?? true) {
        //     $invitation->{$request->entity}->service()->fillDefaults();
        // }

        $design = \App\Models\Design::withTrashed()->find($entity_obj->design_id ?? 2);

        /* Catch all in case migration doesn't pass back a valid design */
        if (! $design) {
            $design = \App\Models\Design::find(2);
        }

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
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
            ],
            'process_markdown' => $entity_obj->client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($template)
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

        nlog("merpy derp {$time}");

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'preview.pdf', ['Content-Type' => 'application/pdf','Cache-Control:' => 'no-cache']);

    }

    /**
     * Returns a template filled with entity variables.
     *
     * @return mixed
     */

    public function show()
    {
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

            $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

            return response()->download($file_path, basename($file_path), ['Cache-Control:' => 'no-cache'])->deleteFileAfterSend(true);
        }

        return $this->blankEntity();
    }

    public function design(DesignPreviewRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $pdf = (new PdfMock($request->all(), $company))->build()->getPdf();

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    public function live(PreviewInvoiceRequest $request)
    {

        // if(Cache::has("preview_".auth()->user()->id))
        //     return response()->json(['message' => 'Please wait a few seconds before trying again, this many requests are not good.'], 400);  

        nlog("should not see a lot of these");

        if (Ninja::isHosted() && !in_array($request->getHost(), ['preview.invoicing.co','staging.invoicing.co'])) {
            return response()->json(['message' => 'This server cannot handle this request.'], 400);
        }
     
        Cache::put("preview_".auth()->user()->id, 60);

        $start = microtime(true);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company = $user->company();

        MultiDB::setDb($company->db);

        if ($request->input('entity') == 'quote') {
            $repo = new QuoteRepository();
            $entity_obj = QuoteFactory::create($company->id, $user->id);
            $class = Quote::class;
        } elseif ($request->input('entity') == 'credit') {
            $repo = new CreditRepository();
            $entity_obj = CreditFactory::create($company->id, $user->id);
            $class = Credit::class;
        } elseif ($request->input('entity') == 'recurring_invoice') {
            $repo = new RecurringInvoiceRepository();
            $entity_obj = RecurringInvoiceFactory::create($company->id, $user->id);
            $class = RecurringInvoice::class;
        } else { //assume it is either an invoice or a null object
            $repo = new InvoiceRepository();
            $entity_obj = InvoiceFactory::create($company->id, $user->id);
            $class = Invoice::class;
        }

        try {
            DB::connection(config('database.default'))->beginTransaction();

            if ($request->has('entity_id')) {

                /** @var \App\Models\Quote | \App\Models\Invoice | \App\Models\RecurringInvoice | \App\Models\Credit $class */
                $temp_obj = $class::on(config('database.default'))
                                    ->with('client.company')
                                    ->where('id', $this->decodePrimaryKey($request->input('entity_id')))
                                    ->where('company_id', $company->id)
                                    ->withTrashed()
                                    ->first();
                                    
                /** Prevents null values from being passed into entity_obj */
                if($temp_obj)
                    $entity_obj = $temp_obj;
            }

            if ($request->has('footer') && !$request->filled('footer') && $request->input('entity') == 'recurring_invoice') {
                $request->merge(['footer' => $company->settings->invoice_footer]);
            }

            if ($request->has('terms') && !$request->filled('terms') && $request->input('entity') == 'recurring_invoice') {
                $request->merge(['terms' => $company->settings->invoice_terms]);
            }

            $entity_obj = $repo->save($request->all(), $entity_obj);

            if (! $request->has('entity_id')) {
                $entity_obj->service()->fillDefaults()->save();
            }

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->client->locale());
            $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

            $html = new HtmlEngine($entity_obj->invitations()->first());

            /** @var \App\Models\Design $design */
            $design = \App\Models\Design::withTrashed()->find($entity_obj->design_id);

            /* Catch all in case migration doesn't pass back a valid design */
            if (! $design) {
                $design = \App\Models\Design::find(2);
            }

            if ($design->is_custom) {
                $options = [
                    'custom_partials' => json_decode(json_encode($design->design), true),
                ];
                $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
            } else {
                $template = new PdfMakerDesign(strtolower($design->name));
            }

            $variables = $html->generateLabelsAndValues();

            $state = [
                'template' => $template->elements([
                    'client' => $entity_obj->client,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    '$product' => $design->design->product,
                    'variables' => $variables,
                ]),
                'variables' => $variables,
                'options' => [
                    'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                    'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
                ],
                'process_markdown' => $entity_obj->client->company->markdown_enabled,
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($template)
                ->build();

            DB::connection(config('database.default'))->rollBack();

            if (request()->query('html') == 'true') {
                $this->purgeCache();
                return $maker->getCompiledHTML();
            }

        } catch(\Exception $e) {
            
            $this->purgeCache();

            DB::connection(config('database.default'))->rollBack();

            if (DB::connection(config('database.default'))->transactionLevel() > 0) {
                DB::connection(config('database.default'))->rollBack();
            }

            return response()->json(['message' => 'Error generating preview. Please retry again shortly.'], 400);
        }

            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                $this->purgeCache();
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
                $this->purgeCache();
                return $pdf;
            }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

        if (Ninja::isHosted()) {
            LightLogs::create(new LivePreview())
                         ->increment()
                         ->batch();
        }

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');
        $response->header('Server-Timing', microtime(true)-$start);

        nlog("returning a response");
        $this->purgeCache();
        return $response;
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
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        DB::connection($company->db)->rollBack();

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
