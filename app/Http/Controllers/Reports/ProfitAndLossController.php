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

namespace App\Http\Controllers\Reports;

use App\Export\CSV\PaymentExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Report\ProfitLossRequest;
use App\Jobs\Report\SendToAdmin;
use App\Models\Client;
use App\Services\Report\ProfitLoss;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class ProfitAndLossController extends BaseController
{
    use MakesHash;

    private string $filename = 'profit_and_loss.csv';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/reports/profitloss",
     *      operationId="getProfitLossReport",
     *      tags={"reports"},
     *      summary="Profit loss reports",
     *      description="Profit loss report",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/GenericReportSchema")
     *      ),
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
    public function __invoke(ProfitLossRequest $request)
    {
        if ($request->has('send_email') && $request->get('send_email')) {
            SendToAdmin::dispatch(auth()->user()->company(), $request->all(), ProfitLoss::class, $this->filename);

            return response()->json(['message' => 'working...'], 200);
        }
        // expect a list of visible fields, or use the default

        $pnl = new ProfitLoss(auth()->user()->company(), $request->all());
        $pnl->build();

        $csv = $pnl->getCsv();

        $headers = [
            'Content-Disposition' => 'attachment',
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $this->filename, $headers);
    }
}
