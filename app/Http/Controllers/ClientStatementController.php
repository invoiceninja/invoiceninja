<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Statements\CreateStatementRequest;
use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;

class ClientStatementController extends BaseController
{
    use MakesHash, PdfMaker;

    /** @var \App\Models\Invoice|\App\Models\Payment */
    protected $entity;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CreateStatementRequest $request
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/client_statement",
     *      operationId="clientStatement",
     *      tags={"clients"},
     *      summary="Return a PDF of the client statement",
     *      description="Return a PDF of the client statement",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="Statment Options",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="start_date",
     *                     description="The start date of the statement period - format Y-m-d",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="end_date",
     *                     description="The start date of the statement period - format Y-m-d",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="client_id",
     *                     description="The hashed ID of the client",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="show_payments_table",
     *                     description="Flag which determines if the payments table is shown",
     *                     type="boolean",
     *                 ),     
     *                 @OA\Property(
     *                     property="show_aging_table",
     *                     description="Flag which determines if the aging table is shown",
     *                     type="boolean",
     *                 )
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Client"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */

    public function statement(CreateStatementRequest $request)
    {
        $pdf = $this->createStatement($request);

        if ($pdf) {
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, 'statement.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->json(['message' => 'Something went wrong. Please check logs.']);
    }

    protected function createStatement(CreateStatementRequest $request): ?string
    {
        $invitation = false;

        if ($request->getInvoices()->count() >= 1) {
            $this->entity = $request->getInvoices()->first();
            $invitation = $this->entity->invitations->first();
        }
        else if ($request->getPayments()->count() >= 1) {
            $this->entity = $request->getPayments()->first()->invoices->first()->invitations->first();
            $invitation = $this->entity->invitations->first();
        }

        $entity_design_id = 1;

        $entity_design_id = $this->entity->design_id
            ? $this->entity->design_id
            : $this->decodePrimaryKey($this->entity->client->getSetting('invoice_design_id'));


        $design = Design::find($entity_design_id);

        if (!$design) {
            $design = Design::find($entity_design_id);
        }

        $html = new HtmlEngine($invitation);

        $options = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'show_payments_table' => $request->show_payments_table,
            'show_aging_table' => $request->show_aging_table,
        ];

        if ($design->is_custom) {
            $options['custom_partials'] = \json_decode(\json_encode($design->design), true);
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name), $options);
        }

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $this->entity->client,
                'entity' => $this->entity,
                'pdf_variables' => (array)$this->entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
                'invoices' => $request->getInvoices(),
                'payments' => $request->getPayments(),
                'aging' => $request->getAging(),
            ], \App\Services\PdfMaker\Design::STATEMENT),
            'variables' => $variables,
            'options' => [],
            'process_markdown' => $this->entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $maker
            ->design($template)
            ->build();

        $pdf = null;

        try {
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                $pdf = (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            }
            else if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
            } else {
                $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));
            }
        } catch (\Exception $e) {
            nlog(print_r($e->getMessage(), 1));
        }

        return $pdf;
    }
}
