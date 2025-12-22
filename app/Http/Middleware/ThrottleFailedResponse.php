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

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFailedResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return mixed
     * @todo When using laravel v12, this middleware can be removed and
     * replaced with the following throttler code in RouteServiceProvider.php:
     * ```
     * // Rate limiter for failed responses to prevent brute force attacks
     * // Allows 3 failed attempts per hour
     * RateLimiter::for('failed_response', function (Request $request) {
     * return Limit::perHour(3)
     * ->by($request->ip())
     * ->response(function () {
     * return response()->json([
     * 'message' => 'Too many failed attempts. Please try again later.'
     * ], 429);
     * })
     * ->after(function (\Symfony\Component\HttpFoundation\Response $response) {
     * // Only count failed responses
     * return $response->getStatusCode() >= 400;
     * });
     * });
     * ```
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 3, $decayMinutes = 60)
    {
        $key = 'failed_response:' . $request->ip() . '|' . $request->path();

        // If we've already hit the rate limit, block the request
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many failed attempts. Please try again later.'
            ], 429);
        }

        // Handle the request
        $response = $next($request);

        // Only count the attempt if the response status is 400 or higher
        if ($response->status() >= 400) {
            RateLimiter::hit($key, $decayMinutes * 60);
        }

        return $response;
    }
}
