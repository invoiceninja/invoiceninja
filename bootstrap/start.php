<?php

/*
require_once 'google/appengine/api/app_identity/AppIdentityService.php';
use \google\appengine\api\app_identity\AppIdentityService;

// Define the gethostname function if it does not exist
if (!function_exists('gethostname')) {
    function gethostname() {
        return AppIdentityService::getApplicationId();
    }
}    
*/

/*
$app->instance('path.storage','gs://invoice-ninja');
$app->instance('path.manifest', 'gs://invoice-ninja/meta');

if(strlen(ini_get('google_app_engine.allow_include_gs_buckets'))) {
        $primary_bucket_name = explode(', ', ini_get('google_app_engine.allow_include_gs_buckets'))[0];
        dd($primary_bucket_name);
        $app->instance('path.storage','gs://'.$primary_bucket_name);
        $app->instance('path.manifest', storage_path().'/meta');
}
*/



if (!function_exists('gethostname')) {
    function gethostname() {
        return php_uname('n');
    }
}


/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application;

//$app->redirectIfTrailingSlash();

/*
|--------------------------------------------------------------------------
| Detect The Application Environment
|--------------------------------------------------------------------------
|
| Laravel takes a dead simple approach to your application environments
| so you can just specify a machine name or HTTP host that matches a
| given environment, then we will automatically detect it for you.
|
*/


$env = $app->detectEnvironment(function ()
{
    if (file_exists(__DIR__.'/environment.php'))
    {
        return require __DIR__.'/environment.php';
    }
    else if (isset($_SERVER['LARAVEL_ENV']))
    {
        return $_SERVER['LARAVEL_ENV'];
    }
    else
    {
        return 'development';
    }
});


/*
$env = $app->detectEnvironment(array(
	'development' => ['precise64', 'ubuntu-server-12042-x64-vbox4210'],
	'gae-development' => ['HILLEL-PC','hillel-PC'],
	'gae-production' => ['GNU/Linux'],
    'fortrabbit' => ['instance-zudx3h.nodes.eu1.frbit.com']
));
*/

/*
|--------------------------------------------------------------------------
| Bind Paths
|--------------------------------------------------------------------------
|
| Here we are binding the paths configured in paths.php to the app. You
| should not be changing these here. If you need to change these you
| may do so within the paths.php file and they will be bound here.
|
*/

$app->bindInstallPaths(require __DIR__.'/paths.php');

/*
|--------------------------------------------------------------------------
| Load The Application
|--------------------------------------------------------------------------
|
| Here we will load the Illuminate application. We'll keep this is in a
| separate location so we can isolate the creation of an application
| from the actual running of the application with a given request.
|
*/

$framework = $app['path.base'].'/vendor/laravel/framework/src';

require $framework.'/Illuminate/Foundation/start.php';

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

// http://stackoverflow.com/questions/20293116/override-http-headers-default-settings-x-frame-options
App::forgetMiddleware('Illuminate\Http\FrameGuard');


return $app;