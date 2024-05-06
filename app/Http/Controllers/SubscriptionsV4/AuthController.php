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

namespace App\Http\Controllers\SubscriptionsV4;

use App\Models\ClientContact;
use App\Models\Subscription;

class AuthController
{
    public function login(Subscription $subscription)
    {
        $contact = ClientContact::where('email', request()->email)
            ->where('company_id', $subscription->company_id)
            ->first();

        if (!$contact) {
            return response()->noContent(401);
        }

        $attempt = auth()->guard('contact')->attempt([
            'email' => request()->email,
            'password' => request()->password,
            'company_id' => $subscription->company_id,
        ]);

        if (!$attempt) {
            return response()->noContent(401);
        }

        return response()->json(
            auth()->guard('contact')->user()->client
        );
    }
}
