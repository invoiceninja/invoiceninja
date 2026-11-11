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

use App\Models\CompanyGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

class SquareOAuthTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const SQUARE_GATEWAY_KEY = '65faab2ab6e3223dbe848b1686490baz';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        config([
            'services.square.application_id' => 'square-application-id',
            'services.square.application_secret' => 'square-application-secret',
            'services.square.environment' => 'sandbox',
        ]);
    }

    public function testSquareCallbackRejectsRawCompanyKeyState()
    {
        Http::fake();

        $response = $this->get(route('square.oauth.callback', [
            'state' => $this->company->company_key,
            'code' => 'attacker-code',
        ]));

        $response->assertOk();
        $response->assertViewIs('auth.square_connect.access_denied');

        Http::assertNothingSent();

        $this->assertFalse(
            CompanyGateway::query()
                ->where('company_id', $this->company->id)
                ->where('gateway_key', self::SQUARE_GATEWAY_KEY)
                ->exists()
        );
    }

    public function testSquareOAuthUsesOpaqueStateAndSelectionToken()
    {
        $connect_token = Str::random(64);

        Cache::put($connect_token, [
            'company_key' => $this->company->company_key,
            'user_id' => $this->user->id,
        ], now()->addHour());

        $connect_response = $this->get(route('square.oauth.connect', ['token' => $connect_token]));

        $connect_response->assertRedirect();

        $redirect_url = $connect_response->headers->get('Location');
        parse_str(parse_url($redirect_url, PHP_URL_QUERY), $query);

        $this->assertSame('square-application-id', $query['client_id']);
        $this->assertNotSame($this->company->company_key, $query['state']);
        $this->assertTrue(Cache::has('square_oauth_state:' . $query['state']));
        $this->assertSame(
            $this->company->company_key,
            Cache::get('square_oauth_state:' . $query['state'])['company_key']
        );

        Http::fake([
            'https://connect.squareupsandbox.com/oauth2/token' => Http::response([
                'access_token' => 'square-access-token',
                'refresh_token' => 'square-refresh-token',
                'expires_at' => '2026-05-01T00:00:00Z',
                'merchant_id' => 'merchant-id',
            ], 200),
            'https://connect.squareupsandbox.com/v2/locations' => Http::response([
                'locations' => [
                    [
                        'id' => 'LOCATION-1',
                        'name' => 'Main Location',
                        'status' => 'ACTIVE',
                        'address' => [
                            'address_line_1' => '1 Square Way',
                            'locality' => 'Sydney',
                            'administrative_district_level_1' => 'NSW',
                            'country' => 'AU',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $callback_response = $this->get(route('square.oauth.callback', [
            'state' => $query['state'],
            'code' => 'square-code',
        ]));

        $callback_response->assertOk();
        $callback_response->assertViewIs('auth.square_connect.select_location');

        $this->assertFalse(Cache::has('square_oauth_state:' . $query['state']));
        $this->assertStringNotContainsString('name="company_key"', $callback_response->getContent());
        $this->assertSame(1, preg_match(
            '/name="selection_token" value="([^"]+)"/',
            $callback_response->getContent(),
            $matches
        ));

        $selection_token = $matches[1];

        $this->assertTrue(Cache::has('square_oauth_location:' . $selection_token));

        $location_response = $this->post(route('square.oauth.select_location'), [
            '_token' => session()->token(),
            'selection_token' => $selection_token,
            'location_id' => 'LOCATION-1',
        ]);

        $location_response->assertOk();
        $location_response->assertViewIs('auth.square_connect.completed');

        $this->assertFalse(Cache::has('square_oauth_location:' . $selection_token));

        $company_gateway = CompanyGateway::query()
            ->where('company_id', $this->company->id)
            ->where('gateway_key', self::SQUARE_GATEWAY_KEY)
            ->first();

        $this->assertNotNull($company_gateway);

        $config = $company_gateway->getConfig();

        $this->assertSame('square-access-token', $config->accessToken);
        $this->assertSame('square-refresh-token', $config->refreshToken);
        $this->assertSame('LOCATION-1', $config->locationId);
        $this->assertTrue($config->oauth2);
    }
}
