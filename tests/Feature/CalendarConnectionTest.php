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

namespace Tests\Feature;

use App\DataMapper\Referral\CalendarConnection;
use App\DataMapper\Referral\ReferralMeta;
use App\DataMapper\UserSettings;
use App\DataMapper\TaskMeta;
use App\Factory\TaskFactory;
use App\Services\Calendar\CalendarConnectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\MockAccountData;
use Tests\TestCase;

class CalendarConnectionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://api.test',
            'ninja.app_url' => 'https://api.test',
            'ninja.react_url' => 'https://react.test',
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.microsoft.client_id' => 'microsoft-client-id',
            'services.microsoft.client_secret' => 'microsoft-client-secret',
        ]);
    }

    public function testAuthorizeHashRedirectsToGoogleWithOpaqueState(): void
    {
        $hash = $this->cacheOneTimeToken('calendar_google');

        $response = $this->get(route('calendar_connection.authorize', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'hash' => $hash,
        ]));

        $response->assertStatus(302);

        $url = $response->headers->get('Location');
        $query = $this->parseUrlQuery($url);

        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('google-client-id', $query['client_id']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent select_account', $query['prompt']);
        $this->assertSame('true', $query['include_granted_scopes']);

        $scopes = explode(' ', $query['scope']);

        $this->assertContains('https://www.googleapis.com/auth/calendar.events.readonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/calendar.calendarlist.readonly', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/calendar.events', $scopes);
        $this->assertNotEmpty($query['state']);

        $stateContext = Cache::get(CalendarConnectionService::STATE_CACHE_PREFIX . $query['state']);

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $stateContext['provider']);
        $this->assertSame($this->user->id, $stateContext['user_id']);
        $this->assertSame(config('database.default'), $stateContext['database']);
    }

    public function testAuthorizeMicrosoftRequestsReadOnlyCalendarScope(): void
    {
        $hash = $this->cacheOneTimeToken('calendar_microsoft');

        $response = $this->get(route('calendar_connection.authorize', [
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'hash' => $hash,
        ]));

        $response->assertStatus(302);

        $query = $this->parseUrlQuery($response->headers->get('Location'));
        $scopes = explode(' ', $query['scope']);

        $this->assertContains('Calendars.Read', $scopes);
        $this->assertNotContains('Calendars.ReadWrite', $scopes);
    }

    public function testAuthorizeRejectsHashWithUnrelatedContext(): void
    {
        $hash = $this->cacheOneTimeToken('quickbooks');

        $this->get(route('calendar_connection.authorize', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'hash' => $hash,
        ]))->assertStatus(403);
    }

    public function testAuthorizeRejectsHashIssuedForOtherProvider(): void
    {
        $hash = $this->cacheOneTimeToken('calendar_google');

        $this->get(route('calendar_connection.authorize', [
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'hash' => $hash,
        ]))->assertStatus(403);
    }

    public function testAuthorizeRejectsUnknownHash(): void
    {
        $this->get(route('calendar_connection.authorize', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'hash' => Str::random(64),
        ]))->assertStatus(403);
    }

    public function testCallbackBouncepointStoresHandoffAndForwardsOnlyTheHandoffToReact(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => 'opaque-state',
            'code' => 'oauth-code',
        ]));

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://react.test/#/calendar_connection/complete?', $location);
        $query = $this->parseUrlFragmentParams($location);
        $this->assertSame('pending', $query['calendar_connection']);
        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $query['provider']);
        $this->assertArrayHasKey('handoff', $query);
        $this->assertArrayNotHasKey('state', $query);
        $this->assertArrayNotHasKey('code', $query);

        $handoffContext = Cache::get(CalendarConnectionService::HANDOFF_CACHE_PREFIX . $query['handoff']);

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $handoffContext['provider']);
        $this->assertSame('opaque-state', $handoffContext['state']);
        $this->assertSame('oauth-code', $handoffContext['code']);
    }

    public function testCallbackBouncepointReturnsDeniedOnProviderError(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'error' => 'access_denied',
        ]));

        $query = $this->parseUrlFragmentParams($response->headers->get('Location'));
        $this->assertSame('denied', $query['calendar_connection']);
        $this->assertArrayNotHasKey('state', $query);
        $this->assertArrayNotHasKey('code', $query);
        $this->assertArrayNotHasKey('handoff', $query);
    }

    public function testCallbackBouncepointReturnsFailedWhenStateOrCodeMissing(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => 'present',
        ]));

        $query = $this->parseUrlFragmentParams($response->headers->get('Location'));
        $this->assertSame('failed', $query['calendar_connection']);
        $this->assertArrayNotHasKey('handoff', $query);
    }

    public function testCompleteStoresSingleCalendarConnectionAndAutoSelectsGooglePrimaryCalendar(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'readonly', 'summary' => 'Read Only', 'primary' => true, 'accessRole' => 'reader'],
                    ['id' => 'primary', 'summary' => 'Primary', 'primary' => true, 'accessRole' => 'owner'],
                    ['id' => 'work', 'summary' => 'Work', 'primary' => false, 'accessRole' => 'writer'],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.calendar_connection.connected', true);
        $response->assertJsonPath('data.calendar_connection.provider', CalendarConnection::PROVIDER_GOOGLE);
        $response->assertJsonPath('data.calendar_connection.provider_user_id', 'google-sub-1');
        $response->assertJsonPath('data.calendar_connection.email', 'calendar@example.com');
        $response->assertJsonPath('data.calendar_connection.calendars.0.calendar_id', 'primary');
        $response->assertJsonPath('data.calendar_connection.calendars.0.name', 'Primary');
        $this->assertArrayNotHasKey('status', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('access_token', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('refresh_token', $response->json('data.calendar_connection'));
        $this->assertFalse(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertInstanceOf(CalendarConnection::class, $connection);
        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $connection->provider);
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame('calendar@example.com', $connection->email);
        $this->assertSame('google-access-token', $connection->access_token);
        $this->assertSame('google-refresh-token', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
        $this->assertSame('Primary', $connection->calendars[0]['name']);
        $this->assertTrue($connection->calendars[0]['primary']);
        $this->assertTrue($connection->calendars[0]['writable']);
    }

    public function testShowReturnsDisconnectedCalendarConnectionShape(): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.show'));

        $response->assertOk();
        $response->assertJsonPath('data.calendar_connection', null);
    }

    public function testShowReturnsConnectedCalendarConnectionShape(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.show'));

        $response->assertOk();
        $response->assertJsonPath('data.calendar_connection.connected', true);
        $response->assertJsonPath('data.calendar_connection.provider', CalendarConnection::PROVIDER_GOOGLE);
        $response->assertJsonPath('data.calendar_connection.provider_user_id', 'google-sub-1');
        $response->assertJsonPath('data.calendar_connection.email', 'calendar@example.com');
        $response->assertJsonPath('data.calendar_connection.calendars.0.calendar_id', 'primary');
        $this->assertArrayNotHasKey('status', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('access_token', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('refresh_token', $response->json('data.calendar_connection'));
    }

    public function testCompleteThenEventsReturnsAutoSelectedGoogleCalendarEvents(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Primary', 'primary' => true, 'accessRole' => 'owner'],
                ],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event-after-auth',
                        'summary' => 'Post Auth Sync',
                        'start' => ['dateTime' => '2026-04-01T09:00:00.000Z'],
                        'end' => ['dateTime' => '2026-04-01T09:30:00.000Z'],
                    ],
                ],
            ]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();
        $this->assertSame('primary', $this->user->settings->calendar_connection->calendars[0]['calendar_id']);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.events.0.title', 'Post Auth Sync');
        $response->assertJsonPath('data.events.0.calendar_id', 'primary');
        $response->assertJsonPath('data.events.0.calendar_name', 'Primary');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events'));
    }

    public function testMicrosoftCompleteThenEventsReturnsAutoSelectedCalendarEvents(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_MICROSOFT);

        Socialite::fake(CalendarConnection::PROVIDER_MICROSOFT, (new SocialiteUser())->map([
            'id' => 'microsoft-user-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('microsoft-access-token')
            ->setRefreshToken('microsoft-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://graph.microsoft.com/v1.0/me/calendars' => Http::response([
                'value' => [
                    ['id' => 'default-calendar', 'name' => 'Calendar', 'isDefaultCalendar' => true, 'canEdit' => true],
                ],
            ]),
            'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView*' => Http::response([
                'value' => [
                    [
                        'id' => 'graph-event-after-auth',
                        'subject' => 'Post Auth Planning',
                        'showAs' => 'busy',
                        'isAllDay' => false,
                        'isCancelled' => false,
                        'body' => ['content' => 'Plan the work'],
                        'location' => ['displayName' => 'Teams'],
                        'start' => ['dateTime' => '2026-04-01T10:00:00.0000000', 'timeZone' => 'UTC'],
                        'end' => ['dateTime' => '2026-04-01T10:30:00.0000000', 'timeZone' => 'UTC'],
                    ],
                ],
            ]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_MICROSOFT]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();
        $this->assertSame('default-calendar', $this->user->settings->calendar_connection->calendars[0]['calendar_id']);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.events.0.title', 'Post Auth Planning');
        $response->assertJsonPath('data.events.0.calendar_id', 'default-calendar');
        $response->assertJsonPath('data.events.0.calendar_name', 'Calendar');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView'));
    }

    public function testCompleteConsumesCallbackHandoffAndConnects(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);
        $handoff = $this->cacheCalendarHandoff(CalendarConnection::PROVIDER_GOOGLE, $state);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Primary', 'primary' => true, 'accessRole' => 'owner'],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'handoff' => $handoff,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.calendar_connection.connected', true);
        $response->assertJsonPath('data.calendar_connection.provider', CalendarConnection::PROVIDER_GOOGLE);
        $response->assertJsonPath('data.calendar_connection.provider_user_id', 'google-sub-1');
        $response->assertJsonPath('data.calendar_connection.email', 'calendar@example.com');
        $response->assertJsonPath('data.calendar_connection.calendars.0.calendar_id', 'primary');
        $this->assertArrayNotHasKey('status', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('access_token', $response->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('refresh_token', $response->json('data.calendar_connection'));
        $this->assertFalse(Cache::has(CalendarConnectionService::HANDOFF_CACHE_PREFIX . $handoff));
        $this->assertFalse(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertInstanceOf(CalendarConnection::class, $connection);
        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $connection->provider);
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
    }

    public function testCompleteRejectsReplayedCallbackHandoff(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);
        $handoff = $this->cacheCalendarHandoff(CalendarConnection::PROVIDER_GOOGLE, $state);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'handoff' => $handoff,
            ])->assertOk();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'handoff' => $handoff,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['handoff']);
    }

    public function testCompleteRejectsHandoffIssuedForOtherProvider(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);
        $handoff = $this->cacheCalendarHandoff(CalendarConnection::PROVIDER_GOOGLE, $state);

        Socialite::shouldReceive('driver')->never();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_MICROSOFT]), [
                'handoff' => $handoff,
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['handoff']);

        $this->assertFalse(Cache::has(CalendarConnectionService::HANDOFF_CACHE_PREFIX . $handoff));
        $this->assertTrue(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));
    }

    public function testCompletePreservesExistingRefreshTokenAndSelectedCalendarsWhenProviderOmitsRefreshToken(): void
    {
        $this->user->referral_meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
        ]);
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'old-access-token',
                'refresh_token' => 'existing-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Http::fake();

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('new-access-token')
            ->setExpiresIn(3600));

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertSame('new-access-token', $connection->access_token);
        $this->assertSame('existing-refresh-token', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
        Http::assertNothingSent();
        $this->assertSame(1, $this->user->referral_meta->free);
        $this->assertSame(2, $this->user->referral_meta->pro);
        $this->assertSame(3, $this->user->referral_meta->enterprise);
    }

    public function testMicrosoftCompleteAutoSelectsDefaultWritableCalendar(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_MICROSOFT);

        Socialite::fake(CalendarConnection::PROVIDER_MICROSOFT, (new SocialiteUser())->map([
            'id' => 'microsoft-user-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('microsoft-access-token')
            ->setRefreshToken('microsoft-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://graph.microsoft.com/v1.0/me/calendars' => Http::response([
                'value' => [
                    ['id' => 'other-calendar', 'name' => 'Other', 'isDefaultCalendar' => false, 'canEdit' => true],
                    ['id' => 'default-calendar', 'name' => 'Calendar', 'isDefaultCalendar' => true, 'canEdit' => true],
                ],
            ]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_MICROSOFT]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertSame(CalendarConnection::PROVIDER_MICROSOFT, $connection->provider);
        $this->assertSame('default-calendar', $connection->calendars[0]['calendar_id']);
        $this->assertSame('Calendar', $connection->calendars[0]['name']);
        $this->assertTrue($connection->calendars[0]['primary']);
        $this->assertTrue($connection->calendars[0]['writable']);
    }

    public function testCompleteStillConnectsWhenCalendarLookupFails(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([], 500),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $connection->provider);
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame([], $connection->calendars);
    }

    public function testCompleteAutoSelectsReadOnlyCalendarForEventHydration(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Primary', 'primary' => true, 'accessRole' => 'reader'],
                    ['id' => 'readonly', 'summary' => 'Read Only', 'primary' => false, 'accessRole' => 'reader'],
                ],
            ]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $connection = $this->user->settings->calendar_connection;

        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
        $this->assertSame('Primary', $connection->calendars[0]['name']);
        $this->assertTrue($connection->calendars[0]['primary']);
        $this->assertFalse($connection->calendars[0]['writable']);
    }

    public function testCompleteAutoSelectsCalendarFromPaginatedProviderList(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/users/me/calendarList')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                if (($query['pageToken'] ?? null) === 'page-2') {
                    return Http::response([
                        'items' => [
                            ['id' => 'work', 'summary' => 'Work', 'primary' => false, 'accessRole' => 'writer'],
                        ],
                    ]);
                }

                return Http::response([
                    'items' => [
                        ['id' => 'readonly', 'summary' => 'Read Only', 'primary' => false, 'accessRole' => 'reader'],
                    ],
                    'nextPageToken' => 'page-2',
                ]);
            }

            return Http::response([], 404);
        });

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $this->assertSame('work', $this->user->settings->calendar_connection->calendars[0]['calendar_id']);
        $this->assertSame('Work', $this->user->settings->calendar_connection->calendars[0]['name']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'pageToken=page-2'));
    }

    public function testMicrosoftCompleteAutoSelectsCalendarFromPaginatedProviderList(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_MICROSOFT);

        Socialite::fake(CalendarConnection::PROVIDER_MICROSOFT, (new SocialiteUser())->map([
            'id' => 'microsoft-user-1',
            'name' => 'Calendar User',
            'email' => 'calendar@example.com',
        ])->setToken('microsoft-access-token')
            ->setRefreshToken('microsoft-refresh-token')
            ->setExpiresIn(3600));

        Http::fake(function ($request) {
            if ($request->url() === 'https://graph.microsoft.com/v1.0/me/calendars') {
                return Http::response([
                    'value' => [
                        ['id' => 'readonly', 'name' => 'Read Only', 'isDefaultCalendar' => false, 'canEdit' => false],
                    ],
                    '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendars/page-2',
                ]);
            }

            if ($request->url() === 'https://graph.microsoft.com/v1.0/me/calendars/page-2') {
                return Http::response([
                    'value' => [
                        ['id' => 'work', 'name' => 'Work', 'isDefaultCalendar' => false, 'canEdit' => true],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_MICROSOFT]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->user->refresh();

        $this->assertSame('work', $this->user->settings->calendar_connection->calendars[0]['calendar_id']);
        $this->assertSame('Work', $this->user->settings->calendar_connection->calendars[0]['name']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me/calendars/page-2');
    }

    public function testCompleteFailsWhenStateIsMissingFromCache(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => str_repeat('x', 64),
                'code' => 'oauth-code',
            ])->assertStatus(422);

        $this->user->refresh();
        $this->assertNull($this->user->settings->calendar_connection);
    }

    public function testCompleteRejectsCrossAccountStateInjection(): void
    {
        // Simulate Sally (another user) having initiated the OAuth flow:
        // the cached state names Sally, but Bob (the authenticated caller)
        // is the one POSTing /complete with that state and a code intended
        // for him. The fix must reject this regardless of how the code was
        // obtained.
        $sallyUserId = $this->user->id + 9_999_999;

        $state = Str::random(64);
        Cache::put(CalendarConnectionService::STATE_CACHE_PREFIX . $state, [
            'database' => config('database.default'),
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'user_id' => $sallyUserId,
        ], now()->addMinutes(10));

        Socialite::shouldReceive('driver')->never();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertStatus(422);

        $this->assertFalse(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));

        $this->user->refresh();
        $this->assertNull($this->user->settings->calendar_connection);
    }

    public function testCompleteRejectsReplayedState(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::fake(CalendarConnection::PROVIDER_GOOGLE, (new SocialiteUser())->map([
            'id' => 'google-sub-1',
            'email' => 'calendar@example.com',
        ])->setToken('google-access-token')
            ->setRefreshToken('google-refresh-token')
            ->setExpiresIn(3600));

        Http::fake();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertOk();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertStatus(422);
    }

    public function testCompleteRejectsStateIssuedForOtherProvider(): void
    {
        $state = $this->cacheCalendarState(CalendarConnection::PROVIDER_GOOGLE);

        Socialite::shouldReceive('driver')->never();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_MICROSOFT]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertStatus(422);

        $this->assertFalse(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));
    }

    public function testCompleteRejectsStateFromDifferentTenant(): void
    {
        $state = Str::random(64);
        Cache::put(CalendarConnectionService::STATE_CACHE_PREFIX . $state, [
            'database' => 'db-other',
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'user_id' => $this->user->id,
        ], now()->addMinutes(10));

        Socialite::shouldReceive('driver')->never();

        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => $state,
                'code' => 'oauth-code',
            ])->assertStatus(422);
    }

    public function testCompleteRejectsInvalidStateLength(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.complete', ['provider' => CalendarConnection::PROVIDER_GOOGLE]), [
                'state' => 'too-short',
                'code' => 'oauth-code',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['state']);
    }

    public function testCalendarListKeepsLegacyShapeWhenProviderOmitsName(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'nameless', 'primary' => false, 'accessRole' => 'reader'],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.calendars'));

        $response->assertOk();
        $response->assertJsonPath('data.calendars.0.calendar_id', 'nameless');
        $response->assertJsonPath('data.calendars.0.selected', false);
        $response->assertJsonPath('data.calendars.0.writable', false);
        $this->assertArrayNotHasKey('id', $response->json('data.calendars.0'));
        $this->assertArrayNotHasKey('provider', $response->json('data.calendars.0'));
        $this->assertArrayNotHasKey('name', $response->json('data.calendars.0'));
    }

    public function testCalendarListUpdateAndDisconnectFlow(): void
    {
        $this->user->referral_meta = new ReferralMeta([
            'free' => 4,
            'pro' => 5,
            'enterprise' => 6,
        ]);
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Primary', 'primary' => true, 'accessRole' => 'owner'],
                    ['id' => 'work', 'summary' => 'Work', 'primary' => false, 'accessRole' => 'writer'],
                ],
            ]),
        ]);

        $listResponse = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.calendars'));

        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.calendars.0.calendar_id', 'primary');
        $listResponse->assertJsonPath('data.calendars.0.writable', true);
        $listResponse->assertJsonPath('data.calendars.0.selected', true);
        $listResponse->assertJsonPath('data.calendars.1.calendar_id', 'work');
        $listResponse->assertJsonPath('data.calendars.1.writable', true);
        $listResponse->assertJsonPath('data.calendars.1.selected', false);
        $this->assertArrayNotHasKey('id', $listResponse->json('data.calendars.0'));
        $this->assertArrayNotHasKey('provider', $listResponse->json('data.calendars.0'));

        $updateResponse = $this->withHeaders($this->apiHeaders())
            ->putJson(route('api.calendar_connection.calendars.update'), [
                'calendar_ids' => ['work'],
            ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.calendar_connection.connected', true);
        $updateResponse->assertJsonPath('data.calendar_connection.provider', CalendarConnection::PROVIDER_GOOGLE);
        $updateResponse->assertJsonPath('data.calendar_connection.provider_user_id', 'google-sub-1');
        $updateResponse->assertJsonPath('data.calendar_connection.email', 'calendar@example.com');
        $updateResponse->assertJsonPath('data.calendar_connection.calendars.0.calendar_id', 'work');
        $this->assertArrayNotHasKey('status', $updateResponse->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('access_token', $updateResponse->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('refresh_token', $updateResponse->json('data.calendar_connection'));

        $this->user->refresh();
        $this->assertSame('work', $this->user->settings->calendar_connection->calendars[0]['calendar_id']);

        $deleteResponse = $this->withHeaders($this->apiHeaders())
            ->deleteJson(route('api.calendar_connection.destroy'));

        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('data.calendar_connection', null);

        $this->user->refresh();
        $this->assertNull($this->user->settings->calendar_connection);
        $this->assertSame(4, $this->user->referral_meta->free);
        $this->assertSame(5, $this->user->referral_meta->pro);
        $this->assertSame(6, $this->user->referral_meta->enterprise);
    }


    public function testCalendarProviderHttpRequestsUseThirtySecondTimeout(): void
    {
        /** @var array<string, int|float|null> $timeouts */
        $timeouts = [];

        Http::fake(function ($request, array $options) use (&$timeouts) {
            $url = $request->url();

            if ($url === 'https://login.microsoftonline.com/common/oauth2/v2.0/token') {
                $timeouts['refresh_token'] = $options['timeout'] ?? null;

                return Http::response([
                    'access_token' => 'microsoft-access-token',
                    'refresh_token' => 'new-microsoft-refresh-token',
                    'expires_in' => 3600,
                ]);
            }

            if ($url === 'https://graph.microsoft.com/v1.0/me/calendars') {
                $timeouts['calendar_list'] = $options['timeout'] ?? null;

                return Http::response(['value' => []]);
            }

            if (str_starts_with($url, 'https://www.googleapis.com/calendar/v3/calendars/primary/events')) {
                $timeouts['google_events'] = $options['timeout'] ?? null;

                return Http::response(['items' => []]);
            }

            if (str_starts_with($url, 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView')) {
                $timeouts['microsoft_events'] = $options['timeout'] ?? null;

                return Http::response(['value' => []]);
            }

            return Http::response([], 404);
        });

        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->subMinute()->timestamp,
                'calendars' => [],
            ],
        ]);
        $this->user->save();

        $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.calendars'))
            ->assertOk();

        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-30T23:59:59.999Z',
            ]))
            ->assertOk();

        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'access_token' => 'microsoft-access-token',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'default-calendar', 'name' => 'Calendar', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-30T23:59:59.999Z',
            ]))
            ->assertOk();

        $this->assertSame([
            'refresh_token' => 30,
            'calendar_list' => 30,
            'google_events' => 30,
            'microsoft_events' => 30,
        ], $timeouts);
    }


    public function testGoogleEventsEndpointReturnsSelectedCalendarEventsInRange(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event-1',
                        'summary' => 'Discovery Call',
                        'description' => 'Intro call',
                        'location' => 'Zoom',
                        'status' => 'confirmed',
                        'htmlLink' => 'https://calendar.google.com/event?eid=event-1',
                        'updated' => '2026-04-01T09:30:00.000Z',
                        'start' => ['dateTime' => '2026-04-01T09:00:00.000Z'],
                        'end' => ['dateTime' => '2026-04-01T09:30:00.000Z'],
                    ],
                    [
                        'id' => 'event-2',
                        'summary' => 'Billing Day',
                        'start' => ['date' => '2026-04-02'],
                        'end' => ['date' => '2026-04-03'],
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-03-28T13:00:00.000Z',
                'to' => '2026-05-02T13:59:59.999Z',
            ]));

        $response->assertOk();

        $expectedEventId = $this->user->id . ':' . CalendarConnection::PROVIDER_GOOGLE . ':' . sha1('primary') . ':event-1';

        $response->assertJsonPath('data.events.0.id', $expectedEventId);
        $response->assertJsonPath('data.events.0.calendar_event_id', $expectedEventId);
        $response->assertJsonPath('data.events.0.provider', CalendarConnection::PROVIDER_GOOGLE);
        $response->assertJsonPath('data.events.0.provider_event_id', $expectedEventId);
        $response->assertJsonPath('data.events.0.calendar_id', 'primary');
        $response->assertJsonPath('data.events.0.calendar_name', 'Primary');
        $response->assertJsonPath('data.events.0.title', 'Discovery Call');
        $response->assertJsonPath('data.events.0.start', '2026-04-01T09:00:00.000Z');
        $response->assertJsonPath('data.events.0.end', '2026-04-01T09:30:00.000Z');
        $response->assertJsonPath('data.events.0.all_day', false);
        $this->assertArrayNotHasKey('url', $response->json('data.events.0'));
        $response->assertJsonPath('data.events.1.title', 'Billing Day');
        $response->assertJsonPath('data.events.1.all_day', true);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')
                && $query['timeMin'] === '2026-03-28T13:00:00.000000Z'
                && $query['timeMax'] === '2026-05-02T13:59:59.999000Z'
                && $query['singleEvents'] === 'true'
                && $query['orderBy'] === 'startTime';
        });
    }

    public function testGoogleEventsEndpointFollowsPaginatedEventResults(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                if (($query['pageToken'] ?? null) === 'page-2') {
                    return Http::response([
                        'items' => [
                            [
                                'id' => 'event-2',
                                'summary' => 'Follow Up',
                                'start' => ['dateTime' => '2026-04-01T10:00:00.000Z'],
                                'end' => ['dateTime' => '2026-04-01T10:30:00.000Z'],
                            ],
                        ],
                    ]);
                }

                return Http::response([
                    'items' => [
                        [
                            'id' => 'event-1',
                            'summary' => 'Planning',
                            'start' => ['dateTime' => '2026-04-01T09:00:00.000Z'],
                            'end' => ['dateTime' => '2026-04-01T09:30:00.000Z'],
                        ],
                    ],
                    'nextPageToken' => 'page-2',
                ]);
            }

            return Http::response([], 404);
        });

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.events');
        $response->assertJsonPath('data.events.0.title', 'Planning');
        $response->assertJsonPath('data.events.1.title', 'Follow Up');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'pageToken=page-2'));
    }

    public function testMicrosoftEventsEndpointReturnsSelectedCalendarViewEventsInRange(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'access_token' => 'microsoft-access-token',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'default-calendar', 'name' => 'Calendar', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView*' => Http::response([
                'value' => [
                    [
                        'id' => 'graph-event-1',
                        'subject' => 'Planning',
                        'showAs' => 'busy',
                        'isAllDay' => false,
                        'isCancelled' => false,
                        'webLink' => 'https://outlook.office.com/calendar/item/graph-event-1',
                        'lastModifiedDateTime' => '2026-04-01T08:00:00Z',
                        'body' => ['content' => 'Plan the work'],
                        'location' => ['displayName' => 'Teams'],
                        'start' => ['dateTime' => '2026-04-01T10:00:00.0000000', 'timeZone' => 'UTC'],
                        'end' => ['dateTime' => '2026-04-01T10:30:00.0000000', 'timeZone' => 'UTC'],
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-03-28T13:00:00.000Z',
                'to' => '2026-05-02T13:59:59.999Z',
            ]));

        $response->assertOk();

        $expectedEventId = $this->user->id . ':' . CalendarConnection::PROVIDER_MICROSOFT . ':' . sha1('default-calendar') . ':graph-event-1';

        $response->assertJsonPath('data.events.0.id', $expectedEventId);
        $response->assertJsonPath('data.events.0.calendar_event_id', $expectedEventId);
        $response->assertJsonPath('data.events.0.provider', CalendarConnection::PROVIDER_MICROSOFT);
        $response->assertJsonPath('data.events.0.provider_event_id', $expectedEventId);
        $response->assertJsonPath('data.events.0.calendar_id', 'default-calendar');
        $response->assertJsonPath('data.events.0.title', 'Planning');
        $response->assertJsonPath('data.events.0.description', 'Plan the work');
        $response->assertJsonPath('data.events.0.location', 'Teams');
        $response->assertJsonPath('data.events.0.start', '2026-04-01T10:00:00.0000000Z');
        $response->assertJsonPath('data.events.0.end', '2026-04-01T10:30:00.0000000Z');
        $response->assertJsonPath('data.events.0.all_day', false);
        $response->assertJsonPath('data.events.0.status', 'busy');
        $this->assertArrayNotHasKey('url', $response->json('data.events.0'));

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView')
                && $query['startDateTime'] === '2026-03-28T13:00:00.000000Z'
                && $query['endDateTime'] === '2026-05-02T13:59:59.999000Z';
        });
    }

    public function testMicrosoftEventsEndpointFollowsPaginatedCalendarViewResults(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'access_token' => 'microsoft-access-token',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'default-calendar', 'name' => 'Calendar', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake(function ($request) {
            if ($request->url() === 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView/page-2') {
                return Http::response([
                    'value' => [
                        [
                            'id' => 'graph-event-2',
                            'subject' => 'Follow Up',
                            'showAs' => 'busy',
                            'isAllDay' => false,
                            'isCancelled' => false,
                            'start' => ['dateTime' => '2026-04-01T11:00:00.0000000', 'timeZone' => 'UTC'],
                            'end' => ['dateTime' => '2026-04-01T11:30:00.0000000', 'timeZone' => 'UTC'],
                        ],
                    ],
                ]);
            }

            if (str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView')) {
                return Http::response([
                    'value' => [
                        [
                            'id' => 'graph-event-1',
                            'subject' => 'Planning',
                            'showAs' => 'busy',
                            'isAllDay' => false,
                            'isCancelled' => false,
                            'start' => ['dateTime' => '2026-04-01T10:00:00.0000000', 'timeZone' => 'UTC'],
                            'end' => ['dateTime' => '2026-04-01T10:30:00.0000000', 'timeZone' => 'UTC'],
                        ],
                    ],
                    '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView/page-2',
                ]);
            }

            return Http::response([], 404);
        });

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.events');
        $response->assertJsonPath('data.events.0.title', 'Planning');
        $response->assertJsonPath('data.events.1.title', 'Follow Up');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView/page-2');
    }

    public function testEventsEndpointSkipsEventsAlreadyConvertedToTasksForTheCurrentUser(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        $convertedEventId = $this->user->id . ':' . CalendarConnection::PROVIDER_GOOGLE . ':' . sha1('primary') . ':event-1';
        $task = TaskFactory::create($this->company->id, $this->user->id);
        $task->description = 'Converted event';
        $task->meta = new TaskMeta(calendar_event_id: $convertedEventId);
        $task->saveQuietly();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event-1',
                        'summary' => 'Already converted',
                        'start' => ['dateTime' => '2026-04-01T09:00:00.000Z'],
                        'end' => ['dateTime' => '2026-04-01T09:30:00.000Z'],
                    ],
                    [
                        'id' => 'event-2',
                        'summary' => 'Available event',
                        'start' => ['dateTime' => '2026-04-01T10:00:00.000Z'],
                        'end' => ['dateTime' => '2026-04-01T10:30:00.000Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-30T23:59:59.999Z',
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.events');
        $response->assertJsonPath('data.events.0.title', 'Available event');
        $response->assertJsonPath('data.events.0.calendar_event_id', $this->user->id . ':' . CalendarConnection::PROVIDER_GOOGLE . ':' . sha1('primary') . ':event-2');
    }

    public function testEventsEndpointReturnsEmptyWhenNoCalendarsAreSelected(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [],
            ],
        ]);
        $this->user->save();

        Http::fake();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-03-28T13:00:00.000Z',
                'to' => '2026-05-02T13:59:59.999Z',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.events', []);
        Http::assertNothingSent();
    }

    public function testEventsEndpointValidatesTheDateRange(): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-05-02T13:59:59.999Z',
                'to' => '2026-03-28T13:00:00.000Z',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }

    public function testEventsEndpointRefreshesExpiredTokenBeforeHydratingEvents(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'expired-google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->subMinute()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-google-access-token',
                'refresh_token' => 'new-google-refresh-token',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event-after-refresh',
                        'summary' => 'Fresh Token Event',
                        'start' => ['dateTime' => '2026-04-01T09:00:00.000Z'],
                        'end' => ['dateTime' => '2026-04-01T09:30:00.000Z'],
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.events.0.title', 'Fresh Token Event');

        $this->user->refresh();

        $this->assertSame('new-google-access-token', $this->user->settings->calendar_connection->access_token);
        $this->assertSame('new-google-refresh-token', $this->user->settings->calendar_connection->refresh_token);
    }

    public function testMicrosoftEventsEndpointRefreshesExpiredTokenBeforeHydratingEvents(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'access_token' => 'expired-microsoft-access-token',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->subMinute()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'default-calendar', 'name' => 'Calendar', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response([
                'access_token' => 'new-microsoft-access-token',
                'refresh_token' => 'new-microsoft-refresh-token',
                'expires_in' => 3600,
            ]),
            'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView*' => Http::response([
                'value' => [
                    [
                        'id' => 'graph-event-after-refresh',
                        'subject' => 'Fresh Token Planning',
                        'showAs' => 'busy',
                        'isAllDay' => false,
                        'isCancelled' => false,
                        'start' => ['dateTime' => '2026-04-01T10:00:00.0000000', 'timeZone' => 'UTC'],
                        'end' => ['dateTime' => '2026-04-01T10:30:00.0000000', 'timeZone' => 'UTC'],
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-04-01T00:00:00.000Z',
                'to' => '2026-04-02T00:00:00.000Z',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.events.0.title', 'Fresh Token Planning');

        $this->user->refresh();

        $this->assertSame('new-microsoft-access-token', $this->user->settings->calendar_connection->access_token);
        $this->assertSame('new-microsoft-refresh-token', $this->user->settings->calendar_connection->refresh_token);
    }

    public function testEventsEndpointCapsOversizedRangesToAMonthViewWindow(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_at' => now()->addHour()->timestamp,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response(['items' => []]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.events', [
                'from' => '2026-01-01T00:00:00.000Z',
                'to' => '2026-04-01T00:00:00.000Z',
            ]))
            ->assertOk();

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['timeMin'] === '2026-01-01T00:00:00.000000Z'
                && $query['timeMax'] === '2026-02-15T00:00:00.000000Z';
        });
    }

    public function testMicrosoftCalendarsRefreshExpiredTokensAndNormalizeGraphShape(): void
    {
        $this->user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'refresh_token' => 'microsoft-refresh-token',
                'expires_at' => now()->subMinute()->timestamp,
                'calendars' => [],
            ],
        ]);
        $this->user->save();

        Http::fake([
            'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response([
                'access_token' => 'microsoft-access-token',
                'refresh_token' => 'new-microsoft-refresh-token',
                'expires_in' => 3600,
            ]),
            'https://graph.microsoft.com/v1.0/me/calendars' => Http::response([
                'value' => [
                    ['id' => 'default-calendar', 'name' => 'Calendar', 'isDefaultCalendar' => true, 'canEdit' => true],
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson(route('api.calendar_connection.calendars'));

        $response->assertOk();
        $response->assertJsonPath('data.calendars.0.calendar_id', 'default-calendar');
        $response->assertJsonPath('data.calendars.0.name', 'Calendar');
        $response->assertJsonPath('data.calendars.0.primary', true);
        $response->assertJsonPath('data.calendars.0.writable', true);
        $this->assertArrayNotHasKey('id', $response->json('data.calendars.0'));
        $this->assertArrayNotHasKey('provider', $response->json('data.calendars.0'));

        $this->user->refresh();

        $this->assertSame('microsoft-access-token', $this->user->settings->calendar_connection->access_token);
        $this->assertSame('new-microsoft-refresh-token', $this->user->settings->calendar_connection->refresh_token);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-API-TOKEN' => $this->token,
        ];
    }

    private function cacheCalendarState(string $provider): string
    {
        $state = Str::random(64);

        Cache::put(CalendarConnectionService::STATE_CACHE_PREFIX . $state, [
            'database' => config('database.default'),
            'provider' => $provider,
            'user_id' => $this->user->id,
        ], now()->addMinutes(10));

        return $state;
    }

    private function cacheCalendarHandoff(string $provider, string $state, string $code = 'oauth-code'): string
    {
        $handoff = Str::random(64);

        Cache::put(CalendarConnectionService::HANDOFF_CACHE_PREFIX . $handoff, [
            'provider' => $provider,
            'state' => $state,
            'code' => $code,
        ], now()->addMinutes(5));

        return $handoff;
    }

    private function cacheOneTimeToken(string $context): string
    {
        $hash = Str::random(64);

        Cache::put($hash, [
            'user_id' => $this->user->id,
            'company_key' => $this->company->company_key,
            'context' => $context,
        ], 3600);

        return $hash;
    }

    /**
     * @return array<string, string>
     */
    private function parseUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * Parses the fragment portion of a SPA URL of the form
     * `https://host/#/path?a=1&b=2` into its query-style params.
     *
     * @return array<string, string>
     */
    private function parseUrlFragmentParams(string $url): array
    {
        $fragment = (string) parse_url($url, PHP_URL_FRAGMENT);
        $queryPart = explode('?', $fragment, 2)[1] ?? '';
        parse_str($queryPart, $params);

        /** @var array<string, string> $params */
        return $params;
    }
}
