<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Livewire\BillingPortal\Purchase;
use App\Rules\Subscriptions\Steps;
use Illuminate\Http\JsonResponse;

class SubscriptionStepsController extends BaseController
{
    public function index(): JsonResponse
    {
        $dependencies = collect(Purchase::$dependencies)
            ->map(fn ($dependency) => [
                'id' => $dependency['id'],
                'dependencies' => collect($dependency['dependencies'])
                    ->map(fn ($dependency) => Purchase::$dependencies[$dependency]['id'])
                    ->toArray(),
            ])
            ->toArray();

        return response()->json($dependencies);
    }

    public function check(): JsonResponse
    {
        request()->validate(([
            'steps' => ['required', new Steps()]
        ]));

        return response()->json([], 200);
    }
}
