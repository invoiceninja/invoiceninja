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

namespace App\Http\Controllers;

use App\Http\Requests\CalendarConnection\AuthCalendarConnectionRequest;
use App\Http\Requests\CalendarConnection\CalendarConnectionEventsRequest;
use App\Http\Requests\CalendarConnection\CompleteCalendarConnectionRequest;
use App\Http\Requests\CalendarConnection\UpdateCalendarConnectionCalendarsRequest;
use App\Libraries\MultiDB;
use App\Models\User;
use App\Services\Calendar\CalendarConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalendarConnectionController extends BaseController
{
    public function show(CalendarConnectionService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($this->user())]);
    }

    /**
     * Starts the OAuth handshake.
     *
     * Resolves the one-time token to its initiator, switches to that tenant,
     * builds the provider's consent URL with a fresh state token, and 302s
     * the browser to the provider. Mirrors the QuickBooks authorize/{token}
     * pattern so calendar shares the same start-of-flow shape as every other
     * OAuth integration in the codebase.
     */
    public function redirectToProvider(string $provider, string $hash, AuthCalendarConnectionRequest $request, CalendarConnectionService $service): RedirectResponse
    {
        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $url = $service->buildAuthorizationUrl($request->resolveUser(), $provider);

        return redirect()->to($url);
    }

    /**
     * Receives the provider's redirect back to the application.
     *
     * Pure bouncepoint: never calls Socialite, never persists tokens. Stores
     * the provider state and code in a short-lived handoff cache entry, then
     * forwards only that handoff token to the React SPA hash route.
     */
    public function callback(string $provider, Request $request, CalendarConnectionService $service): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->redirectToReact($provider, 'denied');
        }

        $state = (string) $request->query('state', '');
        $code  = (string) $request->query('code', '');

        if ($state === '' || $code === '') {
            return $this->redirectToReact($provider, 'failed');
        }

        $handoff = $service->cacheCallbackHandoff($provider, $state, $code);

        return $this->redirectToReact($provider, 'pending', $handoff);
    }

    /**
     * Authenticated finalisation step.
     *
     * Posted by the React SPA after the popup lands on the bouncepoint. New
     * clients send the handoff token; legacy clients may still send state and
     * code directly. The service keeps the state/user/database binding check.
     */
    public function complete(string $provider, CompleteCalendarConnectionRequest $request, CalendarConnectionService $service): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['handoff'])) {
            $handoff = $service->resolveCallbackHandoff($provider, (string) $validated['handoff']);
            $state = $handoff['state'];
            $code = $handoff['code'];
        } else {
            $state = (string) $validated['state'];
            $code = (string) $validated['code'];
        }

        $service->completeConnection(
            $this->user(),
            $provider,
            $state,
            $code,
        );

        return response()->json(['data' => $service->show($this->user())]);
    }

    public function calendars(CalendarConnectionService $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'calendars' => $service->availableCalendars($this->user()),
            ],
        ]);
    }

    public function events(CalendarConnectionEventsRequest $request, CalendarConnectionService $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'events' => $service->events(
                    $this->user(),
                    (string) $request->validated('from'),
                    (string) $request->validated('to'),
                ),
            ],
        ]);
    }

    public function updateCalendars(UpdateCalendarConnectionCalendarsRequest $request, CalendarConnectionService $service): JsonResponse
    {
        $connection = $service->updateCalendars($this->user(), $request->validated('calendar_ids'));

        return response()->json([
            'data' => [
                'calendar_connection' => $connection->toArray(),
            ],
        ]);
    }

    public function destroy(CalendarConnectionService $service): JsonResponse
    {
        $service->disconnect($this->user());

        return response()->json([
            'data' => [
                'calendar_connection' => null,
            ],
        ]);
    }

    private function redirectToReact(string $provider, string $status, ?string $handoff = null): RedirectResponse
    {
        $baseUrl = rtrim(config('ninja.react_url') ?: config('ninja.app_url'), '/#');

        $params = ['calendar_connection' => $status, 'provider' => $provider];

        if ($handoff !== null) {
            $params['handoff'] = $handoff;
        }

        return redirect()->away($baseUrl . '/#/calendar_connection/complete?' . http_build_query($params));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
