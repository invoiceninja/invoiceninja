<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use Closure;
use Hashids\Hashids;
use Illuminate\Http\Request;

/**
 * Class ValidJson.
 */
class ValidJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if (
            $request->isJson() &&
            $request->getContent() !== '' &&
            is_null(json_decode($request->getContent())) &&
            json_last_error() !== JSON_ERROR_NONE
        ) {

            // nlog("Malformed JSON payload.");
            // nlog($request->all());

            return response()->json([
                'message' => 'Malformed JSON payload.',
                'error' => 'Invalid JSON data provided',
            ], 400);
        }

        return $next($request);
    }
}
