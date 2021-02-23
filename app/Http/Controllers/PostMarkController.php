<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

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
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
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

        if($request->header('X-API-SECURITY') && $request->header('X-API-SECURITY') == config('postmark.secret'))
        {

        }

    }





// {
//   "RecordType": "Delivery",
//   "ServerID": 23,
//   "MessageStream": "outbound",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "Recipient": "john@example.com",
//   "Tag": "welcome-email",
//   "DeliveredAt": "2021-02-21T16:34:52Z",
//   "Details": "Test delivery webhook details",
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   }
// }
    private function processDelivery($request)
    {

    }

// {
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   },
//   "RecordType": "Bounce",
//   "ID": 42,
//   "Type": "HardBounce",
//   "TypeCode": 1,
//   "Name": "Hard bounce",
//   "Tag": "Test",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "ServerID": 1234,
//   "MessageStream": "outbound",
//   "Description": "The server was unable to deliver your message (ex: unknown user, mailbox not found).",
//   "Details": "Test bounce details",
//   "Email": "john@example.com",
//   "From": "sender@example.com",
//   "BouncedAt": "2021-02-21T16:34:52Z",
//   "DumpAvailable": true,
//   "Inactive": true,
//   "CanActivate": true,
//   "Subject": "Test subject",
//   "Content": "Test content"
// }

    private function processBounce($request)
    {

    }

// {
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   },
//   "RecordType": "SpamComplaint",
//   "ID": 42,
//   "Type": "SpamComplaint",
//   "TypeCode": 100001,
//   "Name": "Spam complaint",
//   "Tag": "Test",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "ServerID": 1234,
//   "MessageStream": "outbound",
//   "Description": "The subscriber explicitly marked this message as spam.",
//   "Details": "Test spam complaint details",
//   "Email": "john@example.com",
//   "From": "sender@example.com",
//   "BouncedAt": "2021-02-21T16:34:52Z",
//   "DumpAvailable": true,
//   "Inactive": true,
//   "CanActivate": false,
//   "Subject": "Test subject",
//   "Content": "Test content"
// }
    private function processSpamComplaint($request)
    {

    }


}
