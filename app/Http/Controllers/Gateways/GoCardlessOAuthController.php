<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */


namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoCardless\OAuthConnectRequest;
use Illuminate\Support\Facades\Http;

class GoCardlessOAuthController extends Controller
{
    public function connect(OAuthConnectRequest $request): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $params = [
            'client_id' => config('services.gocardless.client_id'),
            'redirect_uri' => route('gocardless.oauth.confirm', ['token' => $request->getCompany()->company_key]),
            'scope' => 'read_write',
            'response_type' => 'code',
            'prefill[email]' => 'ben@invoiceninja.com',
            'prefill[given_name]' => 'Ben',
            'prefill[family_name]' => 'The Ninja',
            'prefill[organisation_name]' => 'Fishing Store',
            'prefill[country_code]' => 'GB',
        ];

        return redirect()->to(
            sprintf('https://connect-sandbox.gocardless.com/oauth/authorize?%s', http_build_query($params))
        );
    }

    public function confirm(OAuthConnectRequest $request): \Illuminate\Foundation\Application|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $code = $request->query('code');

        $response = Http::post('https://connect-sandbox.gocardless.com/oauth/access_token', [
            'client_id' => config('services.gocardless.client_id'),
            'client_secret' => config('services.gocardless.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        dd($response->body());
    }
}
