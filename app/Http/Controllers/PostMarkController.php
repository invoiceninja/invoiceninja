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

use App\Jobs\PostMark\ProcessPostmarkWebhook;
use Illuminate\Http\Request;

/**
 * Class PostMarkController.
 */
class PostMarkController extends BaseController
{
    public function __construct()
    {
    }

    /**
     * Process Postmark Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/postmark_webhook",
     *      operationId="postmarkWebhook",
     *      tags={"postmark"},
     *      summary="Processing webhooks from PostMark",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function webhook(Request $request)
    {
        if ($request->header('X-API-SECURITY') && $request->header('X-API-SECURITY') == config('services.postmark.token')) {
            ProcessPostmarkWebhook::dispatch($request->all())->delay(10);

            return response()->json(['message' => 'Success'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
