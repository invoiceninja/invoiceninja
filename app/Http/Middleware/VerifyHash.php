<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Company;
use App\Models\PaymentHash;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

class VerifyHash
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
       
        if($request->has('payment_hash')){

            $ph = PaymentHash::with('fee_invoice')->where('hash', $request->payment_hash)->first();

            if($ph)
                auth()->guard('contact')->loginUsingId($ph->fee_invoice->invitations->first()->contact->id, true);

            return $next($request);

        }

        abort(404, 'Unable to verify payment hash');
    }
}
