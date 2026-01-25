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

use App\Services\Chart\ChartService;
use App\Http\Requests\Chart\ShowChartRequest;
use App\Http\Requests\Chart\ShowCalculatedFieldRequest;

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
