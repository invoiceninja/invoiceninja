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

use App\Http\Requests\Activity\DownloadHistoricalEntityRequest;
use App\Models\Activity;
use App\Transformers\ActivityTransformer;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends BaseController
{
    use PdfMaker, PageNumbering;

    protected $entity_type = Activity::class;

    protected $entity_transformer = ActivityTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Get(
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
     * @param Request $request
     * @return Response|mixed
     */
    public function index(Request $request)
    {
        $default_activities = $request->has('rows') ? $request->input('rows') : 50;

        $activities = Activity::orderBy('created_at', 'DESC')->company()
                                ->take($default_activities);

        if ($request->has('react')) {
            $system = ctrans('texts.system');

            $data = $activities->cursor()->map(function ($activity) use ($system) {
                $arr =
                [
                    'client' => $activity->client ? $activity->client : '',
                    'contact' => $activity->contact ? $activity->contact : '',
                    'quote' => $activity->quote ? $activity->quote : '',
                    'user' => $activity->user ? $activity->user : '',
                    'expense' => $activity->expense ? $activity->expense : '',
                    'invoice' => $activity->invoice ? $activity->invoice : '',
                    'recurring_invoice' => $activity->recurring_invoice ? $activity->recurring_invoice : '',
                    'payment' => $activity->payment ? $activity->payment : '',
                    'credit' => $activity->credit ? $activity->credit : '',
                    'task' => $activity->task ? $activity->task : '',
                ];

                return array_merge($arr, $activity->toArray());
            });

            return response()->json(['data' => $data->toArray()], 200);
        }

        return $this->listResponse($activities);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/actvities/download_entity/{activity_id}",
     *      operationId="getActivityHistoricalEntityPdf",
     *      tags={"actvities"},
     *      summary="Gets a PDF for the given activity",
     *      description="Gets a PDF for the given activity",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="activity_id",
     *          in="path",
     *          description="The Activity Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="PDF File",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=404,
     *          description="No file exists for the given record",
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param DownloadHistoricalEntityRequest $request
     * @param Activity $activity
     * @return JsonResponse|StreamedResponse
     */
    public function downloadHistoricalEntity(DownloadHistoricalEntityRequest $request, Activity $activity)
    {
        $backup = $activity->backup;
        $html_backup = '';

        /* Refactor 20-10-2021
         *
         * We have moved the backups out of the database and into object storage.
         * In order to handle edge cases, we still check for the database backup
         * in case the file no longer exists
        */

        if ($backup && $backup->filename && Storage::disk(config('filesystems.default'))->exists($backup->filename)) { //disk

            if (Ninja::isHosted()) {
                $html_backup = file_get_contents(Storage::disk(config('filesystems.default'))->url($backup->filename));
            } else {
                $html_backup = file_get_contents(Storage::disk(config('filesystems.default'))->path($backup->filename));
            }
        } elseif ($backup && $backup->html_backup) { //db
            $html_backup = $backup->html_backup;
        } elseif (! $backup || ! $backup->html_backup) { //failed
            return response()->json(['message'=> ctrans('texts.no_backup_exists'), 'errors' => new stdClass], 404);
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom)->convertHtmlToPdf($html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } else {
            $pdf = $this->makePdf(null, null, $html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        }

        if (isset($activity->invoice_id)) {
            $filename = $activity->invoice->numberFormatter().'.pdf';
        } elseif (isset($activity->quote_id)) {
            $filename = $activity->quote->numberFormatter().'.pdf';
        } elseif (isset($activity->credit_id)) {
            $filename = $activity->credit->numberFormatter().'.pdf';
        } else {
            $filename = 'backup.pdf';
        }

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
