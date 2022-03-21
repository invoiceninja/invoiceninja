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


                //need to wrap an additional block over this to funnel users in a particular range

                if ($guard == 'user') {
                    
                    if(request()->is('migration/*') || request()->is('settings/*')) {

                        return $next($request);

                    }


                }

                return redirect('/settings/account_management');

    }
}
