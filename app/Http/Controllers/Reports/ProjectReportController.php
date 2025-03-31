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

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Report\ProjectReportRequest;
use App\Jobs\Report\PreviewReport;
use App\Jobs\Report\SendToAdmin;
use App\Services\Report\ProjectReport;
use App\Utils\Traits\MakesHash;

class ProjectReportController extends BaseController
{
    use MakesHash;

    private string $filename = 'project_report.pdf';

    public function __construct()
    {
        parent::__construct();
    }
    public function __invoke(ProjectReportRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($request->has('send_email') && $request->get('send_email') && $request->missing('output')) {
            SendToAdmin::dispatch($user->company(), $request->all(), ProjectReport::class, $this->filename);

            return response()->json(['message' => 'working...'], 200);
        }

        $hash = \Illuminate\Support\Str::uuid();

        PreviewReport::dispatch($user->company(), $request->all(), ProjectReport::class, $hash);

        return response()->json(['message' => $hash], 200);

    }
}
