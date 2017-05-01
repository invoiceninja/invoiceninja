<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;
use App\Models\LookupContact;
use App\Models\LookupInvitation;

class DatabaseLookup
{
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        if (! env('MULTI_DB_ENABLED')) {
            return $next($request);
        }

        // user's value is set when logging in
        if ($guard == 'user') {
            if (! session('SESSION_USER_DB_SERVER')) {
                return redirect('/logout');
            }
        // contacts can login with just the URL
        } else {
            if (request()->invitation_key) {
                LookupInvitation::setServerByField('invitation_key', request()->invitation_key);
            } elseif (request()->contact_key) {
                LookupContact::setServerByField('contact_key', request()->contact_key);
            }
        }

        return $next($request);
    }
}
