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

use App\Http\Requests\Account\CreateAccountRequest;
use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Statics;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;

class StaticController extends BaseController
{
    /**
     * Show the list of Invoices.
     *
     * @param InvoiceFilters $filters  The filters
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/statics",
     *      operationId="getStatics",
     *      tags={"statics"},
     *      summary="Gets a list of statics",
     *      description="Lists all statics",
     *
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of static data",
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
    public function __invoke()
    {
        $response = Statics::company(auth()->user()->getCompany()->getLocale());

        return response()->json($response, 200, ['Content-type'=> 'application/json; charset=utf-8'], JSON_PRETTY_PRINT);
    }
}
