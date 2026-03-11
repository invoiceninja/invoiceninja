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

    /**
     * List all passkey credentials for the authenticated user.
     *
     * Returns each credential's hashed ID, display name, creation timestamp,
     * and the Unix timestamp of its last successful authentication (if any).
     *
     * @return JsonResponse
     */
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

    /**
     * Generate WebAuthn registration options for the authenticated user.
     *
     * Creates a new challenge and returns the PublicKeyCredentialCreationOptions
     * payload that the browser's WebAuthn API needs to create a new credential.
     * An optional display name may be provided; otherwise the user's full name is used.
     *
     * @param  Request       $request
     * @return JsonResponse  The WebAuthn creation options including the challenge token.
     */
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

    /**
     * Complete passkey registration by verifying and storing the new credential.
     *
     * Validates the challenge token and attestation payload from the browser,
     * delegates cryptographic verification to PasskeyService, and persists
     * the new PasskeyCredential. Returns 422 if registration fails (e.g.
     * invalid attestation, duplicate credential, or maximum limit reached).
     *
     * @param  Request       $request
     * @return JsonResponse  The newly created credential's hashed ID and name, or an error.
     */
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

    /**
     * Delete a passkey credential belonging to the authenticated user.
     *
     * Verifies ownership by comparing the credential's user_id against the
     * authenticated user before deletion. Returns 403 if the credential
     * belongs to a different user.
     *
     * @param  PasskeyCredential  $passkey  The credential resolved via route-model binding.
     * @return JsonResponse
     */
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

    /**
     * Generate WebAuthn authentication options for a pre-login passkey challenge.
     *
     * This is an unauthenticated endpoint. Given an email address, it resolves
     * the user across all tenant databases and returns PublicKeyCredentialRequestOptions
     * scoped to that user's registered credentials. Returns a generic 401 error if the
     * user is not found, is deleted, or has no passkeys registered — avoiding user
     * enumeration by not distinguishing between these cases.
     *
     * @param  Request       $request
     * @return JsonResponse  The WebAuthn assertion options including the challenge token.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        /** @var \App\Models\User|null $user */
        $user = MultiDB::hasUser(['email' => $validated['email'], 'is_deleted' => 0, 'deleted_at' => null]);

        if (!$user || !$user->passkey_credentials()->exists()) {
            return response()->json(['message' => ctrans('texts.invalid_credentials')], 400);
        }

        $data = $this->passkeyService->getAuthenticationOptions($user, true);

        return response()->json($data);
    }
}
