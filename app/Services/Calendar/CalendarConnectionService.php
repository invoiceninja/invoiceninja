<?php

namespace App\Services\Calendar;

use App\DataMapper\Referral\CalendarConnection;
use App\DataMapper\UserSettings;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Manages a user's calendar connection lifecycle for Google and Microsoft.
 *
 * Handles the OAuth handshake via Socialite, persists provider tokens and
 * selected calendars on the user's settings, refreshes expired access
 * tokens, and fetches calendar lists and events from the underlying provider
 * APIs.
 */
class CalendarConnectionService
{
    public const STATE_CACHE_PREFIX = 'calendar_connection:state:';

    public const HANDOFF_CACHE_PREFIX = 'calendar_connection:handoff:';

    private const STATE_TTL_MINUTES = 10;

    private const HANDOFF_TTL_MINUTES = 5;

    private const GOOGLE_CALENDARS_ENDPOINT = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    private const GOOGLE_EVENTS_ENDPOINT_FORMAT = 'https://www.googleapis.com/calendar/v3/calendars/%s/events';

    private const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const MICROSOFT_CALENDARS_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/calendars';

    private const MICROSOFT_CALENDAR_VIEW_ENDPOINT_FORMAT = 'https://graph.microsoft.com/v1.0/me/calendars/%s/calendarView';

    private const MICROSOFT_TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    private const MAX_EVENT_RANGE_DAYS = 45;

    private const PROVIDER_HTTP_TIMEOUT_SECONDS = 30;

    /**
     * Returns the user's current calendar connection as an array payload,
     * or null when no provider has been connected yet.
     *
     * @return array<string, mixed>
     */
    public function show(User $user): array
    {
        $connection = $this->userSettings($user)->calendar_connection;

        return [
            'calendar_connection' => $connection?->toArray(),
        ];
    }

    /**
     * Builds the provider authorization URL that kicks off the OAuth handshake.
     *
     * The opaque, single-use state token is stored server-side and bound to
     * the initiating user/tenant so the authenticated `/complete` step can
     * verify the same user is completing the flow they started.
     */
    public function buildAuthorizationUrl(User $user, string $provider): string
    {
        $provider = $this->validateProvider($provider);
        $state = Str::random(64);

        Cache::put(self::STATE_CACHE_PREFIX . $state, [
            'database' => config('database.default'),
            'provider' => $provider,
            'user_id' => $user->id,
        ], now()->addMinutes(self::STATE_TTL_MINUTES));

        return $this->providerAuthorizationUrl($provider, $state);
    }

    private function providerAuthorizationUrl(string $provider, string $state): string
    {
        $driver = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider))
            ->scopes($this->scopes($provider));

        if ($provider === CalendarConnection::PROVIDER_GOOGLE) {
            $driver->with([
                'access_type' => 'offline',
                'include_granted_scopes' => 'true',
                'prompt' => 'consent select_account',
            ]);
        }

        if ($provider === CalendarConnection::PROVIDER_MICROSOFT) {
            $driver->with([
                'prompt' => 'select_account',
            ]);
        }

        $url = $driver->redirect()->getTargetUrl();
        $separator = parse_url($url, PHP_URL_QUERY) ? '&' : '?';

        return $url . $separator . http_build_query(['state' => $state]);
    }

    public function cacheCallbackHandoff(string $provider, string $state, string $code): string
    {
        $provider = $this->validateProvider($provider);
        $handoff = Str::random(64);

        Cache::put(self::HANDOFF_CACHE_PREFIX . $handoff, [
            'provider' => $provider,
            'state' => $state,
            'code' => $code,
        ], now()->addMinutes(self::HANDOFF_TTL_MINUTES));

        return $handoff;
    }

    /**
     * @return array{state: string, code: string}
     */
    public function resolveCallbackHandoff(string $provider, string $handoff): array
    {
        $provider = $this->validateProvider($provider);
        $handoffContext = Cache::pull(self::HANDOFF_CACHE_PREFIX . $handoff);

        if (! is_array($handoffContext)
            || ($handoffContext['provider'] ?? null) !== $provider
            || empty($handoffContext['state'])
            || empty($handoffContext['code'])) {
            throw ValidationException::withMessages(['handoff' => 'Invalid calendar connection handoff.']);
        }

        return [
            'state' => (string) $handoffContext['state'],
            'code' => (string) $handoffContext['code'],
        ];
    }

    /**
     * Completes the OAuth handshake for the authenticated user.
     *
     * Pulls the cached state, asserts the completing user matches the one
     * that initiated the flow (closes cross-account OAuth injection),
     * exchanges the authorization code for tokens via Socialite, and
     * persists the resulting CalendarConnection on the user's settings. A
     * default writable calendar is auto-selected when none is
     * configured yet.
     *
     * @throws ValidationException when the state is missing/invalid, when
     *                             the completing user does not match the
     *                             cached initiator, or when the provider
     *                             does not return a usable refresh token.
     */
    public function completeConnection(User $user, string $provider, string $state, string $code): User
    {
        $provider = $this->validateProvider($provider);

        $stateContext = Cache::pull(self::STATE_CACHE_PREFIX . $state);

        if (!is_array($stateContext)
            || ($stateContext['provider'] ?? null) !== $provider
            || empty($stateContext['user_id'])) {
            throw ValidationException::withMessages(['state' => 'Invalid calendar connection state.']);
        }

        if ((int) $stateContext['user_id'] !== (int) $user->id) {
            report(new \RuntimeException(sprintf(
                'Calendar connection state mismatch: expected user %d, got %d (provider %s).',
                $stateContext['user_id'],
                $user->id,
                $provider,
            )));

            throw ValidationException::withMessages(['state' => 'Invalid calendar connection state.']);
        }

        if (($stateContext['database'] ?? null) !== config('database.default')) {
            throw ValidationException::withMessages(['state' => 'Invalid calendar connection state.']);
        }

        // Socialite's ->user() reads the `code` from the current request input.
        // Merge it explicitly so the exchange works regardless of how the
        // caller delivered the code (query, JSON body, form).
        request()->merge(['code' => $code]);

        $socialiteUser = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider))
            ->user();

        $providerUserId = $socialiteUser->getId();

        if (!$providerUserId) {
            throw ValidationException::withMessages(['provider_user_id' => 'The calendar provider did not return a user id.']);
        }

        $this->persistConnection($user, $provider, $socialiteUser, $providerUserId);

        $this->selectDefaultCalendarAfterCallback($user);

        return $user;
    }

    /**
     * Writes the provider tokens and profile onto the user's settings,
     * preserving an existing refresh token and prior calendar selection when
     * the same provider account is being re-connected.
     *
     * @throws ValidationException when no refresh token is available (neither
     *                             newly issued nor previously stored).
     */
    private function persistConnection(User $user, string $provider, SocialiteUser $socialiteUser, string $providerUserId): void
    {
        $settings = $this->userSettings($user);
        $existingConnection = $settings->calendar_connection;

        $accessToken = $this->accessToken($socialiteUser);
        $refreshToken = $this->refreshToken($socialiteUser);
        $sameConnection = $existingConnection
            && $existingConnection->provider === $provider
            && $existingConnection->provider_user_id === $providerUserId;

        if (!$refreshToken && $sameConnection) {
            $refreshToken = $existingConnection->refresh_token;
        }

        if (!$refreshToken) {
            throw ValidationException::withMessages(['refresh_token' => 'The calendar provider did not return a refresh token.']);
        }

        $settings->setCalendarConnection(new CalendarConnection([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $socialiteUser->getEmail(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $this->expiresAt($socialiteUser),
            'calendars' => $sameConnection ? $existingConnection->calendars : [],
        ]));

        $user->settings = $settings;
        $user->save();
    }

    /**
     * Fetches the list of calendars the connected provider exposes for the
     * user, refreshing the access token first when it is close to expiry.
     *
     * Each calendar entry is augmented with a `selected` flag indicating
     * whether it is currently part of the user's saved selection.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException when the provider request fails.
     */
    public function availableCalendars(User $user): array
    {
        $connection = $this->connectionOrFail($user);
        $availableCalendars = $this->availableProviderCalendars($user, $connection);

        $selectedIds = collect($connection->calendars)
            ->pluck('calendar_id')
            ->filter()
            ->values()
            ->all();

        return array_map(
            fn (array $calendar): array => $calendar + [
                'selected' => in_array($calendar['calendar_id'], $selectedIds, true),
            ],
            $availableCalendars
        );
    }

    /**
     * @return array<int, array{calendar_id: string, name?: string, primary?: bool, writable?: bool}>
     */
    private function availableProviderCalendars(User $user, CalendarConnection $connection): array
    {
        $connection = $this->freshConnection($user, $connection);
        $provider = (string) $connection->provider;
        $url = $this->calendarEndpoint($provider);
        $query = [];
        $calendars = [];

        do {
            $response = Http::withToken((string) $connection->access_token)
                ->timeout(self::PROVIDER_HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($url, $query);

            if ($response->failed()) {
                throw ValidationException::withMessages(['calendar_connection' => 'Unable to load calendars from the provider.']);
            }

            $body = $response->json();

            $calendars = [
                ...$calendars,
                ...match ($provider) {
                    CalendarConnection::PROVIDER_GOOGLE => $body['items'] ?? [],
                    CalendarConnection::PROVIDER_MICROSOFT => $body['value'] ?? [],
                    default => [],
                },
            ];

            $nextPageToken = $provider === CalendarConnection::PROVIDER_GOOGLE
                ? $body['nextPageToken'] ?? null
                : null;
            $nextLink = $provider === CalendarConnection::PROVIDER_MICROSOFT
                ? $body['@odata.nextLink'] ?? null
                : null;

            if ($nextPageToken) {
                $url = $this->calendarEndpoint($provider);
                $query = ['pageToken' => (string) $nextPageToken];
            } elseif ($nextLink) {
                $url = (string) $nextLink;
                $query = [];
            } else {
                $url = null;
                $query = [];
            }
        } while ($url !== null);

        $normalizedConnection = new CalendarConnection([
            'provider' => $connection->provider,
            'provider_user_id' => $connection->provider_user_id,
            'calendars' => $calendars,
        ]);

        return $normalizedConnection->calendars;
    }

    /**
     * Returns normalized events across every selected calendar within the
     * given time window, sorted by start time ascending.
     *
     * Refreshes the access token if needed, walks all paginated provider
     * responses, and flattens Google and Microsoft event shapes into a
     * consistent payload.
     *
     * @param string $from ISO-8601 / parseable start of the range (UTC)
     * @param string $to   ISO-8601 / parseable end of the range (UTC)
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(User $user, string $from, string $to): array
    {
        $connection = $this->connectionOrFail($user);

        if ($connection->calendars === []) {
            return [];
        }

        $connection = $this->freshConnection($user, $connection);
        [$from, $to] = $this->formatRangeDateTimes($from, $to);
        $events = [];

        foreach ($connection->calendars as $calendar) {
            $events = [
                ...$events,
                ...match ($connection->provider) {
                    CalendarConnection::PROVIDER_GOOGLE => $this->googleEvents($user, $connection, $calendar, $from, $to),
                    CalendarConnection::PROVIDER_MICROSOFT => $this->microsoftEvents($user, $connection, $calendar, $from, $to),
                    default => [],
                },
            ];
        }

        usort($events, fn (array $first, array $second): int => strcmp((string) $first['start'], (string) $second['start']));

        return $this->withoutConvertedEvents($user, $events);
    }

    /**
     * Removes events already converted into active tasks by this user.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function withoutConvertedEvents(User $user, array $events): array
    {
        $eventIds = collect($events)
            ->pluck('calendar_event_id')
            ->filter(fn (mixed $eventId): bool => is_string($eventId) && $eventId !== '')
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return $events;
        }

        $convertedEventIds = Task::query()
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereIn('meta->calendar_event_id', $eventIds->all())
            ->get(['meta'])
            ->map(fn (Task $task): ?string => $task->meta?->calendar_event_id)
            ->filter()
            ->flip();

        if ($convertedEventIds->isEmpty()) {
            return $events;
        }

        return collect($events)
            ->reject(fn (array $event): bool => $convertedEventIds->has((string) ($event['calendar_event_id'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * Persists the user's calendar selection.
     *
     * Validates that every requested calendar id is still available on the
     * provider, then stores a minimal projection (id, name, primary,
     * writable) on the connection.
     *
     * @param array<int, string> $calendarIds
     *
     * @throws ValidationException when one or more ids are no longer
     *                             available on the provider.
     */
    public function updateCalendars(User $user, array $calendarIds): CalendarConnection
    {
        $calendarIds = array_values(array_unique(array_map('strval', $calendarIds)));
        $connection = $this->connectionOrFail($user);
        $availableCalendars = collect($this->availableProviderCalendars($user, $connection))->keyBy('calendar_id');
        $missingCalendarIds = array_values(array_diff($calendarIds, $availableCalendars->keys()->all()));

        if ($missingCalendarIds) {
            throw ValidationException::withMessages([
                'calendar_ids' => 'One or more selected calendars are no longer available.',
            ]);
        }

        $connection->calendars = collect($calendarIds)
            ->map(fn (string $calendarId): array => Arr::only((array) $availableCalendars->get($calendarId), [
                'calendar_id',
                'name',
                'primary',
                'writable',
            ]))
            ->values()
            ->all();

        $settings = $this->userSettings($user);
        $settings->setCalendarConnection($connection);

        $user->settings = $settings;
        $user->save();

        return $connection;
    }

    /**
     * Removes the calendar connection (tokens and selected calendars) from
     * the user's settings. Does not revoke tokens at the provider.
     */
    public function disconnect(User $user): void
    {
        $settings = $this->userSettings($user);
        $settings->clearCalendarConnection();

        $user->settings = $settings;
        $user->save();
    }

    /**
     * Best-effort selection of a sensible default calendar immediately after
     * a successful OAuth callback so the user has something to view without
     * needing to pick one manually.
     *
     * Any failure is reported but swallowed so it cannot break the callback.
     */
    private function selectDefaultCalendarAfterCallback(User $user): void
    {
        try {
            $connection = $this->connectionOrFail($user);

            if ($connection->calendars !== []) {
                return;
            }

            $defaultCalendar = $this->defaultCalendar($this->availableProviderCalendars($user, $connection));

            if (!$defaultCalendar) {
                return;
            }

            $connection = $this->connectionOrFail($user);
            $connection->calendars = [
                Arr::only($defaultCalendar, [
                    'calendar_id',
                    'name',
                    'primary',
                    'writable',
                ]),
            ];

            $settings = $this->userSettings($user);
            $settings->setCalendarConnection($connection);

            $user->settings = $settings;
            $user->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Picks a default calendar from a provider listing.
     *
     * Prefers writable calendars, but falls back to readable calendars so
     * event hydration works for shared/read-only calendar access.
     *
     * @param array<int, array<string, mixed>> $calendars
     * @return array<string, mixed>|null
     */
    private function defaultCalendar(array $calendars): ?array
    {
        foreach ($calendars as $calendar) {
            if (($calendar['primary'] ?? false) === true && ($calendar['writable'] ?? false) === true) {
                return $calendar;
            }
        }

        foreach ($calendars as $calendar) {
            if (($calendar['writable'] ?? false) === true) {
                return $calendar;
            }
        }

        foreach ($calendars as $calendar) {
            if (($calendar['primary'] ?? false) === true) {
                return $calendar;
            }
        }

        return $calendars[0] ?? null;
    }

    /**
     * Returns the absolute OAuth callback URL for the given provider, used
     * both when redirecting and when exchanging the authorization code.
     */
    private function callbackUrl(string $provider): string
    {
        return route('calendar_connection.callback', ['provider' => $provider]);
    }

    /**
     * Guards the supplied provider against the supported allow-list and
     * returns it unchanged on success.
     *
     * @throws ValidationException when the provider is unsupported.
     */
    private function validateProvider(string $provider): string
    {
        if (!in_array($provider, [
            CalendarConnection::PROVIDER_GOOGLE,
            CalendarConnection::PROVIDER_MICROSOFT,
        ], true)) {
            throw ValidationException::withMessages(['provider' => 'Calendar provider is not supported.']);
        }

        return $provider;
    }

    /**
     * Returns the OAuth scopes requested for the given provider — enough to
     * list calendars and read events on Google and Microsoft Graph.
     *
     * @return array<int, string>
     */
    private function scopes(string $provider): array
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
                'https://www.googleapis.com/auth/calendar.events.readonly',
            ],
            CalendarConnection::PROVIDER_MICROSOFT => [
                'openid',
                'email',
                'profile',
                'offline_access',
                'User.Read',
                'Calendars.Read',
            ],
            default => [],
        };
    }

    /**
     * Returns the user's settings as a typed value object, hydrating one
     * from the raw cast payload when the attribute is not already an instance.
     */
    private function userSettings(User $user): UserSettings
    {
        return $user->settings instanceof UserSettings
            ? $user->settings
            : new UserSettings($user->settings);
    }

    /**
     * Returns the user's active CalendarConnection or throws when no
     * provider is connected. Used by every operation that needs to talk
     * to the provider.
     *
     * @throws ValidationException
     */
    private function connectionOrFail(User $user): CalendarConnection
    {
        $connection = $this->userSettings($user)->calendar_connection;

        if (!$connection || !$connection->isConnected()) {
            throw ValidationException::withMessages(['calendar_connection' => 'No calendar connection is configured.']);
        }

        return $connection;
    }

    /**
     * Ensures the connection's access token is still valid, refreshing it
     * via the provider's token endpoint when it has expired (or is about
     * to). The refreshed tokens are persisted back to the user's settings
     * meta so subsequent calls reuse them.
     *
     * @throws ValidationException when no refresh token is available or
     *                             the provider refuses to issue a new
     *                             access token.
     */
    private function freshConnection(User $user, CalendarConnection $connection): CalendarConnection
    {
        if ($connection->access_token && !$connection->tokenExpiresWithin()) {
            return $connection;
        }

        if (!$connection->refresh_token) {
            throw ValidationException::withMessages(['calendar_connection' => 'No calendar refresh token is configured.']);
        }

        $data = $this->refreshTokenResponse($connection);

        $connection->access_token = (string) $data['access_token'];
        $connection->refresh_token = isset($data['refresh_token']) && $data['refresh_token'] !== ''
            ? (string) $data['refresh_token']
            : $connection->refresh_token;
        $connection->expires_at = isset($data['expires_in'])
            ? now()->addSeconds((int) $data['expires_in'])->timestamp
            : $connection->expires_at;

        $settings = $this->userSettings($user);
        $settings->setCalendarConnection($connection);

        $user->settings = $settings;
        $user->save();

        return $connection;
    }

    /**
     * Posts a refresh-token grant to the provider's token endpoint and
     * returns the decoded JSON body.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException when the response is unsuccessful or
     *                             missing an access token.
     */
    private function refreshTokenResponse(CalendarConnection $connection): array
    {
        $provider = (string) $connection->provider;
        $response = Http::asForm()
            ->timeout(self::PROVIDER_HTTP_TIMEOUT_SECONDS)
            ->post($this->tokenEndpoint($provider), $this->refreshTokenPayload($connection));

        if ($response->failed() || !$response->json('access_token')) {
            throw ValidationException::withMessages(['calendar_connection' => 'Unable to refresh the calendar token.']);
        }

        return $response->json();
    }

    /**
     * Builds the form payload sent to the provider's token endpoint when
     * refreshing an access token. Microsoft additionally requires the
     * originally granted scopes to be replayed.
     *
     * @return array<string, string>
     */
    private function refreshTokenPayload(CalendarConnection $connection): array
    {
        $provider = (string) $connection->provider;
        $payload = [
            'client_id' => $this->providerConfig($provider, 'client_id'),
            'client_secret' => $this->providerConfig($provider, 'client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $connection->refresh_token,
        ];

        if ($provider === CalendarConnection::PROVIDER_MICROSOFT) {
            $payload['scope'] = implode(' ', $this->scopes($provider));
        }

        return array_filter($payload, fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * Resolves a provider OAuth credential (client_id / client_secret),
     * preferring the standard `services.*` config slot and falling back to
     * the legacy `ninja.auth.google.*` / `ninja.o365.*` slots used by
     * older installations.
     */
    private function providerConfig(string $provider, string $key): ?string
    {
        $value = config("services.{$provider}.{$key}");

        if ($value) {
            return (string) $value;
        }

        if ($provider === CalendarConnection::PROVIDER_GOOGLE) {
            return config("ninja.auth.google.{$key}") ?: null;
        }

        if ($provider === CalendarConnection::PROVIDER_MICROSOFT) {
            return config("ninja.o365.{$key}") ?: null;
        }

        return null;
    }

    /**
     * Fetches all events for a single Google calendar within the supplied
     * range, expanding recurring series via `singleEvents` and walking
     * every `nextPageToken` until the result set is exhausted.
     *
     * @param array{calendar_id: string, name?: string, primary?: bool, writable?: bool} $calendar
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException when the Google API responds with a
     *                             non-2xx status.
     */
    private function googleEvents(User $user, CalendarConnection $connection, array $calendar, string $from, string $to): array
    {
        $calendarId = $calendar['calendar_id'];
        $pageToken = null;
        $events = [];

        do {
            $query = [
                'maxResults' => 2500,
                'orderBy' => 'startTime',
                'showDeleted' => 'false',
                'singleEvents' => 'true',
                'timeMax' => $to,
                'timeMin' => $from,
            ];

            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken((string) $connection->access_token)
                ->timeout(self::PROVIDER_HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(sprintf(self::GOOGLE_EVENTS_ENDPOINT_FORMAT, rawurlencode($calendarId)), $query);

            if ($response->failed()) {
                throw ValidationException::withMessages(['calendar_connection' => 'Unable to load calendar events from Google.']);
            }

            foreach ($response->json('items') ?? [] as $event) {
                if (is_array($event)) {
                    $events[] = $this->normalizeGoogleEvent($user, $event, $calendar);
                }
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $events;
    }

    /**
     * Fetches all events for a single Microsoft calendar within the
     * supplied range using the `calendarView` endpoint (which expands
     * recurrences for us). Follows `@odata.nextLink` until the result set
     * is exhausted and normalises every event into the shared shape.
     *
     * @param array{calendar_id: string, name?: string, primary?: bool, writable?: bool} $calendar
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException when the Microsoft Graph API responds
     *                             with a non-2xx status.
     */
    private function microsoftEvents(User $user, CalendarConnection $connection, array $calendar, string $from, string $to): array
    {
        $calendarId = $calendar['calendar_id'];
        $events = [];
        $url = sprintf(self::MICROSOFT_CALENDAR_VIEW_ENDPOINT_FORMAT, rawurlencode($calendarId));
        $query = [
            '$top' => 100,
            'endDateTime' => $to,
            'startDateTime' => $from,
        ];

        do {
            $response = Http::withToken((string) $connection->access_token)
                ->timeout(self::PROVIDER_HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->withHeaders(['Prefer' => 'outlook.timezone="UTC", outlook.body-content-type="text"'])
                ->get($url, $query);

            if ($response->failed()) {
                throw ValidationException::withMessages(['calendar_connection' => 'Unable to load calendar events from Microsoft.']);
            }

            $body = $response->json();

            foreach ($body['value'] ?? [] as $event) {
                if (is_array($event)) {
                    $events[] = $this->normalizeMicrosoftEvent($user, $event, $calendar);
                }
            }

            $url = $body['@odata.nextLink'] ?? null;
            $query = [];
        } while ($url);

        return $events;
    }

    /**
     * Reshapes a raw Google Calendar event into the provider-agnostic
     * payload the rest of the application consumes. All-day events are
     * detected via the presence of a `date` (rather than `dateTime`)
     * field.
     *
     * @param array<string, mixed> $event
     * @param array{calendar_id: string, name?: string, primary?: bool, writable?: bool} $calendar
     * @return array<string, mixed>
     */
    private function normalizeGoogleEvent(User $user, array $event, array $calendar): array
    {
        $start = is_array($event['start'] ?? null) ? $event['start'] : [];
        $end = is_array($event['end'] ?? null) ? $event['end'] : [];
        $providerEventId = (string) ($event['id'] ?? sha1(json_encode($event, JSON_THROW_ON_ERROR)));
        $calendarEventId = $this->userScopedEventId($user, CalendarConnection::PROVIDER_GOOGLE, $calendar['calendar_id'], $providerEventId);
        $allDay = isset($start['date']);

        return $this->withoutNullValues([
            'id' => $calendarEventId,
            'calendar_event_id' => $calendarEventId,
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'provider_event_id' => $calendarEventId,
            'calendar_id' => $calendar['calendar_id'],
            'calendar_name' => $calendar['name'] ?? null,
            'title' => (string) ($event['summary'] ?? 'Busy'),
            'description' => $event['description'] ?? null,
            'location' => $event['location'] ?? null,
            'start' => $start['dateTime'] ?? $start['date'] ?? null,
            'end' => $end['dateTime'] ?? $end['date'] ?? null,
            'all_day' => $allDay,
            'status' => $event['status'] ?? null,
            'updated' => $event['updated'] ?? null,
        ]);
    }

    /**
     * Reshapes a raw Microsoft Graph event into the provider-agnostic
     * payload the rest of the application consumes. Cancelled events are
     * surfaced via `status = "cancelled"`; free/busy state otherwise maps
     * from `showAs`.
     *
     * @param array<string, mixed> $event
     * @param array{calendar_id: string, name?: string, primary?: bool, writable?: bool} $calendar
     * @return array<string, mixed>
     */
    private function normalizeMicrosoftEvent(User $user, array $event, array $calendar): array
    {
        $providerEventId = (string) ($event['id'] ?? sha1(json_encode($event, JSON_THROW_ON_ERROR)));
        $calendarEventId = $this->userScopedEventId($user, CalendarConnection::PROVIDER_MICROSOFT, $calendar['calendar_id'], $providerEventId);
        $isCancelled = (bool) ($event['isCancelled'] ?? false);
        $body = is_array($event['body'] ?? null) ? $event['body'] : [];

        return $this->withoutNullValues([
            'id' => $calendarEventId,
            'calendar_event_id' => $calendarEventId,
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'provider_event_id' => $calendarEventId,
            'calendar_id' => $calendar['calendar_id'],
            'calendar_name' => $calendar['name'] ?? null,
            'title' => (string) ($event['subject'] ?? 'Busy'),
            'description' => $body['content'] ?? null,
            'location' => is_array($event['location'] ?? null) ? ($event['location']['displayName'] ?? null) : null,
            'start' => $this->microsoftDateTime($event['start'] ?? null),
            'end' => $this->microsoftDateTime($event['end'] ?? null),
            'all_day' => (bool) ($event['isAllDay'] ?? false),
            'status' => $isCancelled ? 'cancelled' : ($event['showAs'] ?? null),
            'updated' => $event['lastModifiedDateTime'] ?? null,
        ]);
    }

    /**
     * Normalises Microsoft's `{dateTime, timeZone}` shape into an
     * ISO-8601 string, appending a `Z` suffix when the timezone is UTC
     * but the dateTime is missing an explicit offset. Returns null for
     * empty payloads.
     */
    private function microsoftDateTime(mixed $payload): ?string
    {
        if (!is_array($payload) || empty($payload['dateTime'])) {
            return null;
        }

        $dateTime = (string) $payload['dateTime'];
        $timeZone = strtoupper((string) ($payload['timeZone'] ?? ''));

        if (in_array($timeZone, ['UTC', 'ETC/UTC'], true) && !preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $dateTime)) {
            return $dateTime . 'Z';
        }

        return $dateTime;
    }

    /**
     * Parses arbitrary user-supplied date/time strings and renders them as
     * the JSON-friendly UTC representation expected by both provider APIs.
     * Ranges larger than a calendar month view are capped to prevent
     * accidental provider fan-out from oversized requests.
     *
     * @return array{0: string, 1: string}
     */
    private function formatRangeDateTimes(string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->utc();
        $toDate = CarbonImmutable::parse($to)->utc();
        $maxToDate = $fromDate->addDays(self::MAX_EVENT_RANGE_DAYS);

        if ($toDate->gt($maxToDate)) {
            $toDate = $maxToDate;
        }

        return [$fromDate->toJSON(), $toDate->toJSON()];
    }

    /**
     * Builds a stable, provider-prefixed event id of the form
     * `{provider}:{sha1(calendarId)}:{eventId}` so events stay uniquely
     * addressable across calendars and providers without leaking the raw
     * calendar id.
     */
    private function compoundEventId(string $provider, string $calendarId, string $eventId): string
    {
        return $provider . ':' . sha1($calendarId) . ':' . $eventId;
    }

    /**
     * Builds the application-level calendar event id used for duplicate
     * protection: one conversion per user per provider calendar event.
     */
    private function userScopedEventId(User $user, string $provider, string $calendarId, string $eventId): string
    {
        return $user->id . ':' . $this->compoundEventId($provider, $calendarId, $eventId);
    }

    /**
     * Strips null and empty-string values from an event payload so the
     * client only receives keys that actually carry data.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutNullValues(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Returns the provider-specific endpoint used to list the user's
     * calendars.
     *
     * @throws ValidationException when the provider is unsupported.
     */
    private function calendarEndpoint(string $provider): string
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => self::GOOGLE_CALENDARS_ENDPOINT,
            CalendarConnection::PROVIDER_MICROSOFT => self::MICROSOFT_CALENDARS_ENDPOINT,
            default => throw ValidationException::withMessages(['provider' => 'Calendar provider is not supported.']),
        };
    }

    /**
     * Returns the provider-specific OAuth token endpoint used when
     * refreshing access tokens.
     *
     * @throws ValidationException when the provider is unsupported.
     */
    private function tokenEndpoint(string $provider): string
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => self::GOOGLE_TOKEN_ENDPOINT,
            CalendarConnection::PROVIDER_MICROSOFT => self::MICROSOFT_TOKEN_ENDPOINT,
            default => throw ValidationException::withMessages(['provider' => 'Calendar provider is not supported.']),
        };
    }

    /**
     * Extracts the access token from the Socialite user, falling back to
     * the raw token response body for drivers that do not populate the
     * standard `token` property.
     */
    private function accessToken(SocialiteUser $socialiteUser): ?string
    {
        if (isset($socialiteUser->token) && $socialiteUser->token !== '') {
            return (string) $socialiteUser->token;
        }

        return $this->accessTokenResponseValue($socialiteUser, 'access_token');
    }

    /**
     * Extracts the refresh token from the Socialite user, falling back to
     * the raw token response body. May legitimately return null on
     * providers that have already issued one earlier (e.g. Google without
     * `prompt=consent`).
     */
    private function refreshToken(SocialiteUser $socialiteUser): ?string
    {
        if (isset($socialiteUser->refreshToken) && $socialiteUser->refreshToken !== '') {
            return (string) $socialiteUser->refreshToken;
        }

        return $this->accessTokenResponseValue($socialiteUser, 'refresh_token');
    }

    /**
     * Computes the absolute unix timestamp at which the freshly issued
     * access token will expire, derived from the provider's `expires_in`
     * value. Returns null when the provider did not advertise a TTL.
     */
    private function expiresAt(SocialiteUser $socialiteUser): ?int
    {
        $expiresIn = null;

        if (isset($socialiteUser->expiresIn) && $socialiteUser->expiresIn !== '') {
            $expiresIn = (int) $socialiteUser->expiresIn;
        } elseif ($value = $this->accessTokenResponseValue($socialiteUser, 'expires_in')) {
            $expiresIn = (int) $value;
        }

        return $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;
    }

    /**
     * Safely pulls a value out of the Socialite user's raw access-token
     * response body, returning null when the field is absent or empty.
     */
    private function accessTokenResponseValue(SocialiteUser $socialiteUser, string $key): ?string
    {
        if (!isset($socialiteUser->accessTokenResponseBody) || !is_array($socialiteUser->accessTokenResponseBody)) {
            return null;
        }

        $value = $socialiteUser->accessTokenResponseBody[$key] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
