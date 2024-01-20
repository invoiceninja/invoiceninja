<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use App\Models\User;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

/**
 * Class UserVerified.
 */
class UserVerified
{
    public function __construct(public ?User $user)
    {

        $this->user = property_exists($user, 'id') ? $user : auth()->user();
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Ninja::isSelfHost()) {
            return $next($request);
        }

        $error = [
            'message' => 'Email confirmation required.',
            'errors' => new \stdClass(),
        ];

        if ($this->user && ! $this->user->isVerified()) {
            return response()->json($error, 403);
        }

        return $next($request);
    }
}
