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
use Illuminate\Http\Request; // Import the Request class
use Illuminate\Support\Facades\Http; // Import the Http facade

class BlockonomicsController extends Controller
{
    public function getBTCPrice(Request $request)
    {
        $currency = $request->query('currency');
        $response = Http::get("https://www.blockonomics.co/api/price?currency={$currency}");

        if ($response->successful()) {
            return response()->json(['price' => $response->json('price')]);
        }

        return response()->json(['error' => 'Unable to fetch BTC price'], 500);
    }
}