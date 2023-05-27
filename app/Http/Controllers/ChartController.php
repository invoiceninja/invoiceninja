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

use App\Http\Requests\Chart\ShowChartRequest;
use App\Services\Chart\ChartService;

class ChartController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param ShowChartRequest $request
     */
    public function totals(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $cs = new ChartService($user->company(), $user, $user->isAdmin());

        return response()->json($cs->totals($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function chart_summary(ShowChartRequest $request)
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $cs = new ChartService($user->company(), $user, $user->isAdmin());

        return response()->json($cs->chart_summary($request->input('start_date'), $request->input('end_date')), 200);
    }

    /**
     * @param ShowChartRequest $request
     */
    public function totalsV2(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $cs = new ChartService($user->company(), $user, $user->isAdmin());

        return response()->json($cs->totals($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function chart_summaryV2(ShowChartRequest $request)
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $cs = new ChartService($user->company(), $user, $user->isAdmin());

        return response()->json($cs->chart_summary($request->input('start_date'), $request->input('end_date')), 200);
    }


}
