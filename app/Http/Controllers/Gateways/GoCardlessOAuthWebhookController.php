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

// @todo: Double check if this should be in admin module

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoCardlessOAuthController extends Controller
{  
    public function __invoke(Request $request)
    {
        foreach ($request->events as $event) {
            match ($event['details']['cause']) {
                'app_disconnected' => info($event),
                default => nlog('Not acting on this event type: ' . $event['details']['cause']),
            };
        }
    }
}
