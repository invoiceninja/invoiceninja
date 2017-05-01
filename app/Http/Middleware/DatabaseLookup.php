<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;
use App\Models\LookupContact;
use App\Models\LookupInvitation;
use App\Models\LookupAccountToken;
use App\Models\LookupUser;

class DatabaseLookup
{
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        if (! env('MULTI_DB_ENABLED')) {
            return $next($request);
        }

        if ($guard == 'user') {
            if ($server = session(SESSION_DB_SERVER)) {
                config(['database.default' => $server]);
            } elseif ($email = $request->email) {
                LookupUser::setServerByField('email', $email);
            }
        } elseif ($guard == 'api') {
            if ($token = $request->header('X-Ninja-Token')) {
                LookupAccountToken::setServerByField('token', $token);
            }
        } elseif ($guard == 'contact') {
            if ($key = request()->invitation_key) {
                LookupInvitation::setServerByField('invitation_key', $key);
            } elseif ($key = request()->contact_key) {
                LookupContact::setServerByField('contact_key', $key);
            }
        } elseif ($guard == 'postmark') {
            LookupInvitation::setServerByField('message_id', request()->MessageID);
        }

        return $next($request);
    }
}
