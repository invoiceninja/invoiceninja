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

use App\Http\Requests\CalendarConnection\UpdateCalendarConnectionCalendarsRequest;
use App\Models\User;
use App\Services\Calendar\CalendarConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CalendarConnectionController extends BaseController
{
    public function show(CalendarConnectionService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($this->user())]);
    }

    public function authorizeProvider(string $provider, CalendarConnectionService $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'url' => $service->authorizeUrl($this->user(), $provider),
            ],
        ]);
    }

    public function callback(string $provider, Request $request, CalendarConnectionService $service): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->redirectToReact('denied');
        }

        try {
            $service->handleCallback($provider, $request->query('state'));
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToReact('failed');
        }

        return $this->redirectToReact('connected');
    }

    public function calendars(CalendarConnectionService $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'calendars' => $service->availableCalendars($this->user()),
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

    private function redirectToReact(string $status): RedirectResponse
    {
        $baseUrl = rtrim(config('ninja.react_url') ?: config('ninja.app_url'), '/');

        return redirect()->to($baseUrl . '/#/settings/user_details/connect?calendar_connection=' . $status);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
