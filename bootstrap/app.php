<?php

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

$app = new Illuminate\Foundation\Application(
	realpath(__DIR__.'/../')
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
	'Illuminate\Contracts\Http\Kernel',
	'App\Http\Kernel'
);

$app->singleton(
	'Illuminate\Contracts\Console\Kernel',
	'App\Console\Kernel'
);

$app->singleton(
	'Illuminate\Contracts\Debug\ExceptionHandler',
	'App\Exceptions\Handler'
);

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

/*
if (strstr($_SERVER['HTTP_USER_AGENT'], 'PhantomJS') && Utils::isNinjaDev()) {
    $app->loadEnvironmentFrom('.env.testing');
}
*/

// Write info messages to a separate file
$app->configureMonologUsing(function($monolog) {
    $monolog->pushHandler(new Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-info.log', Monolog\Logger::INFO, false));
    $monolog->pushHandler(new Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-warning.log', Monolog\Logger::WARNING, false));
    $monolog->pushHandler(new Monolog\Handler\StreamHandler(storage_path() . '/logs/laravel-error.log', Monolog\Logger::ERROR, false));
});

// Capture real IP if using cloudflare
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
	$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
}

return $app;
