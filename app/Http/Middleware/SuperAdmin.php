<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use stdClass;

class SuperAdmin
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
        $error = [
            'message' => 'Unauthorized: Super Admin access required',
            'errors' => new stdClass(),
        ];

        if (!auth()->check()) {
            if ($request->json) {
                return response()->json($error, 401);
            }
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        // Get account from user
        $account = $user->account;

        // Check if account is super admin AND email matches the configured super admin email
        $superAdminEmail = env('SUPER_ADMIN_EMAIL');
        
        if (!$account || !$account->is_super_admin) {
            if ($request->json || $request->expectsJson()) {
                return response()->json($error, 403);
            }
            abort(403, 'Super Admin access required');
        }

        // Additional security: verify email matches configured super admin email
        if ($superAdminEmail && $user->email !== $superAdminEmail) {
            if ($request->json || $request->expectsJson()) {
                return response()->json($error, 403);
            }
            abort(403, 'Super Admin access denied');
        }

        return $next($request);
    }
}

