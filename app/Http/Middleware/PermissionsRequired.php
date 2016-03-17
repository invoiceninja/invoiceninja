<?php namespace App\Http\Middleware;

use Closure;
use Auth;

class PermissionsRequired {
	/**
     * @var array of controller => [action => permission]
     */
    static protected $actions = [];
	
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next, $guard = 'user')
	{
		// Get the current route.
		$route = $request->route();

		// Get the current route actions.
		$actions = $route->getAction();

		// Check if we have any permissions to check the user has.
		if ($permissions = !empty($actions['permissions']) ? $actions['permissions'] : null)
		{    
			if(!Auth::user($guard)->hasPermission($permissions, !empty($actions['permissions_require_all']))){
				return response('Unauthorized.', 401);
			}
		}
		
		// Check controller permissions
		$action = explode('@', $request->route()->getActionName());
		if(isset(static::$actions[$action[0]]) && isset(static::$actions[$action[0]][$action[1]])) {
            $controller_permissions = static::$actions[$action[0]][$action[1]];
			if(!Auth::user($guard)->hasPermission($controller_permissions)){
				return response('Unauthorized.', 401);
			}
        }

		return $next($request);
	}

    /**
     * add a controller's action permission
     *
     * @param \App\Http\Controllers\Controller $controller
     * @param array $permissions
     */
    public static function addPermission(\App\Http\Controllers\Controller $controller, $permissions)
    {
        static::$actions[get_class($controller)] = $permissions;
    }
}
