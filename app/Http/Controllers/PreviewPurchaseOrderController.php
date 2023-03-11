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

use App\DataMapper\Analytics\LivePreview;
use App\Factory\PurchaseOrderFactory;
use App\Http\Requests\Preview\PreviewPurchaseOrderRequest;
use App\Jobs\Util\PreviewPdf;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Repositories\PurchaseOrderRepository;
use App\Services\PdfMaker\Design;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\VendorHtmlEngine;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Turbo124\Beacon\Facades\LightLogs;

class PreviewPurchaseOrderController extends BaseController
{
    use MakesHash;
    use MakesInvoiceHtml;
    use PageNumbering;


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a template filled with entity variables.
     *
     * @return \Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/preview/purchase_order",
     *      operationId="getPreviewPurchaseOrder",
     *      tags={"preview"},
     *      summary="Returns a pdf preview for purchase order",
     *      description="Returns a pdf preview for purchase order.",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The pdf response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
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

            $entity_obj = PurchaseOrder::whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->company->locale());
            $t->replace(Ninja::transformTranslations($entity_obj->company->settings));

            $html = new VendorHtmlEngine($entity_obj->invitations()->first());

            $design_namespace = 'App\Services\PdfMaker\Designs\\'.request()->design['name'];

            $design_class = new $design_namespace();

            $state = [
                'template' => $design_class->elements([
                    'client' => null,
                    'vendor' => $entity_obj->vendor,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'variables' => $html->generateLabelsAndValues(),
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $entity_obj->company->markdown_enabled,
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
            
            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, auth()->user()->company());

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }

                return $pdf;
            }

            //else
            $file_path = (new PreviewPdf($maker->getCompiledHTML(true), auth()->user()->company()))->handle();

            return response()->download($file_path, basename($file_path), ['Cache-Control:' => 'no-cache'])->deleteFileAfterSend(true);
        }

        return $this->blankEntity();
    }

    public function live(PreviewPurchaseOrderRequest $request)
    {
        $company = auth()->user()->company();

        MultiDB::setDb($company->db);

        $repo = new PurchaseOrderRepository();
        $entity_obj = PurchaseOrderFactory::create($company->id, auth()->user()->id);
        $class = PurchaseOrder::class;

        try {
            DB::connection(config('database.default'))->beginTransaction();

            if ($request->has('entity_id')) {
                $entity_obj = $class::on(config('database.default'))
                                    ->with('vendor.company')
                                    ->where('id', $this->decodePrimaryKey($request->input('entity_id')))
                                    ->where('company_id', $company->id)
                                    ->withTrashed()
                                    ->first();
            }

            $entity_obj = $repo->save($request->all(), $entity_obj);

            if (!$request->has('entity_id')) {
                $entity_obj->service()->fillDefaults()->save();
            }
                
            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->company->locale());
            $t->replace(Ninja::transformTranslations($entity_obj->company->settings));

            $html = new VendorHtmlEngine($entity_obj->invitations()->first());

            $design = \App\Models\Design::find($entity_obj->design_id);

            /* Catch all in case migration doesn't pass back a valid design */
            if (!$design) {
                $design = \App\Models\Design::find(2);
            }

            if ($design->is_custom) {
                $options = [
                'custom_partials' => json_decode(json_encode($design->design), true)
              ];
                $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
            } else {
                $template = new PdfMakerDesign(strtolower($design->name));
            }

            $variables = $html->generateLabelsAndValues();

            $state = [
                'template' => $template->elements([
                    'client' => null,
                    'vendor' => $entity_obj->vendor,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'variables' => $html->generateLabelsAndValues(),
                    '$product' => $design->design->product,
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $entity_obj->company->markdown_enabled,
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($template)
                ->build();

            DB::connection(config('database.default'))->rollBack();

            if (request()->query('html') == 'true') {
                return $maker->getCompiledHTML();
            }
        } catch(\Exception $e) {
            DB::connection(config('database.default'))->rollBack();
            return;
        }


            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            }
            
            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, auth()->user()->company());

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }

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

        return $response;
    }

    private function blankEntity()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations(auth()->user()->company()->settings));

        $invitation = PurchaseOrderInvitation::where('company_id', auth()->user()->company()->id)->orderBy('id', 'desc')->first();

        /* If we don't have a valid invitation in the system - create a mock using transactions */
        if (!$invitation) {
            return $this->mockEntity();
        }

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new VendorHtmlEngine($invitation);

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => null,
                'vendor' => $invitation->purchase_order->vendor,
                'entity' => $invitation->purchase_order,
                'pdf_variables' => (array) $invitation->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $invitation->company->markdown_enabled,
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

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf =  (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, auth()->user()->company());

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }
            
        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), auth()->user()->company()))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    private function mockEntity()
    {
        DB::connection(auth()->user()->company()->db)->beginTransaction();

        $vendor = Vendor::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
            ]);

        $contact = VendorContact::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'vendor_id' => $vendor->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

        $purchase_order = PurchaseOrder::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                    'vendor_id' => $vendor->id,
                    'terms' => 'Sample Terms',
                    'footer' => 'Sample Footer',
                    'public_notes' => 'Sample Public Notes',
                ]);

        $invitation = PurchaseOrderInvitation::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                    'purchase_order_id' => $purchase_order->id,
                    'vendor_contact_id' => $contact->id,
        ]);

        $purchase_order->setRelation('invitations', $invitation);
        $purchase_order->setRelation('vendor', $vendor);
        $purchase_order->setRelation('company', auth()->user()->company());
        $purchase_order->load('vendor.company');

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new VendorHtmlEngine($purchase_order->invitations()->first());

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => null,
                'vendor' => $purchase_order->vendor,
                'entity' => $purchase_order,
                'pdf_variables' => (array) $purchase_order->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $purchase_order->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        DB::connection(auth()->user()->company()->db)->rollBack();

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, auth()->user()->company());

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }
            
        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), auth()->user()->company()))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }
}
