<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Shopify\CallbackRequest;
use App\Http\Requests\Shopify\InitialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ShopifyController
{
    public function index(InitialRequest $request): RedirectResponse
    {
        $request->session()->put('shopify.nonce', Str::random(64));

        $url = __('https://:shop/admin/oauth/authorize?client_id=:client_id&scope=:scope&redirect_uri=:redirect_uri&state=:nonce&grant_options[]=:grant_options', [
            'shop' => $request->shop,
            'client_id' => config('ninja.shopify.client_id'),
            'scope' => 'write_products', // Adjust accordingly
            'redirect_uri' => url('/shopify/callback'),
            'nonce' => $request->session()->get('shopify.nonce'),
            'grant_options' => 'per-user',
        ]);

        return redirect($url);
    }

    public function callback(CallbackRequest $request)
    {
        $url = __('https://:shop/admin/oauth/access_token?client_id=:client_id&client_secret=:client_secret&code=:code', [
            'shop' => $request->shop,
            'client_id' => config('ninja.shopify.client_id'),
            'client_secret' => config('ninja.shopify.client_secret'),
            'code' => $request->code,
        ]);

        $request = Http::post($url);

        if ($request->status() !== 200) {
            return response()->json([
                'message' => __('Unable to connect to Shopify'),
            ], 500);
        }

        // Redirect to the React domain..

        dd($request->json());
    }
}
