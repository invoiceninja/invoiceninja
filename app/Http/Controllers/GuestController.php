<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GuestController extends Controller
{
    //
    /**
     * GuestController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function defaultRoute()
    {Log::error('lets go go og');
        if(Auth::check())
            return redirect('/dashboard');
        else
            return redirect('/login');
    }


}
