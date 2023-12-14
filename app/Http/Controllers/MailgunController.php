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

use App\Jobs\Mailgun\ProcessMailgunInboundWebhook;
use App\Jobs\Mailgun\ProcessMailgunWebhook;
use Illuminate\Http\Request;

/**
 * Class MailgunController.
 */
class MailgunController extends BaseController
{
    private $invitation;

    public function __construct()
    {
    }

    /**
     * Process Mailgun Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_webhook",
     *      operationId="mailgunWebhook",
     *      tags={"mailgun"},
     *      summary="Processing webhooks from Mailgun",
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
        if ($request->header('X-API-SECURITY') && $request->header('X-API-SECURITY') == config('services.mailgun.token')) {
            ProcessMailgunWebhook::dispatch($request->all())->delay(10);

            return response()->json(['message' => 'Success'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Process Mailgun Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_inbound_webhook",
     *      operationId="mailgunInboundWebhook",
     *      tags={"mailgun"},
     *      summary="Processing inbound webhooks from Mailgun",
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
    public function inboundWebhook(Request $request)
    {
        if ($request->header('X-API-SECURITY') && $request->header('X-API-SECURITY') == config('services.mailgun.token')) {
            ProcessMailgunInboundWebhook::dispatch($request->all())->delay(10);

            return response()->json(['message' => 'Success'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
