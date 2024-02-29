<?php

namespace App\Http\Controllers;

use App\Livewire\BillingPortal\Purchase;
use Illuminate\Http\JsonResponse;

class SubscriptionStepsController extends BaseController
{
    public function index(): JsonResponse
    {
        // @todo: perhaps integrate this in statics

        $dependencies = collect(Purchase::$dependencies)
            ->map(fn($dependency) => [
                'id' => $dependency['id'],
                'dependencies' => collect($dependency['dependencies'])
                    ->map(fn($dependency) => Purchase::$dependencies[$dependency]['id'])
                    ->toArray(),
            ])
            ->toArray();

        return response()->json($dependencies);
    }
}
