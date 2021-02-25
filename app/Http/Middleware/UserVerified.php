<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use App\Models\User;
use Closure;
use Hashids\Hashids;
use Illuminate\Http\Request;

/**
 * Class UserVerified.
 */
class UserVerified
{
    public $user;

    public function __construct(?User $user)
    {
        $this->user = $user ?: auth()->user();
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

        $error = [
            'message' => 'Email confirmation required.',
            'errors' => new \stdClass,
        ];

        if ($this->user && !$this->user->isVerified()) 
            return response()->json($error, 403);
        
        return $next($request);
    }
}
