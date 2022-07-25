<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use App\Events\User\UserLoggedIn;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\TruthSource;
use Closure;
use Illuminate\Http\Request;
use stdClass;

class TokenAuth
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
        if ($request->header('X-API-TOKEN') && ($company_token = CompanyToken::with(['user', 'company'])->where('token', $request->header('X-API-TOKEN'))->first())) {
            $user = $company_token->user;

            $error = [
                'message' => 'User inactive',
                'errors' => new stdClass,
            ];
            //user who once existed, but has been soft deleted
            if (! $user) {
                return response()->json($error, 403);
            }

            if (Ninja::isHosted() && $company_token->is_system == 0 && ! $user->account->isPaid()) {
                $error = [
                    'message' => 'Feature not available with free / unpaid account.',
                    'errors' => new stdClass,
                ];

                return response()->json($error, 403);
            }

            $truth = app()->make(TruthSource::class);

            $truth->setCompanyUser($company_token->cu);
            $truth->setUser($company_token->user);
            $truth->setCompany($company_token->company);
            $truth->setCompanyToken($company_token);

            /*
            |
            | Necessary evil here: As we are authenticating on CompanyToken,
            | we need to link the company to the user manually. This allows
            | us to decouple a $user and their attached companies completely.
            |
            */

            app('queue')->createPayloadUsing(function () use ($company_token) {
                return ['db' => $company_token->company->db];
            });

            //user who once existed, but has been soft deleted
            if ($company_token->cu->is_locked) {
                $error = [
                    'message' => 'User access locked',
                    'errors' => new stdClass,
                ];

                return response()->json($error, 403);
            }

            //stateless, don't remember the user.
            auth()->login($user, false);
            auth()->user()->setCompany($company_token->company);
        } else {
            $error = [
                'message' => 'Invalid token',
                'errors' => new stdClass,
            ];

            return response()->json($error, 403);
        }

        return $next($request);
    }
}
