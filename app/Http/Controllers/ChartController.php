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

use App\Http\Requests\Chart\ShowChartRequest;
use App\Services\Chart\ChartService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChartController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/charts/totals",
     *      operationId="getChartTotals",
     *      tags={"charts"},
     *      summary="Get chart data",
     *      description="Get chart data",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Parameter(
     *          name="rows",
     *          in="query",
     *          description="The number of activities to return",
     *          example="50",
     *          required=false,
     *          @OA\Schema(
     *              type="number",
     *              format="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="json dataset of chart data",
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
     * @param Request $request
     * @return Response|mixed
     */
    public function totals(ShowChartRequest $request)
    {
        $cs = new ChartService(auth()->user()->company());

        return response()->json($cs->totals($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function chart_summary(ShowChartRequest $request)
    {
        $cs = new ChartService(auth()->user()->company());

        return response()->json($cs->chart_summary($request->input('start_date'), $request->input('end_date')), 200);
    }
}
