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

class SchedulerController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->company()->account->latest_version == '0.0.0') {
            return response()->json(['message' => ctrans('texts.scheduler_has_never_run')], 400);
        } else {
            return response()->json(['message' => ctrans('texts.scheduler_has_run')], 200);
        }
    }
}
