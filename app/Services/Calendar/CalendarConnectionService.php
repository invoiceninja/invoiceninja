<?php

namespace App\Services\Calendar;

use App\DataMapper\Referral\CalendarConnection;
use App\DataMapper\Referral\ReferralMeta;
use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class CalendarConnectionService
{
    public const STATE_CACHE_PREFIX = 'calendar_connection:state:';

    private const GOOGLE_CALENDARS_ENDPOINT = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    private const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const MICROSOFT_CALENDARS_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/calendars';

    private const MICROSOFT_TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    /**
     * @return array<string, mixed>
     */
    public function show(User $user): array
    {
        $connection = $this->referralMeta($user)->calendar_connection;

        return [
            'calendar_connection' => $connection?->toArray(),
        ];
    }

    public function authorizeUrl(User $user, string $provider): string
    {
        $provider = $this->validateProvider($provider);
        $state = Str::random(64);

        Cache::put(self::STATE_CACHE_PREFIX . $state, [
            'database' => config('database.default'),
            'provider' => $provider,
            'user_id' => $user->id,
        ], now()->addMinutes(10));

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

        $url = $driver->redirect()->getTargetUrl();
        $separator = parse_url($url, PHP_URL_QUERY) ? '&' : '?';

        return $url . $separator . http_build_query(['state' => $state]);
    }

    public function handleCallback(string $provider, ?string $state): User
    {
        $provider = $this->validateProvider($provider);

        if (!$state) {
            throw ValidationException::withMessages(['state' => 'Missing calendar connection state.']);
        }

        $stateContext = Cache::pull(self::STATE_CACHE_PREFIX . $state);

        if (!is_array($stateContext)
            || ($stateContext['provider'] ?? null) !== $provider
            || empty($stateContext['user_id'])) {
            throw ValidationException::withMessages(['state' => 'Invalid calendar connection state.']);
        }

        if (isset($stateContext['database']) && is_string($stateContext['database'])) {
            MultiDB::setDB($stateContext['database']);
        }

        $user = User::query()->findOrFail((int) $stateContext['user_id']);

        $socialiteUser = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider))
            ->user();

        $providerUserId = $socialiteUser->getId();

        if (!$providerUserId) {
            throw ValidationException::withMessages(['provider_user_id' => 'The calendar provider did not return a user id.']);
        }

        $meta = $this->referralMeta($user);
        $existingConnection = $meta->calendar_connection;

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

        $meta->setCalendarConnection(new CalendarConnection([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $socialiteUser->getEmail(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $this->expiresAt($socialiteUser),
            'calendars' => $sameConnection ? $existingConnection->calendars : [],
        ]));

        $user->referral_meta = $meta;
        $user->save();

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableCalendars(User $user): array
    {
        $connection = $this->connectionOrFail($user);
        $connection = $this->freshConnection($user, $connection);

        $response = Http::withToken((string) $connection->access_token)
            ->acceptJson()
            ->get($this->calendarEndpoint((string) $connection->provider));

        if ($response->failed()) {
            throw ValidationException::withMessages(['calendar_connection' => 'Unable to load calendars from the provider.']);
        }

        $calendars = match ($connection->provider) {
            CalendarConnection::PROVIDER_GOOGLE => $response->json('items') ?? [],
            CalendarConnection::PROVIDER_MICROSOFT => $response->json('value') ?? [],
            default => [],
        };

        $normalizedConnection = new CalendarConnection([
            'provider' => $connection->provider,
            'provider_user_id' => $connection->provider_user_id,
            'calendars' => $calendars,
        ]);

        $selectedIds = collect($connection->calendars)
            ->pluck('calendar_id')
            ->filter()
            ->values()
            ->all();

        return array_map(
            fn (array $calendar): array => $calendar + [
                'selected' => in_array($calendar['calendar_id'], $selectedIds, true),
            ],
            $normalizedConnection->calendars
        );
    }

    /**
     * @param array<int, string> $calendarIds
     */
    public function updateCalendars(User $user, array $calendarIds): CalendarConnection
    {
        $calendarIds = array_values(array_unique(array_map('strval', $calendarIds)));
        $availableCalendars = collect($this->availableCalendars($user))->keyBy('calendar_id');
        $missingCalendarIds = array_values(array_diff($calendarIds, $availableCalendars->keys()->all()));

        if ($missingCalendarIds) {
            throw ValidationException::withMessages([
                'calendar_ids' => 'One or more selected calendars are no longer available.',
            ]);
        }

        $connection = $this->connectionOrFail($user);
        $connection->calendars = collect($calendarIds)
            ->map(fn (string $calendarId): array => Arr::only((array) $availableCalendars->get($calendarId), [
                'calendar_id',
                'name',
                'primary',
                'writable',
            ]))
            ->values()
            ->all();

        $meta = $this->referralMeta($user);
        $meta->setCalendarConnection($connection);

        $user->referral_meta = $meta;
        $user->save();

        return $connection;
    }

    public function disconnect(User $user): void
    {
        $meta = $this->referralMeta($user);
        $meta->clearCalendarConnection();

        $user->referral_meta = $meta;
        $user->save();
    }

    private function callbackUrl(string $provider): string
    {
        return route('calendar_connection.callback', ['provider' => $provider]);
    }

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
                'https://www.googleapis.com/auth/calendar.events',
            ],
            CalendarConnection::PROVIDER_MICROSOFT => [
                'openid',
                'email',
                'profile',
                'offline_access',
                'User.Read',
                'Calendars.ReadWrite',
            ],
            default => [],
        };
    }

    private function referralMeta(User $user): ReferralMeta
    {
        return $user->referral_meta instanceof ReferralMeta
            ? $user->referral_meta
            : new ReferralMeta($user->referral_meta);
    }

    private function connectionOrFail(User $user): CalendarConnection
    {
        $connection = $this->referralMeta($user)->calendar_connection;

        if (!$connection || !$connection->isConnected()) {
            throw ValidationException::withMessages(['calendar_connection' => 'No calendar connection is configured.']);
        }

        return $connection;
    }

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

        $meta = $this->referralMeta($user);
        $meta->setCalendarConnection($connection);

        $user->referral_meta = $meta;
        $user->save();

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshTokenResponse(CalendarConnection $connection): array
    {
        $provider = (string) $connection->provider;
        $response = Http::asForm()->post($this->tokenEndpoint($provider), $this->refreshTokenPayload($connection));

        if ($response->failed() || !$response->json('access_token')) {
            throw ValidationException::withMessages(['calendar_connection' => 'Unable to refresh the calendar token.']);
        }

        return $response->json();
    }

    /**
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

    private function calendarEndpoint(string $provider): string
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => self::GOOGLE_CALENDARS_ENDPOINT,
            CalendarConnection::PROVIDER_MICROSOFT => self::MICROSOFT_CALENDARS_ENDPOINT,
            default => throw ValidationException::withMessages(['provider' => 'Calendar provider is not supported.']),
        };
    }

    private function tokenEndpoint(string $provider): string
    {
        return match ($provider) {
            CalendarConnection::PROVIDER_GOOGLE => self::GOOGLE_TOKEN_ENDPOINT,
            CalendarConnection::PROVIDER_MICROSOFT => self::MICROSOFT_TOKEN_ENDPOINT,
            default => throw ValidationException::withMessages(['provider' => 'Calendar provider is not supported.']),
        };
    }

    private function accessToken(SocialiteUser $socialiteUser): ?string
    {
        if (isset($socialiteUser->token) && $socialiteUser->token !== '') {
            return (string) $socialiteUser->token;
        }

        return $this->accessTokenResponseValue($socialiteUser, 'access_token');
    }

    private function refreshToken(SocialiteUser $socialiteUser): ?string
    {
        if (isset($socialiteUser->refreshToken) && $socialiteUser->refreshToken !== '') {
            return (string) $socialiteUser->refreshToken;
        }

        return $this->accessTokenResponseValue($socialiteUser, 'refresh_token');
    }

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

    private function accessTokenResponseValue(SocialiteUser $socialiteUser, string $key): ?string
    {
        if (!isset($socialiteUser->accessTokenResponseBody) || !is_array($socialiteUser->accessTokenResponseBody)) {
            return null;
        }

        $value = $socialiteUser->accessTokenResponseBody[$key] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
