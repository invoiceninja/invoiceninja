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
    private string $migration_notification = 'The Invoice Ninja v4 platform is now disabled for free users. Please <a class="btn btn-primary btn-sm" href="/migration/start">Migrate Now</a> to the new Invoice Ninja v5 platform to remain as a free account.<br><br>
*Not ready for v5? Upgrade to Pro or Enterprise to remain on v4. *Please note that the v4 platform will be "sunset" in November 2022.';

    private string $silo = 'V4 is now disabled for your account. Please migrate. <a class="btn btn-primary btn-sm" href="/migration/start">Migrate Now</a> Upgrade to v5 and take advantage of our <a class="btn btn-danger btn-sm" href="https://invoicing.co/campaign/black_friday_2022">Black friday promo</a>';

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

                return redirect('/settings/account_management')->with('warning',$this->silo);
        }
        elseif(!auth()->user()->account->company->plan_expires || Carbon::parse(auth()->user()->account->company->plan_expires)->lt(now())){
            session()->flash('warning',$this->migration_notification);
        }

        return $next($request);
    }
}
