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

    public function testGoogleAuthorizeEndpointReturnsAProviderUrlWithOpaqueState(): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson(route('api.calendar_connection.authorize', ['provider' => CalendarConnection::PROVIDER_GOOGLE]));

        $response->assertOk();

        $url = $response->json('data.url');
        $query = $this->parseUrlQuery($url);

        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('google-client-id', $query['client_id']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent select_account', $query['prompt']);
        $this->assertSame('true', $query['include_granted_scopes']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/calendar.events', $query['scope']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/calendar.calendarlist.readonly', $query['scope']);
        $this->assertNotEmpty($query['state']);

        $stateContext = Cache::get(CalendarConnectionService::STATE_CACHE_PREFIX . $query['state']);

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $stateContext['provider']);
        $this->assertSame($this->user->id, $stateContext['user_id']);
        $this->assertSame(config('database.default'), $stateContext['database']);
    }

    public function testCallbackStoresSingleCalendarConnectionAndAutoSelectsGooglePrimaryCalendar(): void
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

        $response = $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => $state,
            'code' => 'oauth-code',
        ]));

        $response->assertRedirect('https://react.test/#/settings/user_details/connect?calendar_connection=connected');
        $this->assertFalse(Cache::has(CalendarConnectionService::STATE_CACHE_PREFIX . $state));

        $this->user->refresh();

        $connection = $this->user->referral_meta->calendar_connection;

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
        $this->assertNull($this->user->oauth_user_id);
        $this->assertNull($this->user->oauth_provider_id);
        $this->assertNull($this->user->oauth_user_refresh_token);
    }

    public function testCallbackPreservesExistingRefreshTokenAndSelectedCalendarsWhenProviderOmitsRefreshToken(): void
    {
        $this->user->referral_meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
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

        $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => $state,
            'code' => 'oauth-code',
        ]))->assertRedirect('https://react.test/#/settings/user_details/connect?calendar_connection=connected');

        $this->user->refresh();

        $connection = $this->user->referral_meta->calendar_connection;

        $this->assertSame('new-access-token', $connection->access_token);
        $this->assertSame('existing-refresh-token', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
        Http::assertNothingSent();
        $this->assertSame(1, $this->user->referral_meta->free);
        $this->assertSame(2, $this->user->referral_meta->pro);
        $this->assertSame(3, $this->user->referral_meta->enterprise);
    }

    public function testMicrosoftCallbackAutoSelectsDefaultWritableCalendar(): void
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

        $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_MICROSOFT,
            'state' => $state,
            'code' => 'oauth-code',
        ]))->assertRedirect('https://react.test/#/settings/user_details/connect?calendar_connection=connected');

        $this->user->refresh();

        $connection = $this->user->referral_meta->calendar_connection;

        $this->assertSame(CalendarConnection::PROVIDER_MICROSOFT, $connection->provider);
        $this->assertSame('default-calendar', $connection->calendars[0]['calendar_id']);
        $this->assertSame('Calendar', $connection->calendars[0]['name']);
        $this->assertTrue($connection->calendars[0]['primary']);
        $this->assertTrue($connection->calendars[0]['writable']);
    }

    public function testCallbackStillConnectsWhenCalendarLookupFails(): void
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

        $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => $state,
            'code' => 'oauth-code',
        ]))->assertRedirect('https://react.test/#/settings/user_details/connect?calendar_connection=connected');

        $this->user->refresh();

        $connection = $this->user->referral_meta->calendar_connection;

        $this->assertSame(CalendarConnection::PROVIDER_GOOGLE, $connection->provider);
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame([], $connection->calendars);
    }

    public function testCallbackLeavesCalendarsEmptyWhenProviderOnlyReturnsReadOnlyCalendars(): void
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

        $this->get(route('calendar_connection.callback', [
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'state' => $state,
            'code' => 'oauth-code',
        ]))->assertRedirect('https://react.test/#/settings/user_details/connect?calendar_connection=connected');

        $this->user->refresh();

        $this->assertSame([], $this->user->referral_meta->calendar_connection->calendars);
    }

    public function testCalendarListUpdateAndDisconnectFlow(): void
    {
        $this->user->referral_meta = new ReferralMeta([
            'free' => 4,
            'pro' => 5,
            'enterprise' => 6,
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
        $listResponse->assertJsonPath('data.calendars.0.selected', true);
        $listResponse->assertJsonPath('data.calendars.1.calendar_id', 'work');
        $listResponse->assertJsonPath('data.calendars.1.selected', false);

        $updateResponse = $this->withHeaders($this->apiHeaders())
            ->putJson(route('api.calendar_connection.calendars.update'), [
                'calendar_ids' => ['work'],
            ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.calendar_connection.provider', CalendarConnection::PROVIDER_GOOGLE);
        $updateResponse->assertJsonPath('data.calendar_connection.calendars.0.calendar_id', 'work');
        $this->assertArrayNotHasKey('access_token', $updateResponse->json('data.calendar_connection'));
        $this->assertArrayNotHasKey('refresh_token', $updateResponse->json('data.calendar_connection'));

        $this->user->refresh();
        $this->assertSame('work', $this->user->referral_meta->calendar_connection->calendars[0]['calendar_id']);

        $deleteResponse = $this->withHeaders($this->apiHeaders())
            ->deleteJson(route('api.calendar_connection.destroy'));

        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('data.calendar_connection', null);

        $this->user->refresh();
        $this->assertNull($this->user->referral_meta->calendar_connection);
        $this->assertSame(4, $this->user->referral_meta->free);
        $this->assertSame(5, $this->user->referral_meta->pro);
        $this->assertSame(6, $this->user->referral_meta->enterprise);
    }


    public function testGoogleEventsEndpointReturnsSelectedCalendarEventsInRange(): void
    {
        $this->user->referral_meta = new ReferralMeta([
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
        $response->assertJsonPath('data.events.0.provider', CalendarConnection::PROVIDER_GOOGLE);
        $response->assertJsonPath('data.events.0.provider_event_id', 'event-1');
        $response->assertJsonPath('data.events.0.calendar_id', 'primary');
        $response->assertJsonPath('data.events.0.calendar_name', 'Primary');
        $response->assertJsonPath('data.events.0.title', 'Discovery Call');
        $response->assertJsonPath('data.events.0.start', '2026-04-01T09:00:00.000Z');
        $response->assertJsonPath('data.events.0.end', '2026-04-01T09:30:00.000Z');
        $response->assertJsonPath('data.events.0.all_day', false);
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

    public function testMicrosoftEventsEndpointReturnsSelectedCalendarViewEventsInRange(): void
    {
        $this->user->referral_meta = new ReferralMeta([
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
        $response->assertJsonPath('data.events.0.provider', CalendarConnection::PROVIDER_MICROSOFT);
        $response->assertJsonPath('data.events.0.provider_event_id', 'graph-event-1');
        $response->assertJsonPath('data.events.0.calendar_id', 'default-calendar');
        $response->assertJsonPath('data.events.0.title', 'Planning');
        $response->assertJsonPath('data.events.0.description', 'Plan the work');
        $response->assertJsonPath('data.events.0.location', 'Teams');
        $response->assertJsonPath('data.events.0.start', '2026-04-01T10:00:00.0000000Z');
        $response->assertJsonPath('data.events.0.end', '2026-04-01T10:30:00.0000000Z');
        $response->assertJsonPath('data.events.0.all_day', false);
        $response->assertJsonPath('data.events.0.status', 'busy');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/calendars/default-calendar/calendarView')
                && $query['startDateTime'] === '2026-03-28T13:00:00.000000Z'
                && $query['endDateTime'] === '2026-05-02T13:59:59.999000Z';
        });
    }

    public function testEventsEndpointReturnsEmptyWhenNoCalendarsAreSelected(): void
    {
        $this->user->referral_meta = new ReferralMeta([
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

    public function testMicrosoftCalendarsRefreshExpiredTokensAndNormalizeGraphShape(): void
    {
        $this->user->referral_meta = new ReferralMeta([
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

        $this->user->refresh();

        $this->assertSame('microsoft-access-token', $this->user->referral_meta->calendar_connection->access_token);
        $this->assertSame('new-microsoft-refresh-token', $this->user->referral_meta->calendar_connection->refresh_token);
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

    /**
     * @return array<string, string>
     */
    private function parseUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
