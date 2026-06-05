<?php

namespace Tests\Unit;

use App\DataMapper\Referral\CalendarConnection;
use App\DataMapper\Referral\ReferralMeta;
use App\DataMapper\UserSettings;
use App\Models\User;
use App\Transformers\UserTransformer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class ReferralMetaCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:' . base64_encode(str_repeat('a', 32))]);
    }

    public function testItHydratesLegacyPayloadAndPreservesTransformerShape(): void
    {
        $user = $this->userWithReferralMeta(json_encode([
            'free' => 2,
            'pro' => 3,
            'enterprise' => 4,
        ], JSON_THROW_ON_ERROR));

        $meta = $user->referral_meta;

        $this->assertInstanceOf(ReferralMeta::class, $meta);
        $this->assertSame(2, $meta->free);
        $this->assertSame(3, $meta->pro);
        $this->assertSame(4, $meta->enterprise);

        $payload = (new UserTransformer())->transform($user);
        $referralMeta = $payload['referral_meta'];

        $this->assertInstanceOf(\stdClass::class, $referralMeta);
        $this->assertSame(['free', 'pro', 'enterprise'], array_keys(get_object_vars($referralMeta)));
        $this->assertSame(2, $referralMeta->free);
        $this->assertSame(3, $referralMeta->pro);
        $this->assertSame(4, $referralMeta->enterprise);
    }

    public function testItDefaultsMissingReferralMetaToTheLegacyResponseShape(): void
    {
        $payload = (new UserTransformer())->transform($this->userWithReferralMeta(null));
        $referralMeta = $payload['referral_meta'];

        $this->assertInstanceOf(\stdClass::class, $referralMeta);
        $this->assertSame(['free', 'pro', 'enterprise'], array_keys(get_object_vars($referralMeta)));
        $this->assertSame(0, $referralMeta->free);
        $this->assertSame(0, $referralMeta->pro);
        $this->assertSame(0, $referralMeta->enterprise);
    }

    public function testReferralMetaIgnoresCalendarConnectionPayloads(): void
    {
        $meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'refresh_token' => 'google-refresh-secret',
            ],
        ]);

        $user = $this->userWithReferralMeta(null);
        $user->referral_meta = $meta;

        $stored = $user->getAttributes()['referral_meta'];
        $storedPayload = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
        ], $storedPayload);
        $this->assertArrayNotHasKey('calendar_connection', $storedPayload);
        $this->assertArrayNotHasKey('calendar', $storedPayload);
        $this->assertArrayNotHasKey('calendar_connections', $storedPayload);
        $this->assertSame(['free', 'pro', 'enterprise'], array_keys($meta->toArray()));
    }

    public function testUserSettingsStoresASingleCalendarConnectionWithMultipleCalendarsAndRedactsTokens(): void
    {
        $settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    [
                        'id' => 'primary',
                        'summary' => 'Primary Calendar',
                        'primary' => true,
                        'accessRole' => 'owner',
                    ],
                    [
                        'id' => 'family-calendar-id',
                        'summary' => 'Family',
                        'primary' => false,
                        'accessRole' => 'writer',
                    ],
                ],
            ],
        ]);

        $user = $this->userWithSettings(null);
        $user->settings = $settings;

        $stored = $user->getAttributes()['settings'];

        $this->assertStringNotContainsString('google-access-secret', $stored);
        $this->assertStringNotContainsString('google-refresh-secret', $stored);

        $storedPayload = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('calendar_connection', $storedPayload);
        $this->assertSame(
            ['provider', 'provider_user_id', 'email', 'access_token', 'refresh_token', 'expires_at', 'calendars'],
            array_keys($storedPayload['calendar_connection'])
        );
        $this->assertNotSame('google-access-secret', $storedPayload['calendar_connection']['access_token']);
        $this->assertNotSame('google-refresh-secret', $storedPayload['calendar_connection']['refresh_token']);
        $this->assertCount(2, $storedPayload['calendar_connection']['calendars']);

        $rehydratedSettings = $this->userWithSettings($stored)->settings;

        $this->assertInstanceOf(CalendarConnection::class, $rehydratedSettings->calendar_connection);
        $this->assertSame('google-access-secret', $rehydratedSettings->calendar_connection->access_token);
        $this->assertSame('google-refresh-secret', $rehydratedSettings->calendar_connection->refresh_token);
        $this->assertTrue($rehydratedSettings->calendar_connection->isConnected());
    }

    public function testUserTransformerExposesLegacyCalendarConnectionUnderSettings(): void
    {
        $user = $this->userWithSettings(null);
        $user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);

        $payload = (new UserTransformer())->transform($user);

        $this->assertArrayHasKey('settings', $payload);
        $this->assertInstanceOf(\stdClass::class, $payload['settings']);
        $this->assertObjectHasProperty('calendar_connection', $payload['settings']);

        $connection = $payload['settings']->calendar_connection;

        $this->assertInstanceOf(\stdClass::class, $connection);
        $this->assertSame(CalendarConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('calendar@example.com', $connection->email);
        $this->assertSame(['status', 'email'], array_keys(get_object_vars($connection)));
    }

    public function testUserTransformerExposesDisconnectedCalendarConnectionUnderSettings(): void
    {
        $payload = (new UserTransformer())->transform($this->userWithSettings(null));

        $this->assertArrayHasKey('settings', $payload);
        $this->assertInstanceOf(\stdClass::class, $payload['settings']);
        $this->assertObjectHasProperty('calendar_connection', $payload['settings']);
        $this->assertSame(CalendarConnection::STATUS_DISCONNECTED, $payload['settings']->calendar_connection->status);
        $this->assertSame('', $payload['settings']->calendar_connection->email);
        $this->assertSame(['status', 'email'], array_keys(get_object_vars($payload['settings']->calendar_connection)));
    }

    public function testUserTransformerDoesNotExposeCalendarStorageOrAliasPayloadsFromSettings(): void
    {
        $user = $this->userWithSettings(json_encode([
            'calendar' => [
                'access_token' => 'legacy-access-secret',
                'refresh_token' => 'legacy-refresh-secret',
            ],
            'calendar_connections' => [
                [
                    'access_token' => 'legacy-array-access-secret',
                    'refresh_token' => 'legacy-array-refresh-secret',
                ],
            ],
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $payload = (new UserTransformer())->transform($user);
        $encodedPayload = json_encode($payload['settings'], JSON_THROW_ON_ERROR);

        $this->assertSame(['calendar_connection'], array_keys(get_object_vars($payload['settings'])));
        $this->assertSame(['status', 'email'], array_keys(get_object_vars($payload['settings']->calendar_connection)));
        $this->assertStringNotContainsString('legacy-access-secret', $encodedPayload);
        $this->assertStringNotContainsString('legacy-refresh-secret', $encodedPayload);
        $this->assertStringNotContainsString('legacy-array-access-secret', $encodedPayload);
        $this->assertStringNotContainsString('legacy-array-refresh-secret', $encodedPayload);
        $this->assertStringNotContainsString('google-access-secret', $encodedPayload);
        $this->assertStringNotContainsString('google-refresh-secret', $encodedPayload);
        $this->assertStringNotContainsString('google-sub-1', $encodedPayload);
        $this->assertStringNotContainsString('primary', $encodedPayload);
    }

    public function testResponseOnlySettingsPayloadDoesNotOverwriteStoredCalendarConnection(): void
    {
        $user = $this->userWithSettings(null);
        $user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);

        $stored = $user->getAttributes()['settings'];
        $rehydratedUser = $this->userWithSettings($stored);
        $payload = (new UserTransformer())->transform($rehydratedUser);

        $rehydratedUser->settings = (array) $payload['settings'];

        $roundTrippedSettings = $this->userWithSettings($rehydratedUser->getAttributes()['settings'])->settings;
        $connection = $roundTrippedSettings->calendar_connection;

        $this->assertInstanceOf(CalendarConnection::class, $connection);
        $this->assertTrue($connection->isConnected());
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame('google-access-secret', $connection->access_token);
        $this->assertSame('google-refresh-secret', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
    }

    public function testStringifiedResponseOnlySettingsPayloadDoesNotOverwriteStoredCalendarConnection(): void
    {
        $user = $this->userWithSettings(null);
        $user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);

        $stored = $user->getAttributes()['settings'];
        $rehydratedUser = $this->userWithSettings($stored);
        $payload = (new UserTransformer())->transform($rehydratedUser);

        $rehydratedUser->settings = json_encode((array) $payload['settings'], JSON_THROW_ON_ERROR);

        $roundTrippedSettings = $this->userWithSettings($rehydratedUser->getAttributes()['settings'])->settings;
        $connection = $roundTrippedSettings->calendar_connection;

        $this->assertInstanceOf(CalendarConnection::class, $connection);
        $this->assertTrue($connection->isConnected());
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame('google-access-secret', $connection->access_token);
        $this->assertSame('google-refresh-secret', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
    }

    public function testTypedResponseOnlySettingsPayloadDoesNotOverwriteStoredCalendarConnection(): void
    {
        $user = $this->userWithSettings(null);
        $user->settings = new UserSettings([
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'email' => 'calendar@example.com',
                'access_token' => 'google-access-secret',
                'refresh_token' => 'google-refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'primary', 'name' => 'Primary', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);

        $stored = $user->getAttributes()['settings'];
        $rehydratedUser = $this->userWithSettings($stored);
        $payload = (new UserTransformer())->transform($rehydratedUser);

        $rehydratedUser->settings = new UserSettings([
            'calendar_connection' => (array) $payload['settings']->calendar_connection,
        ]);

        $roundTrippedSettings = $this->userWithSettings($rehydratedUser->getAttributes()['settings'])->settings;
        $connection = $roundTrippedSettings->calendar_connection;

        $this->assertInstanceOf(CalendarConnection::class, $connection);
        $this->assertTrue($connection->isConnected());
        $this->assertSame('google-sub-1', $connection->provider_user_id);
        $this->assertSame('google-access-secret', $connection->access_token);
        $this->assertSame('google-refresh-secret', $connection->refresh_token);
        $this->assertSame('primary', $connection->calendars[0]['calendar_id']);
    }

    public function testCalendarConnectionLegacyResponseIncludesDisconnectedProviderDetails(): void
    {
        $connection = new CalendarConnection([
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-sub-1',
            'email' => 'calendar@example.com',
        ]);

        $this->assertSame([
            'connected' => false,
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-sub-1',
            'email' => 'calendar@example.com',
        ], $connection->toResponseArray());
    }

    public function testReferralCountUpdatesDoNotInteractWithUserSettings(): void
    {
        $meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
        ]);

        $meta->updateReferralCounts(7, 8, 9);

        $this->assertSame(7, $meta->free);
        $this->assertSame(8, $meta->pro);
        $this->assertSame(9, $meta->enterprise);
        $this->assertSame([
            'free' => 7,
            'pro' => 8,
            'enterprise' => 9,
        ], $meta->toArray());
    }

    private function userWithReferralMeta(?string $referralMeta): User
    {
        return $this->userWithRawAttributes([
            'referral_meta' => $referralMeta,
            'settings' => null,
        ]);
    }

    private function userWithSettings(?string $settings): User
    {
        return $this->userWithRawAttributes([
            'referral_meta' => null,
            'settings' => $settings,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function userWithRawAttributes(array $overrides): User
    {
        $user = new User();
        $user->setRawAttributes(array_merge([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'last_login' => '2026-01-01 00:00:00',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'deleted_at' => null,
            'is_deleted' => false,
            'phone' => '',
            'email_verified_at' => null,
            'signature' => '',
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'oauth_provider_id' => null,
            'last_confirmed_email_address' => null,
            'google_2fa_secret' => null,
            'password' => 'secret',
            'oauth_user_token' => null,
            'verified_phone_number' => false,
            'language_id' => 'en',
            'user_logged_in_notification' => false,
            'referral_code' => 'referral-code',
        ], $overrides), true);
        $user->setRelation('passkey_credentials', new EloquentCollection());

        return $user;
    }
}
