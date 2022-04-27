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

namespace App\Http\Controllers\Reports;

use App\Export\CSV\ClientExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Report\ClientReportRequest;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class ClientReportController extends BaseController
{
    use MakesHash;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/reports/clients",
     *      operationId="getClientReport",
     *      tags={"reports"},
     *      summary="Client reports",
     *      description="Export client reports",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="success",
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
    public function __invoke(ClientReportRequest $request)
    {
        // expect a list of visible fields, or use the default

        // return response()->json(['message' => 'Processing'], 200);

        // [
        //     'report_keys',
        //     'date_range',
        // ]
        
        $export = new ClientExport(auth()->user()->company(), $request->all());

        $csv = $export->run();
    }



}
