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

use App\Utils\Ninja;
use App\Utils\SystemHealth;
use Illuminate\Http\Response;

class PingController extends BaseController
{
    /**
     * Get a ping response from the system.
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/ping",
     *      operationId="getPing",
     *      tags={"ping"},
     *      summary="Attempts to ping the API",
     *      description="Attempts to ping the API",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The company and user name",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       )
     *     )
     */
    public function index()
    {
        return response()->json(
            ['company_name' => auth()->user()->getCompany()->present()->name(),
                'user_name' => auth()->user()->present()->name(),
            ],
            200
        );
    }

    /**
     * Get a health check of the system.
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/health_check",
     *      operationId="getHealthCheck",
     *      tags={"health_check"},
     *      summary="Attempts to get a health check from the API",
     *      description="Attempts to get a health check from the API",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="A key/value map of the system health",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       )
     *     )
     */
    public function health()
    {
        if (Ninja::isNinja()) {
            return response()->json(['message' => ctrans('texts.route_not_available'), 'errors'=>[]], 403);
        }

        return response()->json(SystemHealth::check(), 200);
    }
}
