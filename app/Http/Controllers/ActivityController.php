<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Http\Requests\Activity\DownloadHistoricalInvoiceRequest;
use App\Models\Activity;
use App\Transformers\ActivityTransformer;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Http\Request;

class ActivityController extends BaseController
{
    use PdfMaker;

    protected $entity_type = Activity::class;

    protected $entity_transformer = ActivityTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/actvities",
     *      operationId="getActivities",
     *      tags={"actvities"},
     *      summary="Gets a list of actvities",
     *      description="Lists all activities",
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
     *          description="A list of actvities",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Activity"),
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
     *
     */
    public function index(Request $request)
    {
        $default_activities = $request->has('rows') ? $request->input('rows') : 50;

        $activities = Activity::orderBy('created_at', 'DESC')->company()
                                ->take($default_activities);

        return $this->listResponse($activities);
    }

    public function downloadHistoricalInvoice(DownloadHistoricalInvoiceRequest $request, Activity $activity)
    {
        $backup = $activity->backup;

        if(!$backup || !$backup->html_backup)
            return response()->json(['message'=> 'No backup exists for this activity', 'errors' => new \stdClass], 404);

        $pdf = $this->makePdf(null, null, $backup->html_backup);

        if(isset($activity->invoice_id))
            $filename = $activity->invoice->number . ".pdf";
        elseif(isset($activity->quote_id))
            $filename = $activity->quote->number . ".pdf";
        elseif(isset($activity->credit_id))
            $filename = $activity->credit->number . ".pdf";
        else
            $filename = "backup.pdf";

        return response()->streamDownload(function () use($pdf) {
            echo $pdf;
        }, $filename);
    }

}
