<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Closure;
use App\Http\Middleware\TokenAuth;
use App\Http\Middleware\ApiSecretCheck;
use App\Http\Middleware\SetDb;
use App\Http\Middleware\Locale;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class Authenticate
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('mcp.enabled'), 404);

        // Streamable HTTP requires Origin validation, including for authenticated clients.
        if ($request->headers->has('Origin')) {
            $origin = $request->headers->get('Origin');
            $allowed = array_merge([$request->getSchemeAndHttpHost()], config('mcp.allowed_origins', []));
            if (!is_string($origin) || !in_array($origin, $allowed, true)) {
                return response()->json(['message' => 'Origin not allowed.'], 403);
            }
        }

        $bearer = $request->bearerToken();
        $token = $request->header('X-API-TOKEN');
        if ($bearer && $token && !hash_equals($token, $bearer)) {
            abort(403, 'Conflicting API credentials.');
        }
        if ($bearer) {
            $request->headers->set('X-API-TOKEN', $bearer);
        }
        $request->headers->set('Accept', 'application/json, text/event-stream');

        // Reuse the application's authentication and tenant selection, in that order.
        return app(Pipeline::class)->send($request)
            ->through([ApiSecretCheck::class, SetDb::class, TokenAuth::class, Locale::class])
            ->then($next);
    }
}
