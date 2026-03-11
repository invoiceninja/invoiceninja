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

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Libraries\MultiDB;
use App\Models\PasskeyCredential;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Auth\Passkeys\PasskeyService;

class PasskeyController extends BaseController
{
    public function __construct(private readonly PasskeyService $passkeyService)
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return response()->json([
            'data' => $user->passkey_credentials
                ->map(fn (PasskeyCredential $credential) => [
                    'id' => $credential->hashed_id,
                    'name' => $credential->name,
                    'created_at' => $credential->created_at,
                    'last_used_at' => $credential->last_used_at?->timestamp,
                ])
                ->values(),
        ]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = $this->passkeyService->getRegistrationOptions(
            $user,
            $request->string('name')->toString() ?: null
        );

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'credential' => ['required', 'array'],
            'credential.clientDataJSON' => ['required', 'string'],
            'credential.attestationObject' => ['required', 'string'],
            'credential.transports' => ['nullable', 'array'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $credential = $this->passkeyService->registerCredential(
                $user,
                $validated['challenge_token'],
                $validated['credential'],
                $validated['name'] ?? null
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $credential->hashed_id,
                'name' => $credential->name,
            ],
            'message' => 'Passkey added',
        ]);
    }

    public function destroy(PasskeyCredential $passkey): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ((int) $passkey->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $passkey->delete();

        return response()->json(['message' => 'Passkey removed']);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        /** @var \App\Models\User|null $user */
        $user = MultiDB::hasUser(['email' => $validated['email'], 'is_deleted' => 0]);

        if (!$user || $user->trashed() || $user->is_deleted || !$user->passkey_credentials()->exists()) {
            return response()->json(['message' => ctrans('texts.invalid_credentials')], 401);
        }

        $data = $this->passkeyService->getAuthenticationOptions($user, true);

        return response()->json($data);
    }
}
