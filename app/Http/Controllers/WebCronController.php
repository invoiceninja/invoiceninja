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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class WebCronController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/webcron",
     *      operationId="webcron",
     *      tags={"webcron"},
     *      summary="Executes the task scheduler via a webcron service",
     *      description="Executes the task scheduler via a webcron service",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="Success response",
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
    public function index(Request $request)
    {
        set_time_limit(0);

        if (! config('ninja.webcron_secret')) {
            return response()->json(['message' => 'Web cron has not been configured'], 403);
        }

        if ($request->has('secret') && (config('ninja.webcron_secret') == $request->query('secret'))) {
            Artisan::call('schedule:run');

            return response()->json(['message' => 'Executing web cron'], 200);
        }

        return response()->json(['message' => 'Invalid secret'], 403);
    }
}
