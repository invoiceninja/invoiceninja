<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Auth;
use Closure;
use Illuminate\Http\Request;

/**
 * Class PermissionsRequired.
 */
class PermissionsRequired
{
    /**
     * @var array
     */
    protected static $actions = [];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string  $guard
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = 'user')
    {
        // Get the current route.
        $route = $request->route();

        // Get the current route actions.
        $actions = $route->getAction();

        // Check if we have any permissions to check the user has.
        if ($permissions = ! empty($actions['permissions']) ? $actions['permissions'] : null) {
            if (! Auth::user($guard)->hasPermission($permissions, ! empty($actions['permissions_require_all']))) {
                return response('Unauthorized.', 401);
            }
        }

        // Check controller permissions
        $action = explode('@', $request->route()->getActionName());
        if (isset(static::$actions[$action[0]]) && isset(static::$actions[$action[0]][$action[1]])) {
            $controller_permissions = static::$actions[$action[0]][$action[1]];
            if (! Auth::user($guard)->hasPermission($controller_permissions)) {
                return response('Unauthorized.', 401);
            }
        }

        return $next($request);
    }

    /**
     * add a controller's action permission.
     *
     * @param Controller $controller
     * @param array      $permissions
     */
    public static function addPermission(Controller $controller, array $permissions)
    {
        static::$actions[get_class($controller)] = $permissions;
    }
}
