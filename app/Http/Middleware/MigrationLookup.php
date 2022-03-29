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
use Illuminate\Support\Carbon;
use Utils;

class MigrationLookup
{
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        if (! env('MULTI_DB_ENABLED')) {
            return $next($request);
        }

        //need to wrap an additional block over this to funnel users in a particular range
        if(auth()->user()->id >= config('ninja.migration_user_start') && 
           auth()->user()->id <= config('ninja.migration_user_end') && 
           (!auth()->user()->account->company->plan_expires || Carbon::parse(auth()->user()->account->company->plan_expires)->lt(now())))
        {
                if ($guard == 'user') {
                    
                    if(request()->is('migration/*') || request()->is('settings/*')) {

                        return $next($request);

                    }

                }

                return redirect('/settings/account_management')->with('warning','V4 is now disabled for your account. Please migrate.');
        }
        elseif(!auth()->user()->account->company->plan_expires || Carbon::parse(auth()->user()->account->company->plan_expires)->lt(now())){
            session()->flash('warning','Please consider migrating to V5, V4 has entered end of life.');
        }

        return $next($request);
    }
}
