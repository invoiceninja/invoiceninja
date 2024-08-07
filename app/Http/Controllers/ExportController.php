<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Export\StoreExportRequest;
use App\Jobs\Company\CompanyExport;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExportController extends BaseController
{
    use MakesHash;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/export",
     *      operationId="getExport",
     *      tags={"export"},
     *      summary="Export data from the system",
     *      description="Export data from the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
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
    public function index(StoreExportRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $hash = Str::uuid()->toString();
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('protected_download', now()->addHour(), ['hash' => $hash]);
        Cache::put($hash, $url, 3600);

        CompanyExport::dispatch($user->getCompany(), $user, $hash);

        return response()->json(['message' => 'Processing', 'url' => $url], 200);
    }
}
