<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class GuestController extends Controller
{
    //
    /**
     * GuestController constructor.
     */
    public function __construct()
    {

    }

    public function defaultRoute()
    {
        if(Auth::check())
            return redirect('/dashboard');
        else
            return redirect('/login');
    }


}
