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
        $pdf = $request->client()->service()->statement(
            $request->only(['start_date', 'end_date', 'show_payments_table', 'show_aging_table', 'status'])
        );

        if ($pdf) {
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, ctrans('texts.statement').'.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->json(['message' => 'Something went wrong. Please check logs.']);
    }
}
