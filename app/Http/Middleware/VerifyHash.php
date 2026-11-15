<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Http\Middleware;

use App\Models\PaymentHash;
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
        $paymentHash = $request->input('payment_hash');

        if (is_string($paymentHash) && $paymentHash !== '') {
            $ph = PaymentHash::query()->with('fee_invoice')->where('hash', $paymentHash)->first();

            if ($ph) {
                auth()->guard('contact')->loginUsingId($ph->fee_invoice->invitations->first()->contact->id, true);
            }

            return $next($request);
        }

        abort(404, 'Unable to verify payment hash');
    }
}
