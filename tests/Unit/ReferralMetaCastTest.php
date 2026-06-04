<?php

namespace Tests\Unit;

use App\DataMapper\Referral\CalendarConnection;
use App\DataMapper\Referral\ReferralMeta;
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
        $this->assertSame(['free', 'pro', 'enterprise', 'calendar_connection'], array_keys(get_object_vars($referralMeta)));
        $this->assertSame(2, $referralMeta->free);
        $this->assertSame(3, $referralMeta->pro);
        $this->assertSame(4, $referralMeta->enterprise);
        $this->assertSame(CalendarConnection::STATUS_DISCONNECTED, $referralMeta->calendar_connection->status);
        $this->assertSame(['status'], array_keys(get_object_vars($referralMeta->calendar_connection)));
    }

    public function testItDefaultsMissingReferralMetaToTheLegacyResponseShape(): void
    {
        $payload = (new UserTransformer())->transform($this->userWithReferralMeta(null));
        $referralMeta = $payload['referral_meta'];

        $this->assertInstanceOf(\stdClass::class, $referralMeta);
        $this->assertSame(['free', 'pro', 'enterprise', 'calendar_connection'], array_keys(get_object_vars($referralMeta)));
        $this->assertSame(0, $referralMeta->free);
        $this->assertSame(0, $referralMeta->pro);
        $this->assertSame(0, $referralMeta->enterprise);
        $this->assertSame(CalendarConnection::STATUS_DISCONNECTED, $referralMeta->calendar_connection->status);
        $this->assertSame(['status'], array_keys(get_object_vars($referralMeta->calendar_connection)));
    }

    public function testItStoresASingleCalendarConnectionWithMultipleCalendarsAndRedactsTokens(): void
    {
        $meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
        ]);
        $meta->setCalendarConnection(new CalendarConnection([
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
        ]));

        $user = $this->userWithReferralMeta(null);
        $user->referral_meta = $meta;

        $stored = $user->getAttributes()['referral_meta'];

        $this->assertStringNotContainsString('google-access-secret', $stored);
        $this->assertStringNotContainsString('google-refresh-secret', $stored);

        $storedPayload = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('calendar', $storedPayload);
        $this->assertArrayNotHasKey('calendar_connections', $storedPayload);
        $this->assertArrayHasKey('calendar_connection', $storedPayload);
        $this->assertSame(
            ['provider', 'provider_user_id', 'email', 'access_token', 'refresh_token', 'expires_at', 'calendars'],
            array_keys($storedPayload['calendar_connection'])
        );
        $this->assertNotSame('google-access-secret', $storedPayload['calendar_connection']['access_token']);
        $this->assertNotSame('google-refresh-secret', $storedPayload['calendar_connection']['refresh_token']);
        $this->assertCount(2, $storedPayload['calendar_connection']['calendars']);

        $rehydratedMeta = $this->userWithReferralMeta($stored)->referral_meta;

        $this->assertInstanceOf(CalendarConnection::class, $rehydratedMeta->calendar_connection);
        $this->assertSame('google-access-secret', $rehydratedMeta->calendar_connection->access_token);
        $this->assertSame('google-refresh-secret', $rehydratedMeta->calendar_connection->refresh_token);
        $this->assertTrue($rehydratedMeta->calendar_connection->isConnected());

        $response = $rehydratedMeta->toResponseObject();

        $this->assertSame(1, $response->free);
        $this->assertSame(2, $response->pro);
        $this->assertSame(3, $response->enterprise);
        $this->assertObjectHasProperty('calendar_connection', $response);
        $this->assertSame(CalendarConnection::STATUS_CONNECTED, $response->calendar_connection->status);
        $this->assertSame(['status'], array_keys(get_object_vars($response->calendar_connection)));
    }

    public function testReferralCountUpdatesDoNotClearCalendarConnection(): void
    {
        $meta = new ReferralMeta([
            'free' => 1,
            'pro' => 2,
            'enterprise' => 3,
            'calendar_connection' => [
                'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                'provider_user_id' => 'microsoft-user-1',
                'email' => 'calendar@example.com',
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
                'expires_at' => 1893456000,
                'calendars' => [
                    ['calendar_id' => 'calendar-id', 'name' => 'Calendar', 'primary' => true, 'writable' => true],
                ],
            ],
        ]);

        $meta->updateReferralCounts(7, 8, 9);

        $this->assertSame(7, $meta->free);
        $this->assertSame(8, $meta->pro);
        $this->assertSame(9, $meta->enterprise);
        $this->assertInstanceOf(CalendarConnection::class, $meta->calendar_connection);
        $this->assertSame(CalendarConnection::PROVIDER_MICROSOFT, $meta->calendar_connection->provider);
        $this->assertSame('refresh-secret', $meta->calendar_connection->refresh_token);
        $this->assertSame('calendar-id', $meta->calendar_connection->calendars[0]['calendar_id']);
    }

    public function testItHydratesPreviousCalendarConnectionShapesIntoTheSingleConnection(): void
    {
        $legacyCalendar = new ReferralMeta([
            'calendar' => [
                'provider' => CalendarConnection::PROVIDER_GOOGLE,
                'provider_user_id' => 'google-sub-1',
                'refresh_token' => 'legacy-refresh-token',
            ],
        ]);

        $this->assertInstanceOf(CalendarConnection::class, $legacyCalendar->calendar_connection);
        $this->assertSame('legacy-refresh-token', $legacyCalendar->calendar_connection->refresh_token);

        $arrayShape = new ReferralMeta([
            'calendar_connections' => [
                [
                    'provider' => CalendarConnection::PROVIDER_MICROSOFT,
                    'provider_user_id' => 'microsoft-user-1',
                    'refresh_token' => 'array-refresh-token',
                    'calendars' => [['calendar_id' => 'calendar-id']],
                ],
            ],
        ]);

        $this->assertInstanceOf(CalendarConnection::class, $arrayShape->calendar_connection);
        $this->assertSame('array-refresh-token', $arrayShape->calendar_connection->refresh_token);
        $this->assertSame('calendar-id', $arrayShape->calendar_connection->calendars[0]['calendar_id']);
    }

    private function userWithReferralMeta(?string $referralMeta): User
    {
        $user = new User();
        $user->setRawAttributes([
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
            'referral_meta' => $referralMeta,
        ], true);
        $user->setRelation('passkey_credentials', new EloquentCollection());

        return $user;
    }
}
