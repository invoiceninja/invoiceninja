<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class Locale
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

        /*LOCALE SET */
        if ($request->has('lang')) {
            $locale = $request->input('lang');
            App::setLocale($locale);
        } elseif (auth('contact')->user()) {
            App::setLocale(auth('contact')->user()->client->locale());
        } elseif (auth()->user()) {
            App::setLocale(auth()->user()->company()->getLocale());
        } else {
            App::setLocale(config('ninja.i18n.locale'));
        }

        return $next($request);
    }
}
