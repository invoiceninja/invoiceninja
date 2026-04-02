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

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\Square\OAuthCallbackRequest;
use App\Http\Requests\Square\OAuthConnectRequest;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SquareController extends BaseController
{
    /**
     * Redirect to Square OAuth authorization page.
     */
    public function connect(OAuthConnectRequest $request, string $token): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\Company $company */
        $company = $request->getCompany();

        $base_url = config('services.square.environment') === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        $params = [
            'client_id' => config('services.square.application_id'),
            'scope' => 'PAYMENTS_WRITE PAYMENTS_READ ORDERS_WRITE ORDERS_READ CUSTOMERS_WRITE CUSTOMERS_READ ITEMS_READ ITEMS_WRITE MERCHANT_PROFILE_READ',
            'session' => false,
            'state' => $company->company_key,
        ];

        return redirect()->to(
            sprintf('%s/oauth2/authorize?%s', $base_url, http_build_query($params))
        );
    }

    /**
     * Handle the Square OAuth callback — exchange code for tokens, then show location picker.
     */
    public function callback(OAuthCallbackRequest $request): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        /** @var \App\Models\Company $company */
        $company = $request->getCompany();

        if ($request->query('error')) {
            return view('auth.square_connect.access_denied');
        }

        $base_url = config('services.square.environment') === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        $response = Http::asJson()->post("{$base_url}/oauth2/token", [
            'client_id' => config('services.square.application_id'),
            'client_secret' => config('services.square.application_secret'),
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
        ]);

        if ($response->failed()) {
            return view('auth.square_connect.access_denied');
        }

        $data = $response->json();

        // Fetch locations using the new access token
        $locations_response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $data['access_token'],
        ])->get("{$base_url}/v2/locations");

        $locations = [];

        if ($locations_response->successful()) {
            foreach ($locations_response->json('locations') ?? [] as $location) {
                $locations[] = [
                    'id' => $location['id'],
                    'name' => $location['name'] ?? $location['id'],
                    'status' => $location['status'] ?? 'UNKNOWN',
                    'address' => $this->formatLocationAddress($location),
                ];
            }
        }

        // Store token data in cache so we can finalize after location selection
        $cache_key = 'square_oauth_' . $company->company_key;
        Cache::put($cache_key, [
            'company_key' => $company->company_key,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_at' => $data['expires_at'] ?? '',
            'merchant_id' => $data['merchant_id'] ?? '',
        ], 600);

        return view('auth.square_connect.select_location', [
            'locations' => $locations,
            'company_key' => $company->company_key,
        ]);
    }

    /**
     * Store the selected location and finalize the gateway setup.
     */
    public function selectLocation(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        $request->validate([
            'company_key' => 'required|string',
            'location_id' => 'required|string',
        ]);

        $cache_key = 'square_oauth_' . $request->input('company_key');
        $cached = Cache::pull($cache_key);

        if (! $cached) {
            return view('auth.square_connect.access_denied');
        }

        MultiDB::findAndSetDbByCompanyKey($cached['company_key']);

        $company = Company::query()
            ->where('company_key', $cached['company_key'])
            ->firstOrFail();

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', '65faab2ab6e3223dbe848b1686490baz')
            ->where('company_id', $company->id)
            ->first();

        if ($company_gateway === null) {
            $company_gateway = CompanyGatewayFactory::create($company->id, $company->owner()->id);
            $fees_and_limits = new \stdClass();
            $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits();
            $company_gateway->gateway_key = '65faab2ab6e3223dbe848b1686490baz';
            $company_gateway->fees_and_limits = $fees_and_limits;
            $company_gateway->setConfig([]);
            $company_gateway->token_billing = 'always';
        }

        $payload = [
            'accessToken' => $cached['access_token'],
            'refreshToken' => $cached['refresh_token'],
            'applicationId' => config('services.square.application_id'),
            'locationId' => $request->input('location_id'),
            'signatureKey' => '',
            'testMode' => config('services.square.environment') !== 'production',
            'oauth2' => true,
            'expires_at' => $cached['expires_at'],
        ];

        $company_gateway->setConfig($payload);
        $company_gateway->save();

        $redirect_uri = config('ninja.react_url')
            ? config('ninja.react_url') . '/#/settings/online_payments'
            : config('ninja.app_url');

        return view('auth.square_connect.completed', ['url' => $redirect_uri]);
    }

    private function formatLocationAddress(array $location): string
    {
        $address = $location['address'] ?? [];
        $parts = array_filter([
            $address['address_line_1'] ?? '',
            $address['locality'] ?? '',
            $address['administrative_district_level_1'] ?? '',
            $address['country'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
