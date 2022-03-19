<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;
use App\Models\LookupAccount;
use App\Models\LookupContact;
use App\Models\LookupInvitation;
use App\Models\LookupProposalInvitation;
use App\Models\LookupAccountToken;
use App\Models\LookupUser;
use Auth;
use Utils;

class MigrationLookup
{
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        if (! env('MULTI_DB_ENABLED')) {
            return $next($request);
        }

        if ($guard == 'user') {
            
        }

        return $next($request);
    }
}
