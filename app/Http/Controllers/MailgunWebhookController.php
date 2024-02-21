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

use App\Jobs\Mailgun\ProcessMailgunWebhook;
use App\Jobs\PostMark\ProcessPostmarkWebhook;
use Illuminate\Http\Request;

/**
 * Class MailgunWebhookController.
 */
class MailgunWebhookController extends BaseController
{
    private $invitation;

    public function __construct()
    {
    }

    public function webhook(Request $request)
    {

        $input = $request->all();

        if (\abs(\time() - $request['signature']['timestamp']) > 15) {
            return response()->json(['message' => 'Success'], 200);
        }

        if(\hash_equals(\hash_hmac('sha256', $input['signature']['timestamp'] . $input['signature']['token'], config('services.mailgun.webhook_signing_key')), $input['signature']['signature'])) {
            ProcessMailgunWebhook::dispatch($request->all())->delay(10);
        }

        return response()->json(['message' => 'Success.'], 200);
    }
}
