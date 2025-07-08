<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Vendor;
use App\Libraries\MultiDB;
use App\Jobs\Util\PreviewPdf;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\Pdf\PdfMock;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Services\Pdf\PdfService;
use App\Utils\PhantomJS\Phantom;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Factory\PurchaseOrderFactory;
use App\Utils\Traits\MakesInvoiceHtml;
use Turbo124\Beacon\Facades\LightLogs;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use App\DataMapper\Analytics\LivePreview;
use App\Repositories\PurchaseOrderRepository;
use App\Http\Requests\Preview\ShowPreviewRequest;
use App\Http\Requests\Preview\PreviewPurchaseOrderRequest;

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
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response | \Symfony\Component\HttpFoundation\BinaryFileResponse | \Illuminate\Http\RedirectResponse | \Illuminate\Routing\Redirector | string
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
    public function show(ShowPreviewRequest $request)
    {
        if ($request->input('entity', false) &&
            $request->input('entity_id', false) != '-1' &&
            $request->has('body')) {

            $design_object = json_decode(json_encode($request->input('design')), true);

            if (! is_array($design_object)) {
                return response()->json(['message' => ctrans('texts.invalid_design_object')], 400);
            }

            $entity_obj = PurchaseOrder::query()->whereId($this->decodePrimaryKey($request->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->company->locale());
            $t->replace(Ninja::transformTranslations($entity_obj->company->settings));

            $invitation = $entity_obj->invitations()->first();

            $ps = new PdfService($invitation, 'product', [
                'client' => $entity_obj->client ?? false,
                'vendor' => $entity_obj->vendor ?? false,
                $request->input('entity')."s" => [$entity_obj],
            ]);

            $ps->boot()
            ->designer
            ->buildFromPartials($request->design['design']);

            $ps->builder
            ->build();

            if ($request->query('html') == 'true') {
                return $ps->getHtml();
            }

            $pdf = $ps->getPdf();

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

    public function live(PreviewPurchaseOrderRequest $request)
    {

        $start = microtime(true);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $invitation = $request->resolveInvitation();
        $vendor = $request->getVendor();
        $settings = $user->company()->settings;
        $entity_obj = $invitation->purchase_order;
        $entity_obj->fill($request->all());

        if (!$entity_obj->id) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{"purchase_order_design_id"}));
            $entity_obj->footer = empty($entity_obj->footer) ? $settings->{"purchase_order_footer"} : $entity_obj->footer;
            $entity_obj->terms = empty($entity_obj->terms) ? $settings->{"purchase_order_terms"} : $entity_obj->terms;
            $entity_obj->public_notes = empty($entity_obj->public_notes) ? $request->getVendor()->public_notes : $entity_obj->public_notes;
            $invitation->setRelation($request->entity, $entity_obj);

        }

        $ps = new PdfService($invitation, 'purchase_order', [
            'client' => $entity_obj->client ?? false,
            'vendor' => $vendor ?? false,
            "purchase_orders" => [$entity_obj],
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
            'Server-Timing' => (string)(microtime(true) - $start)
        ]);


    }

    private function blankEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($user->company()->settings));

        /** @var \App\Models\PurchaseOrderInvitation $invitation */
        $invitation = PurchaseOrderInvitation::where('company_id', $user->company()->id)->orderBy('id', 'desc')->first();

        /* If we don't have a valid invitation in the system - create a mock using transactions */
        if (!$invitation) {
            return $this->mockEntity();
        }

        $design_object = json_decode(json_encode(request()->input('design')), true);

        if (! is_array($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $ps = new PdfService($invitation, 'product', [
            'client' => $invitation->client ?? false,
            'vendor' => $invitation->vendor ?? false,
            "purchase_orders" => [$invitation->purchase_order],
        ]);

        $ps->boot()
        ->designer
        ->buildFromPartials($design_object['design']);

        $ps->builder
        ->build();


        if (request()->query('html') == 'true') {
            return $ps->getHtml();
        }

        $pdf = $ps->getPdf();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'preview.pdf', [
            'Content-Disposition' => 'inline',
            'Content-Type' => 'application/pdf',
            'Cache-Control:' => 'no-cache',
        ]);

    }

    private function mockEntity()
    {

        nlog("mockEntity");

        $start = microtime(true);
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $request = request()->input('design');
        $request['entity_type'] = request()->input('entity', 'invoice');

        $pdf = (new PdfMock($request, $company))->build();

        if (request()->query('html') == 'true') {
            return $pdf->getHtml();
        }

        $pdf = $pdf->getPdf();

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');
        $response->header('Server-Timing', (string) (microtime(true) - $start));

        return $response;

    }
}
