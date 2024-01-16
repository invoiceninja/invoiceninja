<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class PasswordTimeoutController extends Controller
{
    public function __invoke()
    {
        $cached = Cache::get(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in');

        return $cached ? response()->json(['message' => 'Password is valid'], 200) : response()->json(['message' => 'Invalid Password'], 412);
    }
}
