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

use App\Models\ClientContact;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class AuthController
{
    public function login(Subscription $subscription): JsonResponse
    {
        $contact = ClientContact::where('email', request()->email)
            ->where('company_id', $subscription->company_id)
            ->first();

        if (!$contact) {
            return response()->noContent(401);
        }

        return response()->json([
            'id' => $contact->hashed_id,
            'email' => $contact->email,
        ]);
    }
}
