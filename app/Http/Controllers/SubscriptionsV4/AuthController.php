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

use App\Http\Requests\SubscriptionsV4\LoginCheckRequest;
use App\Http\Requests\SubscriptionsV4\LoginRequest;
use App\Livewire\BillingPortal\Authentication\Login;
use App\Livewire\BillingPortal\Authentication\Register;
use App\Livewire\BillingPortal\Authentication\RegisterOrLogin;
use App\Models\ClientContact;
use App\Models\Subscription;
use App\Services\Subscription\StepService;
use Illuminate\Support\Str;

class AuthController
{
    public function login(Subscription $subscription, LoginRequest $request)
    {
        $steps = StepService::mapToClassNames(
            steps: $subscription->steps,
        );

        $contact = ClientContact::query()
            ->where('email', $request->email)
            ->where('company_id', $subscription->company_id)
            ->first();

        if ($contact) {
            $attempt = auth()->guard('contact')->attempt([
                'email' => request()->email,
                'password' => request()->password,
                'company_id' => $subscription->company_id,
            ]);

            if ($attempt) {
                $key = Str::random(64);

                cache()->put(
                    key: $key,
                    value: $contact->id,
                );

                return response()->json([
                    'contact' => auth()->guard('contact')->user()->client,
                    'key' => $key,
                ]);
            }

            return response()->noContent(401);
        }

        if (in_array(Login::class, $steps) && $contact === null) {
            return response()->noContent(401);
        }

        // Otherwise register user
        // Send email and configure otp/e-mail auth
    }

    public function check(Subscription $subscription, LoginCheckRequest $request): \Illuminate\Http\Response
    {
        $contact = ClientContact::query()
            ->where('email', $request->email)
            ->where('company_id', $subscription->company_id)
            ->first();

        if ($contact) {
            return response()->noContent(200);
        }

        $steps = StepService::mapToClassNames(
            steps: $subscription->steps,
        );

        if (
            in_array(Register::class, $steps) ||
            in_array(RegisterOrLogin::class, $steps)
        ) {
            return response()->noContent(200);
        }

        return response()->noContent(404);
    }
}
