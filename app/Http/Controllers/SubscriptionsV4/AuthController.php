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
use App\Http\Requests\SubscriptionsV4\AuthenticateRequest;
use App\Livewire\BillingPortal\Authentication\ClientRegisterService;
use App\Livewire\BillingPortal\Authentication\Login;
use App\Livewire\BillingPortal\Authentication\Register;
use App\Livewire\BillingPortal\Authentication\RegisterOrLogin;
use App\Models\ClientContact;
use App\Models\Subscription;
use App\Services\Subscription\StepService;
use App\Transformers\ClientContactTransformer;
use App\Transformers\ClientTransformer;
use Illuminate\Support\Str;
use Laracasts\Presenter\Exceptions\PresenterException;

class AuthController
{
    /**
     * @throws PresenterException
     */
    public function authenticate(
        Subscription             $subscription,
        AuthenticateRequest      $request,
        ClientTransformer        $client_transformer,
        ClientContactTransformer $client_contact_transformer
    )
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
                    'key' => $key,
                    'contact' => $client_contact_transformer->transform($contact),
                    'client' => $client_transformer->transform($contact->client),
                ]);
            }

            return response()->noContent(401);
        }

        if (in_array(Login::class, $steps) && $contact === null) {
            return response()->noContent(401);
        }

        $service = new ClientRegisterService(
            company: $subscription->company,
        );

        $client = $service->createClient($request->registration_fields);
        $contact = $service->createClientContact($request->registration_fields, $client);

        $key = Str::random(64);

        cache()->put(
            key: $key,
            value: $contact->id,
        );

        return response()->json([
            'key' => $key,
            'contact' => $client_contact_transformer->transform($contact),
            'client' => $client_transformer->transform($contact->client),
        ]);
    }

    public function check(Subscription $subscription, LoginCheckRequest $request): \Illuminate\Http\JsonResponse
    {
        $contact = ClientContact::query()
            ->where('email', $request->email)
            ->where('company_id', $subscription->company_id)
            ->first();

        $steps = StepService::mapToClassNames(
            steps: $subscription->steps,
        );

        $register = in_array(Register::class, $steps) || in_array(RegisterOrLogin::class, $steps);

        return response()->json([
            'existing' => $contact !== null,
            'register' => (bool) $register,
        ]);
    }
}
