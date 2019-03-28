<?php

namespace App\Http\Controllers;


class DashboardController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       // dd(json_decode(auth()->user()->permissions(),true));
        return view('dashboard.index');
    }


}
