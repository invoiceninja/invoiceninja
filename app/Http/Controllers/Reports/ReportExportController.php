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
use App\Http\Requests\Report\ReportPreviewRequest;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;

class ReportExportController extends BaseController
{
    use MakesHash;

    public function __construct()
    {
        parent::__construct();
    }

    public function __invoke(ReportPreviewRequest $request, ?string $hash)
    {
        $report = Cache::get($hash);

        if (!$report) {
            return response()->json(['message' => 'Still working.....'], 409);
        }

        Cache::forget($hash);

        // Check if the content starts with PDF signature (%PDF-)
        $isPdf = str_starts_with(trim($report), '%PDF-');
        
        $attachment_name = $isPdf ? 'report.pdf' : 'report.csv';

        $headers = [
            'Content-Disposition' => "attachment; filename=\"{$attachment_name}\"",
            'Content-Type' => $isPdf ? 'application/pdf' : 'text/csv'
        ];

        // Set appropriate filename extension

        return response()->streamDownload(function () use ($report) {

            echo $report;

        }, $attachment_name, $headers);

    }
}
