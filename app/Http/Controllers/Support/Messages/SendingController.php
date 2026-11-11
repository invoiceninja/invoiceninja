<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Http\Controllers\Support\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Messages\SendMessageRequest;
use App\Mail\SupportMessageSent;
use Illuminate\Support\Facades\Mail;

class SendingController extends Controller
{
    /**
     * Send a support message.
     *
     * @OA\Post(
     *      path="/api/v1/support/messages/send",
     *      operationId="supportMessage",
     *      tags={"support"},
     *      summary="Sends a support message to Invoice Ninja team",
     *      description="Allows a user to send a support message to the Invoice Ninja Team",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *         description="The message",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="message",
     *                     description="The support message",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     description="Images with a combined size of no more than 10 MB",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                 ),
     *             )
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\MediaType(
     *             mediaType="application/json",
     *               @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="string",
     *                     description="Server response",
     *                     example=true,
     *                 ),
     *             )
     *          )
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param SendMessageRequest $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function __invoke(SendMessageRequest $request)
    {
        $send_logs = false;

        if ($request->has('send_logs')) {
            $send_logs = $request->input('send_logs');
        }

        Mail::to(config('ninja.contact.ninja_official_contact'))
            ->send(new SupportMessageSent($request->all(), $send_logs));

        return response()->json([
            'success' => true,
        ], 200);
    }
}
