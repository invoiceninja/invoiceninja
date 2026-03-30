<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Services\Chart\ChartService;
use App\Http\Requests\Chart\ShowChartRequest;
use App\Http\Requests\Chart\ShowForecastRequest;
use App\Http\Requests\Chart\ShowCalculatedFieldRequest;
use Illuminate\Support\Facades\Cache;

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
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);

        return response()->json($cs->totals($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function chart_summary(ShowChartRequest $request)
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);

        return response()->json($cs->chart_summary($request->input('start_date'), $request->input('end_date')), 200);
    }

    /**
     * @param ShowChartRequest $request
     */
    public function totalsV2(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions, $request->input('include_drafts', false));

        return response()->json($cs->totals($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function chart_summaryV2(ShowChartRequest $request)
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);

        return response()->json($cs->chart_summary($request->input('start_date'), $request->input('end_date')), 200);
    }

    public function analytics_summary(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $cacheKey = "analytics_summary:{$user->company()->id}:{$user->id}:{$start}:{$end}";

        $data = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $admin_equivalent_permissions, $start, $end) {
            $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
            return $cs->analytics_summary($start, $end);
        });

        return response()->json($data, 200);
    }

    public function analytics_totals(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $cacheKey = "analytics_totals:{$user->company()->id}:{$user->id}:{$start}:{$end}";

        $data = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $admin_equivalent_permissions, $start, $end) {
            $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
            return $cs->analytics_totals($start, $end);
        });

        return response()->json($data, 200);
    }

    public function cashflow_forecast(ShowForecastRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $bucket = $request->input('bucket_type', 'monthly');
        $cacheKey = "cashflow_forecast:{$user->company()->id}:{$user->id}:{$start}:{$end}:{$bucket}";

        $data = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $admin_equivalent_permissions, $start, $end, $bucket) {
            $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
            return $cs->cashflow_forecast($start, $end, $bucket);
        });

        return response()->json($data, 200);
    }

    public function client_payment_analytics(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cacheKey = "client_payment_analytics:{$user->company()->id}:{$user->id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $admin_equivalent_permissions) {
            $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
            return $cs->client_payment_analytics();
        });

        return response()->json($data, 200);
    }

    public function project_analytics(ShowChartRequest $request)
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cacheKey = "project_analytics:{$user->company()->id}:{$user->id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $admin_equivalent_permissions) {
            $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
            return $cs->project_analytics();
        });

        return response()->json($data, 200);
    }

    public function calculatedFields(ShowCalculatedFieldRequest $request)
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $admin_equivalent_permissions = $user->isAdmin() || $user->hasExactPermissionAndAll('view_all') || $user->hasExactPermissionAndAll('edit_all');

        $cs = new ChartService($user->company(), $user, $admin_equivalent_permissions);
        $result = $cs->getCalculatedField($request->all());

        return response()->json($result, 200);

    }
}
