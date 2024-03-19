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
use Log;

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

        $input = $request->all();

        if (\abs(\time() - $request['signature']['timestamp']) > 15) {
            return response()->json(['message' => 'Success'], 200);
        }

        if (\hash_equals(\hash_hmac('sha256', $input['signature']['timestamp'] . $input['signature']['token'], config('services.mailgun.webhook_signing_key')), $input['signature']['signature'])) {
            ProcessMailgunWebhook::dispatch($request->all())->delay(10);
        }

        return response()->json(['message' => 'Success.'], 200);
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
        $input = $request->all();

        if (!array_key_exists('recipient', $input) || !array_key_exists('message-url', $input)) {
            Log::info('Failed: Message could not be parsed, because required parameters are missing. Please ensure contacting this api-endpoint with a store & notify operation instead of a forward operation!');
            return response()->json(['message' => 'Failed. Missing Parameters'], 400);
        }

        if (!array_key_exists('attachments', $input) || count(json_decode($input['attachments'])) == 0) {
            Log::info('Message ignored because of missing attachments. No Actions would have been taken...');
            return response()->json(['message' => 'Sucess. Soft Fail. Missing Attachments.'], 200);
        }

        if (\abs(\time() - (int) $input['timestamp']) > 150) {
            Log::info('Message ignored because of request body is too old.');
            return response()->json(['message' => 'Success. Soft Fail. Message too old.'], 200);
        }

        // @turbo124 TODO: how to check for services.mailgun.webhook_signing_key on company level, when custom credentials are defined
        if (\hash_equals(\hash_hmac('sha256', $input['timestamp'] . $input['token'], config('services.mailgun.webhook_signing_key')), $input['signature'])) {
            ProcessMailgunInboundWebhook::dispatch($input["recipient"] . "|" . $input["message-url"])->delay(10);

            return response()->json(['message' => 'Success'], 201);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
